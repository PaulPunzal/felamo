<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'Method not allowed.']);
    exit;
}

$input         = json_decode(file_get_contents("php://input"), true);
$session_id    = trim($input['session_id']    ?? '');
$assessment_id = (int)($input['assessment_id'] ?? 0);

if (empty($session_id) || empty($assessment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Assessment ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// ── 1. Resolve session → user_id ─────────────────────────────────────────────
$sess = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$sess->bind_param("s", $session_id);
$sess->execute();
$sess_row = $sess->get_result()->fetch_assoc();
$sess->close();

if (!$sess_row) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = (int)$sess_row['user_id'];

// ── 2. Resolve user_id → lrn & current points ────────────────────────────────
$uq = $conn->prepare("SELECT lrn, points FROM users WHERE id = ?");
$uq->bind_param("i", $user_id);
$uq->execute();
$user = $uq->get_result()->fetch_assoc();
$uq->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}
$lrn = $user['lrn'];

// ── 3. Get aralin_id from this assessment (for rewatch flag scoping) ──────────
$aq = $conn->prepare("SELECT aralin_id FROM assessments WHERE id = ? LIMIT 1");
$aq->bind_param("i", $assessment_id);
$aq->execute();
$aralin_row = $aq->get_result()->fetch_assoc();
$aq->close();
$aralin_id = (int)($aralin_row['aralin_id'] ?? 0);

// ── 4. Block retakes if student already PASSED THIS specific assessment ────────
// FIX: Scope check to the exact assessment_id so passing Aralin 1 never
// blocks Aralin 2, 3, etc.
$already_done = $conn->prepare(
    "SELECT id FROM assessment_results
     WHERE assessment_id = ? AND lrn = ? AND is_completed = 1
     LIMIT 1"
);
$already_done->bind_param("is", $assessment_id, $lrn);
$already_done->execute();
$already_done->store_result();
$is_already_done = ($already_done->num_rows > 0);
$already_done->close();

if ($is_already_done) {
    echo json_encode([
        'status'  => 'already_taken',
        'message' => 'Nasagutan mo na ang pagsusulit na ito.',
    ]);
    exit;
}

// ── 5. Collect all submitted answers ─────────────────────────────────────────
$all_answers = array_merge(
    $input['multiple_choices'] ?? [],
    $input['true_or_false']    ?? [],
    $input['identification']   ?? [],
    $input['jumbled_words']    ?? []
);

// FIX: total_items comes from the number of questions actually submitted,
// NOT from a hardcoded value. This makes Aralin 1 (13 items) and
// Aralin 2 (15 items) each use their own correct denominator.
$total_items = count($all_answers);

if ($total_items === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No answers submitted.']);
    exit;
}

// ── 6. Grade all submitted answers ───────────────────────────────────────────
$score = 0;
foreach ($all_answers as $item) {
    $q_id        = (int)($item['question_id'] ?? 0);
    $user_answer = trim((string)($item['answer'] ?? ''));

    // FIX: Also verify question belongs to THIS assessment to prevent
    // cross-aralin answer injection attacks
    $qstmt = $conn->prepare(
        "SELECT type, correct_answer, choices
         FROM questions
         WHERE id = ? AND assessment_id = ?"
    );
    $qstmt->bind_param("ii", $q_id, $assessment_id);
    $qstmt->execute();
    $q = $qstmt->get_result()->fetch_assoc();
    $qstmt->close();

    if (!$q) continue; // Skip questions that don't belong to this assessment

    $correct = trim($q['correct_answer']);

    if ($q['type'] === 'multiple_choice') {
        // Both $user_answer and $correct are just letters (A, B, C, D)
        if (strtoupper($user_answer) === strtoupper($correct)) {
            $score++;
        }
    } elseif ($q['type'] === 'true_false') {
        $db_is_true = in_array(strtolower($correct), ['true', '1', 'tama']) ? 1 : 0;
        if ((int)$user_answer === $db_is_true) {
            $score++;
        }
    } else {
        // identification & jumbled_word — case-insensitive exact match
        if (strtolower($user_answer) === strtolower($correct)) {
            $score++;
        }
    }
}

// ── 7. Count attempts for THIS assessment only (from log table) ───────────────
$attempt_q = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM assessment_attempt_logs
     WHERE assessment_id = ? AND lrn = ?"
);
$attempt_q->bind_param("is", $assessment_id, $lrn);
$attempt_q->execute();
$attempt_cnt = (int)($attempt_q->get_result()->fetch_assoc()['cnt'] ?? 0) + 1;
$attempt_q->close();

