<?php 
include("components/header.php"); 

$result_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$result_id) {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}

// Fetch the assessment result row
include_once('../backend/db/db.php');
$db   = new db_connect();
$conn = $db->connect();

$stmt = $conn->prepare("
    SELECT 
        ar.id,
        ar.lrn,
        ar.points,
        ar.total,
        ar.created_at,
        ar.is_completed,
        ar.attempt_number,
        a.id          AS assessment_id,
        a.assessment_title,
        a.description AS assessment_description,
        arl.aralin_title,
        arl.aralin_no,
        l.level       AS markahan_level,
        l.id          AS level_id,
        u.first_name,
        u.last_name,
        u.lrn         AS student_lrn
    FROM assessment_results AS ar
    JOIN assessments        AS a   ON ar.assessment_id = a.id
    JOIN aralin             AS arl ON a.aralin_id = arl.id
    JOIN levels             AS l   ON arl.level_id = l.id
    JOIN users              AS u   ON ar.lrn = u.lrn
    WHERE ar.id = ?
    LIMIT 1
");

if (!$stmt) {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}

$stmt->bind_param("i", $result_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}

// Fetch individual answer logs for this assessment + student
$ans_stmt = $conn->prepare("
    SELECT 
        aal.question_id,
        aal.student_answer,
        aal.attempted_at,
        q.question_text,
        q.type,
        q.choices,
        q.correct_answer,
        q.difficulty
    FROM assessment_answer_logs AS aal
    JOIN questions AS q ON aal.question_id = q.id
    WHERE aal.assessment_id = ? AND aal.lrn = ?
    ORDER BY aal.id ASC
");

$answers = [];
if ($ans_stmt) {
    $aid = $result['assessment_id'];
    $lrn = $result['student_lrn'];
    $ans_stmt->bind_param("is", $aid, $lrn);
    $ans_stmt->execute();
    $ans_result = $ans_stmt->get_result();
    while ($row = $ans_result->fetch_assoc()) {
        // Decode MCQ choices JSON
        if ($row['type'] === 'multiple_choice' && !empty($row['choices'])) {
            $row['choices_decoded'] = json_decode($row['choices'], true) ?? [];
        } else {
            $row['choices_decoded'] = [];
        }
        $answers[] = $row;
    }
    $ans_stmt->close();
}

// Helpers
$score      = (int)$result['points'];
$total      = (int)$result['total'];
$pct        = $total > 0 ? round(($score / $total) * 100) : 0;
$passed     = $total > 0 && $pct >= 80;

$ordinalMap = [1 => "Unang", 2 => "Ikalawang", 3 => "Ikatlong", 4 => "Ika-apat na"];
$markahan   = isset($ordinalMap[$result['markahan_level']]) 
              ? $ordinalMap[$result['markahan_level']] . " Markahan" 
              : "Markahan " . $result['markahan_level'];

// Resolve student's answer display text for MCQ
function resolveDisplayAnswer($type, $studentAnswer, $choicesDecoded) {
    if ($type === 'multiple_choice' && !empty($choicesDecoded)) {
        $key = strtoupper(trim($studentAnswer));
        return isset($choicesDecoded[$key]) 
               ? "$key: " . $choicesDecoded[$key] 
               : $studentAnswer;
    }
    if ($type === 'true_false') {
        return $studentAnswer == '1' || strtolower($studentAnswer) === 'true' ? 'True (Tama)' : 'False (Mali)';
    }
    return $studentAnswer;
}

function resolveCorrectDisplay($type, $correctAnswer, $choicesDecoded) {
    if ($type === 'multiple_choice' && !empty($choicesDecoded)) {
        $key = strtoupper(trim($correctAnswer));
        return isset($choicesDecoded[$key])
               ? "$key: " . $choicesDecoded[$key]
               : $correctAnswer;
    }
    if ($type === 'true_false') {
        return (in_array(strtolower($correctAnswer), ['true', '1', 'tama'])) ? 'True (Tama)' : 'False (Mali)';
    }
    return $correctAnswer;
}

function isCorrect($type, $studentAnswer, $correctAnswer) {
    if ($type === 'true_false') {
        $studentBool = in_array(strtolower(trim($studentAnswer)), ['1','true','tama']);
        $correctBool = in_array(strtolower(trim($correctAnswer)), ['1','true','tama']);
        return $studentBool === $correctBool;
    }
    return strtolower(trim($studentAnswer)) === strtolower(trim($correctAnswer));
}

$typeLabels = [
    'multiple_choice' => 'Multiple Choice',
    'true_false'      => 'True or False',
    'identification'  => 'Identification',
    'jumbled_word'    => 'Jumbled Word',
];

$diffColors = [
    'easy'   => 'success',
    'medium' => 'warning',
    'hard'   => 'danger',
];
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_level_id" value="<?= htmlspecialchars($result['level_id']) ?>">

<style>
    nav.navbar { display: none !important; }
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; background-color: #f8f9fa; transition: margin-left 0.3s ease-in-out; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* Sidebar */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255,255,255,0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107 !important; color: #440101 !important; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; cursor: pointer; }

    /* Header */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
    }
    .btn-back-text {
        background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.5);
        font-size: 0.9rem; font-weight: 600; padding: 8px 20px; border-radius: 50px; text-decoration: none;
        transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-back-text:hover { background-color: white; color: #a71b1b; }

    /* Result summary card */
    .result-summary {
        background: white;
        border-radius: 12px;
        padding: 28px 32px;
        margin-bottom: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        border: 1px solid #dee2e6;
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        align-items: center;
    }
    .score-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        flex-shrink: 0;
    }
    .score-circle.pass { background: #d4edda; color: #155724; border: 4px solid #28a745; }
    .score-circle.fail { background: #f8d7da; color: #721c24; border: 4px solid #dc3545; }
    .score-circle .pct  { font-size: 2rem; line-height: 1; }
    .score-circle .lbl  { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }

    .result-info { flex: 1; min-width: 220px; }
    .result-info h5 { font-weight: 800; color: #212529; margin-bottom: 6px; }
    .result-info .meta { font-size: 0.88rem; color: #6c757d; margin-bottom: 3px; }
    .result-info .meta strong { color: #495057; }

    .result-badge { 
        font-size: 1rem; font-weight: 700; 
        padding: 6px 18px; border-radius: 50px;
    }
    .badge-pass { background: #28a745; color: white; }
    .badge-fail { background: #dc3545; color: white; }

    /* Answer cards */
    .answer-card {
        background: white;
        border-radius: 10px;
        padding: 20px 24px;
        margin-bottom: 14px;
        border: 1px solid #dee2e6;
        border-left-width: 5px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .answer-card.correct  { border-left-color: #28a745; }
    .answer-card.wrong    { border-left-color: #dc3545; }
    .answer-card.no-log   { border-left-color: #6c757d; }

    .answer-card .q-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }
    .answer-card .q-num {
        font-weight: 800; font-size: 0.8rem; 
        color: #6c757d; white-space: nowrap;
    }
    .answer-card .q-text {
        font-weight: 600; font-size: 1rem; color: #212529;
        flex: 1;
    }
    .answer-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .answer-box {
        flex: 1;
        min-width: 160px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.88rem;
    }
    .answer-box.student  { background: #f0f0f0; }
    .answer-box.student.correct-ans { background: #d4edda; }
    .answer-box.student.wrong-ans   { background: #f8d7da; }
    .answer-box.correct-key { background: #d4edda; }
    .answer-box label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #6c757d; display: block; margin-bottom: 3px; }
    .answer-box .val { font-weight: 600; color: #212529; }

    /* No answers notice */
    .no-answers-notice {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 16px 20px;
        color: #856404;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .section-heading {
        font-weight: 800; font-size: 1rem; color: #a71b1b;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin: 28px 0 14px 0;
        display: flex; align-items: center; gap: 8px;
    }

    @media print {
        .sidebar, .sidebar-toggle, .page-header-banner .btn-back-text { display: none !important; }
        .main-content { margin-left: 0 !important; }
    }

    @media (max-width: 991.98px) {
        .main-content { margin-left: 0; padding: 1rem; }
        .result-summary { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="dashboard-wrapper">

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">

        <!-- Page header -->
        <div class="page-header-banner">
            <div style="display:flex; align-items:center; gap:15px;">
                <a href="taken_assessments.php?level=<?= htmlspecialchars($result['level_id']) ?>" class="btn-back-text">
                    <i class="bi bi-arrow-left"></i> BACK
                </a>
                <h4 class="m-0 fw-bold text-uppercase" style="font-size:1.3rem;">
                    Result &mdash; <?= htmlspecialchars($result['assessment_title']) ?>
                </h4>
            </div>
            <button onclick="window.print()" class="btn btn-light btn-sm fw-bold shadow-sm">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>

        <!-- Summary card -->
        <div class="result-summary">
            <div class="score-circle <?= $passed ? 'pass' : 'fail' ?>">
                <span class="pct"><?= $pct ?>%</span>
                <span class="lbl"><?= $passed ? 'PASSED' : 'FAILED' ?></span>
            </div>

            <div class="result-info">
                <h5><?= htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) ?></h5>
                <div class="meta">LRN: <strong><?= htmlspecialchars($result['student_lrn']) ?></strong></div>
                <div class="meta">Assessment: <strong><?= htmlspecialchars($result['assessment_title']) ?></strong></div>
                <div class="meta">Aralin: <strong>Aralin <?= (int)$result['aralin_no'] ?> &mdash; <?= htmlspecialchars($result['aralin_title']) ?></strong></div>
                <div class="meta">Markahan: <strong><?= htmlspecialchars($markahan) ?></strong></div>
                <div class="meta">Date Taken: <strong><?= date('F j, Y g:i A', strtotime($result['created_at'])) ?></strong></div>
            </div>

            <div style="display:flex; flex-direction:column; align-items:center; gap:12px;">
                <span class="result-badge <?= $passed ? 'badge-pass' : 'badge-fail' ?>">
                    <i class="bi bi-<?= $passed ? 'check-circle-fill' : 'x-circle-fill' ?> me-1"></i>
                    <?= $passed ? 'PASSED' : 'FAILED' ?>
                </span>
                <div class="text-center">
                    <div style="font-size:2rem; font-weight:900; color:#a71b1b; line-height:1;">
                        <?= $score ?> / <?= $total ?>
                    </div>
                    <div style="font-size:0.8rem; color:#6c757d; font-weight:600;">RAW SCORE</div>
                </div>
                <div class="text-center text-muted" style="font-size:0.82rem;">
                    Passing: 80% (<?= $total > 0 ? ceil($total * 0.8) : 'N/A' ?> items)
                </div>
            </div>
        </div>

        <!-- Answer breakdown -->
        <?php if (empty($answers)): ?>
            <div class="no-answers-notice">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>No answer log available</strong> for this submission.
                This can happen when the student took an earlier version of the quiz before detailed logging was enabled.
                The score above (<?= $score ?>/<?= $total ?>) is still accurate.
            </div>
        <?php else: ?>

            <div class="section-heading">
                <i class="bi bi-list-check"></i>
                Answer Breakdown 
                <span class="badge bg-secondary ms-1" style="font-size:0.8rem; font-weight:600;"><?= count($answers) ?> items</span>
            </div>

            <?php 
            $qNum = 0;
            foreach ($answers as $ans):
                $qNum++;
                $type         = $ans['type'];
                $displayType  = $typeLabels[$type] ?? ucfirst($type);
                $difficulty   = $ans['difficulty'] ?? 'medium';
                $diffColor    = $diffColors[$difficulty] ?? 'secondary';

                $studentDisplay = resolveDisplayAnswer($type, $ans['student_answer'], $ans['choices_decoded']);
                $correctDisplay = resolveCorrectDisplay($type, $ans['correct_answer'], $ans['choices_decoded']);
                $correct        = isCorrect($type, $ans['student_answer'], $ans['correct_answer']);
                $cardClass      = $correct ? 'correct' : 'wrong';
            ?>
            <div class="answer-card <?= $cardClass ?>">
                <div class="q-header">
                    <span class="q-num">#<?= $qNum ?></span>
                    <span class="badge bg-secondary" style="font-size:0.72rem;"><?= htmlspecialchars($displayType) ?></span>
                    <span class="badge bg-<?= $diffColor ?> text-<?= $difficulty === 'medium' ? 'dark' : 'white' ?>" style="font-size:0.72rem;">
                        <?= ucfirst($difficulty) ?>
                    </span>
                    <span class="ms-auto">
                        <?php if ($correct): ?>
                            <span style="color:#28a745; font-weight:700; font-size:0.88rem;">
                                <i class="bi bi-check-circle-fill"></i> Correct
                            </span>
                        <?php else: ?>
                            <span style="color:#dc3545; font-weight:700; font-size:0.88rem;">
                                <i class="bi bi-x-circle-fill"></i> Wrong
                            </span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="q-text"><?= htmlspecialchars($ans['question_text']) ?></div>

                <!-- MCQ choices display -->
                <?php if ($type === 'multiple_choice' && !empty($ans['choices_decoded'])): ?>
                <div class="mt-2 mb-2" style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php foreach ($ans['choices_decoded'] as $letter => $choiceText): 
                        $isStudentChoice  = strtoupper(trim($ans['student_answer'])) === $letter;
                        $isCorrectChoice  = strtoupper(trim($ans['correct_answer'])) === $letter;
                        $choiceBg = '#f8f9fa';
                        $choiceBorder = '#dee2e6';
                        if ($isCorrectChoice) { $choiceBg = '#d4edda'; $choiceBorder = '#28a745'; }
                        if ($isStudentChoice && !$isCorrectChoice) { $choiceBg = '#f8d7da'; $choiceBorder = '#dc3545'; }
                    ?>
                    <div style="
                        padding: 6px 12px; border-radius: 6px; font-size: 0.85rem;
                        background: <?= $choiceBg ?>; border: 1px solid <?= $choiceBorder ?>;
                        font-weight: <?= ($isStudentChoice || $isCorrectChoice) ? '700' : '500' ?>;
                    ">
                        <strong><?= $letter ?>.</strong> <?= htmlspecialchars($choiceText) ?>
                        <?php if ($isStudentChoice && !$isCorrectChoice): ?> <i class="bi bi-x-circle-fill text-danger ms-1"></i><?php endif; ?>
                        <?php if ($isCorrectChoice): ?> <i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Student answer vs correct answer boxes -->
                <div class="answer-row">
                    <div class="answer-box student <?= $correct ? 'correct-ans' : 'wrong-ans' ?>">
                        <label>Student's Answer</label>
                        <div class="val"><?= htmlspecialchars($studentDisplay) ?></div>
                    </div>
                    <?php if (!$correct): ?>
                    <div class="answer-box correct-key">
                        <label>Correct Answer</label>
                        <div class="val"><?= htmlspecialchars($correctDisplay) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>
<script>
    $(document).ready(function () {
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation();
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        $('a.nav-link-custom[href="levels.php"]').addClass('active');
    });
</script>
<?php include("components/footer.php"); ?>