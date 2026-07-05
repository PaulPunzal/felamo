<?php
require_once(__DIR__ . '/../../db/db.php');

header('Content-Type: application/json');

$database = new db_connect();
$conn = $database->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode(['status' => 400, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['section_id']) || !is_numeric($_POST['section_id'])) {
    echo json_encode(['status' => 400, 'message' => 'Missing or invalid Section ID.']);
    exit;
}
$section_id = (int) $_POST['section_id'];

$sectionStmt = $conn->prepare("SELECT teacher_id FROM `sections` WHERE id = ?");
$sectionStmt->bind_param("i", $section_id);
$sectionStmt->execute();
$sectionRow = $sectionStmt->get_result()->fetch_assoc();
$sectionStmt->close();

if (!$sectionRow) {
    echo json_encode(['status' => 400, 'message' => 'Section not found.']);
    exit;
}
$teacher_id = (int) $sectionRow['teacher_id'];

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

$isPreview = isset($_POST['preview']) && $_POST['preview'] === '1';
$ALLOWED_GENDERS = ['Lalaki', 'Babae'];

// Pre-fetch existing lrn/email for duplicate detection (global, across all sections)
$existingLrns = [];
$existingEmails = [];
$fetchStmt = $conn->prepare("SELECT lrn, email FROM `users`");
if ($fetchStmt) {
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['lrn'])) $existingLrns[$row['lrn']] = true;
        if (!empty($row['email'])) $existingEmails[strtolower(trim($row['email']))] = true;
    }
    $fetchStmt->close();
}

// LRNs already assigned to THIS section
$existingAssigned = [];
$assignStmt = $conn->prepare("SELECT student_lrn FROM `student_teacher_assignments` WHERE section_id = ?");
$assignStmt->bind_param("i", $section_id);
$assignStmt->execute();
$assignResult = $assignStmt->get_result();
while ($row = $assignResult->fetch_assoc()) {
    $existingAssigned[$row['student_lrn']] = true;
}
$assignStmt->close();

fgetcsv($handle); // skip header row

$parsedRows      = [];
$errors          = [];
$warnings        = [];
$rowIndex        = 2;
$seenInFile      = []; // lrn
$seenEmailInFile = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count(array_filter($row, 'trim')) === 0) { $rowIndex++; continue; }

    if (count($row) < 8) {
        $errors[] = "Row $rowIndex: Only " . count($row) . " column(s) found — expected 8 (lrn, first_name, middle_name, last_name, birth_date, gender, email, contact_no).";
        $rowIndex++;
        continue;
    }

    $lrn            = trim($row[0]);
    $first_name     = trim($row[1]);
    $middle_name    = trim($row[2]);
    $last_name      = trim($row[3]);
    $birth_date_raw = trim($row[4]);
    $gender         = trim($row[5]);
    $email          = trim($row[6]);
    $contact_no     = trim($row[7]);

    $rowErrors = [];

    if (empty($lrn) || !preg_match('/^\d{12}$/', $lrn)) $rowErrors[] = "LRN must be exactly 12 digits.";
    if (empty($first_name)) $rowErrors[] = "First name is required.";
    if (empty($last_name))  $rowErrors[] = "Last name is required.";

    $birth_date = null;
    if (empty($birth_date_raw)) {
        $rowErrors[] = "Birth date is required.";
    } else {
        $parsedDate = DateTime::createFromFormat('n/j/Y', $birth_date_raw) ?: DateTime::createFromFormat('Y-m-d', $birth_date_raw);
        if (!$parsedDate) {
            $rowErrors[] = "Birth date '$birth_date_raw' is not valid (expected M/D/YYYY).";
        } else {
            $birth_date = $parsedDate->format('Y-m-d');
        }
    }

    if (empty($gender) || !in_array($gender, $ALLOWED_GENDERS)) $rowErrors[] = "Gender must be 'Lalaki' or 'Babae'.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $rowErrors[] = "Valid email is required.";
    if (empty($contact_no) || !ctype_digit($contact_no) || strlen($contact_no) !== 10 ) $rowErrors[] = "Contact no must be exactly 10 digits.";

    $emailLower = strtolower($email);
    $isDuplicate = false;

    if (isset($existingAssigned[$lrn])) {
        $warnings[] = "Row $rowIndex: Skipped — LRN $lrn is already assigned to this section.";
        $isDuplicate = true;
    } elseif (isset($existingLrns[$lrn]) || isset($existingEmails[$emailLower])) {
        $warnings[] = "Row $rowIndex: Skipped — LRN or email already exists in another account ($lrn / $email).";
        $isDuplicate = true;
    } elseif (isset($seenInFile[$lrn]) || isset($seenEmailInFile[$emailLower])) {
        $warnings[] = "Row $rowIndex: Skipped — duplicate LRN or email within this CSV file ($lrn / $email).";
        $isDuplicate = true;
    }

    if (!empty($rowErrors)) {
        foreach ($rowErrors as $err) { $errors[] = "Row $rowIndex: $err"; }
    } elseif (!$isDuplicate) {
        $seenInFile[$lrn] = true;
        $seenEmailInFile[$emailLower] = true;
        $parsedRows[] = [
            'lrn'         => $lrn,
            'first_name'  => $first_name,
            'middle_name' => $middle_name,
            'last_name'   => $last_name,
            'birth_date'  => $birth_date,
            'gender'      => $gender,
            'email'       => $email,
            'contact_no'  => $contact_no,
        ];
    }

    $rowIndex++;
}

fclose($handle);

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
        'message'  => 'No valid rows to insert. ' . (!empty($warnings) ? implode(' | ', $warnings) : 'Check your CSV format.'),
        'warnings' => $warnings,
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $userStmt = $conn->prepare("
        INSERT INTO users (lrn, first_name, middle_name, last_name, birth_date, gender, email, contact_no, password, points, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
    ");
    if (!$userStmt) throw new Exception("Prepare failed: " . $conn->error);

    $assignStmt2 = $conn->prepare("
        INSERT INTO student_teacher_assignments (student_lrn, section_id, teacher_id)
        VALUES (?, ?, ?)
    ");
    if (!$assignStmt2) throw new Exception("Prepare failed: " . $conn->error);

    $insertedCount = 0;
    foreach ($parsedRows as $r) {
        $hashedPassword = password_hash($r['lrn'], PASSWORD_DEFAULT);

        $userStmt->bind_param(
            "sssssssss",
            $r['lrn'], $r['first_name'], $r['middle_name'], $r['last_name'],
            $r['birth_date'], $r['gender'], $r['email'], $r['contact_no'], $hashedPassword
        );
        if (!$userStmt->execute()) {
            throw new Exception("User insert failed for LRN {$r['lrn']}: " . $userStmt->error);
        }

        $assignStmt2->bind_param("sii", $r['lrn'], $section_id, $teacher_id);
        if (!$assignStmt2->execute()) {
            throw new Exception("Assignment insert failed for LRN {$r['lrn']}: " . $assignStmt2->error);
        }

        $insertedCount++;
    }

    $conn->commit();

    echo json_encode([
        'status'   => 200,
        'message'  => "Success! Imported $insertedCount student(s)."
                    . (!empty($warnings) ? ' ' . count($warnings) . ' row(s) skipped.' : ''),
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