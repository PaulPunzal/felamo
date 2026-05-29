<?php
// backend/api/web/item_analysis.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST method required.']);
    exit;
}

$requestType = $_POST['requestType'] ?? '';

$db   = new db_connect();
$conn = $db->connect();

// ── GetItemAnalysis ────────────────────────────────────────────────────────────
// Given an assessment_id, returns every question with:
//   - question text, type, difficulty
//   - total_takers  : how many distinct students submitted an answer
//   - correct_count : how many got it right
//   - wrong_count   : how many got it wrong
//   - percent_correct
if ($requestType === 'GetItemAnalysis') {

    $assessment_id = (int)($_POST['assessment_id'] ?? 0);

    if (!$assessment_id) {
        echo json_encode(['status' => 'error', 'message' => 'assessment_id is required.']);
        exit;
    }

    // ── 1. Header info: assessment + aralin + level ────────────────────────
    $header_stmt = $conn->prepare("
        SELECT
            a.id          AS assessment_id,
            a.assessment_title,
            a.description AS assessment_description,
            arl.aralin_no,
            arl.aralin_title,
            l.level       AS markahan_level,
            l.id          AS level_id
        FROM assessments AS a
        JOIN aralin  AS arl ON a.aralin_id  = arl.id
        JOIN levels  AS l   ON arl.level_id = l.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $header_stmt->bind_param('i', $assessment_id);
    $header_stmt->execute();
    $header = $header_stmt->get_result()->fetch_assoc();
    $header_stmt->close();

    if (!$header) {
        echo json_encode(['status' => 'error', 'message' => 'Assessment not found.']);
        exit;
    }

    // ── 2. Total unique students who took this assessment ──────────────────
    $takers_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT lrn) AS total_takers
        FROM assessment_results
        WHERE assessment_id = ? AND is_completed = 1
    ");
    $takers_stmt->bind_param('i', $assessment_id);
    $takers_stmt->execute();
    $takers_row   = $takers_stmt->get_result()->fetch_assoc();
    $total_takers = (int)($takers_row['total_takers'] ?? 0);
    $takers_stmt->close();

    // ── 3. Per-question stats ──────────────────────────────────────────────
    // questions uses utf8mb4_0900_ai_ci; assessment_answer_logs uses
    // utf8mb4_general_ci.  Adding COLLATE utf8mb4_general_ci to every
    // q.* string column that is compared against aal.* columns or string
    // literals fixes the "Illegal mix of collations" error.
    $q_stmt = $conn->prepare("
        SELECT
            q.id              AS question_id,
            q.question_text,
            q.type,
            q.difficulty,
            q.choices,
            q.correct_answer,
            COUNT(aal.id)     AS total_answers,

            SUM(
                CASE q.type COLLATE utf8mb4_general_ci

                    WHEN 'multiple_choice' THEN
                        IF(
                            UPPER(TRIM(aal.student_answer))
                            = UPPER(TRIM(q.correct_answer COLLATE utf8mb4_general_ci)),
                            1, 0
                        )

                    WHEN 'true_false' THEN
                        IF(
                            (LOWER(TRIM(aal.student_answer)) IN ('1','true','tama'))
                            =
                            (LOWER(TRIM(q.correct_answer COLLATE utf8mb4_general_ci))
                                IN ('1','true','tama')),
                            1, 0
                        )

                    ELSE
                        IF(
                            LOWER(TRIM(aal.student_answer))
                            = LOWER(TRIM(q.correct_answer COLLATE utf8mb4_general_ci)),
                            1, 0
                        )
                END
            ) AS correct_count

        FROM questions AS q
        LEFT JOIN assessment_answer_logs AS aal
               ON aal.question_id   = q.id
              AND aal.assessment_id = q.assessment_id
        WHERE q.assessment_id = ?
        GROUP BY q.id
        ORDER BY q.id ASC
    ");
    $q_stmt->bind_param('i', $assessment_id);
    $q_stmt->execute();
    $q_result = $q_stmt->get_result();

    $questions = [];
    while ($row = $q_result->fetch_assoc()) {
        $correct = (int)$row['correct_count'];
        $total   = (int)$row['total_answers'];
        $wrong   = $total - $correct;
        $pct     = $total > 0 ? round(($correct / $total) * 100) : 0;

        // Decode MCQ choices for display
        $choices_decoded = null;
        if ($row['type'] === 'multiple_choice' && !empty($row['choices'])) {
            $choices_decoded = json_decode($row['choices'], true);
        }

        $questions[] = [
            'question_id'     => (int)$row['question_id'],
            'question_text'   => $row['question_text'],
            'type'            => $row['type'],
            'difficulty'      => $row['difficulty'],
            'correct_answer'  => $row['correct_answer'],
            'choices'         => $choices_decoded,
            'total_answers'   => $total,
            'correct_count'   => $correct,
            'wrong_count'     => $wrong,
            'percent_correct' => $pct,
        ];
    }
    $q_stmt->close();

    // ── 4. Summary stats ───────────────────────────────────────────────────
    $total_q        = count($questions);
    $easy_pct_sum   = 0;
    $hard_questions = []; // questions where < 50% got it right

    foreach ($questions as $q) {
        $easy_pct_sum += $q['percent_correct'];
        if ($q['percent_correct'] < 50 && $q['total_answers'] > 0) {
            $hard_questions[] = $q['question_id'];
        }
    }

    $avg_pct = $total_q > 0 ? round($easy_pct_sum / $total_q) : 0;

    echo json_encode([
        'status'        => 'success',
        'header'        => $header,
        'total_takers'  => $total_takers,
        'total_questions' => $total_q,
        'avg_percent_correct' => $avg_pct,
        'hard_question_ids'   => $hard_questions,
        'questions'     => $questions,
    ]);
    exit;
}

// ── Unknown requestType ────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => "Unknown requestType: $requestType"]);