// Always log this attempt regardless of pass/fail
$log_stmt = $conn->prepare(
    "INSERT INTO assessment_attempt_logs (assessment_id, lrn, score, total, attempted_at)
     VALUES (?, ?, ?, ?, NOW())"
);
$log_stmt->bind_param("isii", $assessment_id, $lrn, $score, $total_items);
$log_stmt->execute();
$log_stmt->close();

// ── 8. Pass / Fail decision ───────────────────────────────────────────────────
// 50% threshold — computed against the actual item count for this aralin
$pass_threshold = 0.50;
$passed         = ($total_items > 0) && (($score / $total_items) >= $pass_threshold);

if ($passed) {
    // Check if this is the student's first-ever pass for THIS assessment
    $prev = $conn->prepare(
        "SELECT id FROM assessment_results WHERE assessment_id = ? AND lrn = ? LIMIT 1"
    );
    $prev->bind_param("is", $assessment_id, $lrn);
    $prev->execute();
    $prev->store_result();
    $first_pass = ($prev->num_rows === 0);
    $prev->close();

    if ($first_pass) {
        // ── Record the official pass ──────────────────────────────────────
        $ins = $conn->prepare(
            "INSERT INTO assessment_results
                 (assessment_id, lrn, points, total, is_completed, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())"
        );
        $ins->bind_param("isii", $assessment_id, $lrn, $score, $total_items);
        $ins->execute();
        $ins->close();

        // ── Save every answer for the history view of THIS aralin ─────────
        $ans_stmt = $conn->prepare(
            "INSERT INTO assessment_answer_logs
                 (assessment_id, lrn, question_id, student_answer, attempted_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        foreach ($all_answers as $item) {
            $q_id        = (int)($item['question_id'] ?? 0);
            $user_answer = trim((string)($item['answer'] ?? ''));
            $ans_stmt->bind_param("isis", $assessment_id, $lrn, $q_id, $user_answer);
            $ans_stmt->execute();
        }
        $ans_stmt->close();

        // ── Award bonus points ────────────────────────────────────────────
        $bonus = 35;
        $upd = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $upd->bind_param("ii", $bonus, $user_id);
        $upd->execute();
        $upd->close();

    } else {
        // Already has a pass record (re-passed after rewatch) — no bonus
        $bonus = 0;
    }

    // ── Clear rewatch flag for THIS aralin only ───────────────────────────
    // FIX: Scope the UPDATE to the exact aralin_id so clearing Aralin 1's
    // rewatch flag never accidentally affects Aralin 2, 3, etc.
    if ($aralin_id > 0) {
        $clr = $conn->prepare(
            "UPDATE student_aralin_progress
             SET needs_rewatch = 0
             WHERE user_id = ? AND aralin_id = ?"
        );
        $clr->bind_param("ii", $user_id, $aralin_id);
        $clr->execute();
        $clr->close();
    }

    echo json_encode([
        'status'       => 'success',
        'raw_points'   => $score,
        'total_items'  => $total_items,
        'bonus_points' => $bonus,
        'first_pass'   => $first_pass,
        'attempts'     => $attempt_cnt,
        'is_completed' => true,
    ]);

} else {
    // ── FAILED — require rewatch for THIS aralin before retrying ─────────
    // FIX: Scope to aralin_id so a failed attempt on Aralin 2 doesn't
    // force a rewatch of Aralin 1.
    if ($aralin_id > 0) {
        $rw = $conn->prepare(
            "UPDATE student_aralin_progress
             SET needs_rewatch = 1
             WHERE user_id = ? AND aralin_id = ?"
        );
        $rw->bind_param("ii", $user_id, $aralin_id);
        $rw->execute();
        $rw->close();
    }

    $percentage = ($total_items > 0) ? round(($score / $total_items) * 100) : 0;

    echo json_encode([
        'status'       => 'failed',
        'raw_points'   => $score,
        'total_items'  => $total_items,
        'percentage'   => $percentage,
        'attempts'     => $attempt_cnt,
        'is_completed' => false,
        'message'      => 'Hindi nakamit ang 50%. Pakitingnan muli ang aralin bago muling sumubok.',
    ]);
}