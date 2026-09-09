<?php
session_start();
require_once('../../class.php'); 

header('Content-Type: application/json');

$db = new global_class();
$conn = $db->conn;

$response = ['status' => 500, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode(['status' => 400, 'message' => 'Invalid request method.']);
    exit;
}

// ── Validate assessment_id ────────────────────────────────────────────────────
if (!isset($_POST['assessment_id']) || !is_numeric($_POST['assessment_id'])) {
    echo json_encode(['status' => 400, 'message' => 'Missing or invalid Assessment ID.']);
    exit;
}
$assessment_id = (int)$_POST['assessment_id'];

// ── Validate uploaded file ────────────────────────────────────────────────────
$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 400, 'message' => 'File upload error code: ' . $file['error']]);
    exit;
}
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    echo json_encode(['status' => 400, 'message' => 'Invalid file type. Please upload a CSV file.']);
    exit;
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['status' => 500, 'message' => 'Failed to open the uploaded file.']);
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────
$ALLOWED_TYPES        = ['multiple_choice', 'true_false', 'identification', 'jumbled_word'];
$ALLOWED_DIFFICULTIES = ['easy', 'medium', 'hard'];
$DIFFICULTY_ALIASES = [
    'easy'      => 'easy',
    'medium'    => 'medium',
    'avg'       => 'medium',
    'average'   => 'medium',
    'hard'      => 'hard',
    'difficult' => 'hard',
];

$ALLOWED_TF_ANSWERS   = ['fact','bluff','true', 'false', '1', '0', 'tama', 'mali'];
$VALID_MCQ_KEYS       = ['A', 'B', 'C', 'D'];
$MIN_QUESTION_LENGTH  = 0;

$TYPE_ALIASES = [
    'fact_bluff'    => 'true_false',
    'fact_or_bluff' => 'true_false',
    'true_false'    => 'true_false',  // optional no-op, but explicit
    'tf'            => 'true_false',
];

// ── Preview mode? ─────────────────────────────────────────────────────────────
// If ?preview=1, parse and validate but do NOT insert. Returns parsed rows.
$isPreview = isset($_POST['preview']) && $_POST['preview'] === '1';

// ── Pre-fetch existing questions for duplicate detection ──────────────────────
$existingQuestions = [];
$fetchStmt = $conn->prepare("SELECT question_text FROM `questions` WHERE `assessment_id` = ?");
if ($fetchStmt) {
    $fetchStmt->bind_param("i", $assessment_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $text = strtolower(trim($row['question_text']));
        if ($text !== '') {
            $existingQuestions[$text] = true;
        }
    }
    $fetchStmt->close();
}

// ── Parse CSV ────────────────────────────────────────────────────────────────
fgetcsv($handle); // skip header row

$parsedRows    = [];
$errors        = [];
$warnings      = [];
$rowIndex      = 2;
$seenInFile    = []; // deduplicate within the file itself

while (($row = fgetcsv($handle)) !== false) {
    // Skip completely blank rows
    if (count(array_filter($row, 'trim')) === 0) {
        $rowIndex++;
        continue;
    }

    if (count($row) < 6) {
        $errors[] = "Row $rowIndex: Only " . count($row) . " column(s) found — expected 6.";
        $rowIndex++;
        continue;
    }

    $concept_group_id = trim($row[0]);
    $type             = strtolower(trim($row[1]));
    $difficulty_raw   = strtolower(trim($row[2]));
    $difficulty       = $DIFFICULTY_ALIASES[$difficulty_raw] ?? $difficulty_raw;
    $question_text    = trim($row[3]);
    $correct_answer   = trim($row[4]);
    $raw_choices      = isset($row[5]) ? trim($row[5]) : '';

    if (isset($TYPE_ALIASES[$type])) {
        $type = $TYPE_ALIASES[$type];
    }

    $rowErrors = [];

    // ── Type validation ───────────────────────────────────────────────────────
    if (!in_array($type, $ALLOWED_TYPES)) {
        $rowErrors[] = "Invalid type '$type'. Allowed: " . implode(', ', $ALLOWED_TYPES);
    }

    // ── Difficulty validation ─────────────────────────────────────────────────
    if (!in_array($difficulty, $ALLOWED_DIFFICULTIES)) {
        $rowErrors[] = "Invalid difficulty '$difficulty_raw'. Allowed: Easy, Avg (or Medium), Difficult (or Hard)";
    }

    // ── Question text length ──────────────────────────────────────────────────
    if (strlen($question_text) < $MIN_QUESTION_LENGTH) {
        $rowErrors[] = "Question text too short (min $MIN_QUESTION_LENGTH characters).";
    }

    // ── Correct answer present ────────────────────────────────────────────────
    if (empty($correct_answer)) {
        $rowErrors[] = "Correct answer cannot be empty.";
    }

    // ── MCQ-specific: choices + correct answer must match ─────────────────────
    $choicesJSON = null;
    if ($type === 'multiple_choice' && empty($rowErrors)) {
        if (empty($raw_choices)) {
            $rowErrors[] = "Multiple choice questions must have choices in column 6.";
        } else {
            $choicesArray = [];
            foreach (explode(',', $raw_choices) as $choice) {
                $parts = explode(':', $choice, 2);
                if (count($parts) === 2) {
                    $key = strtoupper(trim($parts[0]));
                    $val = trim($parts[1]);
                    if (!in_array($key, $VALID_MCQ_KEYS)) {
                        $rowErrors[] = "Choice key '$key' is invalid. Must be A, B, C, or D.";
                    } else {
                        $choicesArray[$key] = $val;
                    }
                } else {
                    $rowErrors[] = "Choices format invalid near: '$choice'. Expected 'A: text, B: text'.";
                }
            }

            if (empty($rowErrors)) {
                // Validate correct_answer is one of A/B/C/D
                $upperAnswer = strtoupper($correct_answer);
                if (!array_key_exists($upperAnswer, $choicesArray)) {
                    $rowErrors[] = "correct_answer '$correct_answer' must match a choice key "
                        . "(found: " . implode(', ', array_keys($choicesArray)) . ").";
                } else {
                    $correct_answer = $upperAnswer; // normalize
                    $choicesJSON = json_encode($choicesArray);
                }
            }
        }
    }

    // ── True/False-specific: answer must be recognizable ─────────────────────
    if ($type === 'true_false' && empty($rowErrors)) {
        if (!in_array(strtolower($correct_answer), $ALLOWED_TF_ANSWERS)) {
            $rowErrors[] = "true_false answer must be one of: "
                . implode(', ', $ALLOWED_TF_ANSWERS) . ". Got '$correct_answer'.";
        }
    }

    // ── Duplicate check (warn, don't hard-fail) ───────────────────────────────
    $checkText = strtolower($question_text);
    $isDuplicate = false;

    if ($checkText !== '') {
        if (isset($existingQuestions[$checkText])) {
            $warnings[] = "Row $rowIndex: Skipped — question already exists in database.";
            $isDuplicate = true;
        } elseif (isset($seenInFile[$checkText])) {
            $warnings[] = "Row $rowIndex: Skipped — duplicate question within this CSV file.";
            $isDuplicate = true;
        }
    }

    // ── Collect row result ────────────────────────────────────────────────────
    if (!empty($rowErrors)) {
        foreach ($rowErrors as $err) {
            $errors[] = "Row $rowIndex: $err";
        }
    } elseif (!$isDuplicate) {
        if ($checkText !== '') {
            $seenInFile[$checkText] = true;
        }
        $parsedRows[] = [
            'concept_group_id' => $concept_group_id,
            'type'             => $type,
            'difficulty'       => $difficulty,
            'question_text'    => $question_text,
            'correct_answer'   => $correct_answer,
            'choices_json'     => $choicesJSON,
        ];
    }

    $rowIndex++;
}

fclose($handle);

// ── If preview mode — return parsed data without inserting ────────────────────
if ($isPreview) {
    echo json_encode([
        'status'   => 200,
        'preview'  => true,
        'message'  => count($parsedRows) . ' valid row(s) ready to insert. '
                    . count($warnings) . ' skipped. '
                    . count($errors) . ' error(s).',
        'valid'    => $parsedRows,
        'warnings' => $warnings,
        'errors'   => $errors,
    ]);
    exit;
}

// ── Hard errors abort the insert ──────────────────────────────────────────────
if (!empty($errors)) {
    echo json_encode([
        'status'   => 400,
        'message'  => 'Upload cancelled — fix the errors below before re-uploading.',
        'errors'   => $errors,
        'warnings' => $warnings,
    ]);
    exit;
}

if (empty($parsedRows)) {
    echo json_encode([
        'status'   => 400,
        'message'  => 'No valid rows to insert. '
                    . (!empty($warnings) ? implode(' | ', $warnings) : 'Check your CSV format.'),
        'warnings' => $warnings,
    ]);
    exit;
}

// ── Insert with transaction ───────────────────────────────────────────────────
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        "INSERT INTO `questions`
            (`assessment_id`, `concept_group_id`, `type`, `difficulty`,
             `question_text`, `choices`, `correct_answer`)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }

    $insertedCount = 0;
    foreach ($parsedRows as $r) {
        $stmt->bind_param(
            "iisssss",
            $assessment_id,
            $r['concept_group_id'],
            $r['type'],
            $r['difficulty'],
            $r['question_text'],
            $r['choices_json'],
            $r['correct_answer']
        );
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $insertedCount++;
    }

    $conn->commit();

    // Activity log
    if (isset($_SESSION['USERNAME'])) {
        $log    = $_SESSION['USERNAME'];
        $date   = date('Y-m-d H:i:s');
        $desc   = "Bulk uploaded $insertedCount question(s) via CSV to assessment #$assessment_id.";
        $logStmt = $conn->prepare(
            "INSERT INTO `activitylog` (`log_username`, `activity_description`, `date_added`)
             VALUES (?, ?, ?)"
        );
        if ($logStmt) {
            $logStmt->bind_param('sss', $log, $desc, $date);
            $logStmt->execute();
        }
    }

    echo json_encode([
        'status'   => 200,
        'message'  => "Success! Inserted $insertedCount question(s)."
                    . (!empty($warnings) ? ' ' . count($warnings) . ' row(s) skipped (duplicates).' : ''),
        'inserted' => $insertedCount,
        'warnings' => $warnings,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status'  => 400,
        'message' => 'Upload cancelled (database error). ' . $e->getMessage(),
    ]);
}
?>