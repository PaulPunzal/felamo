<?php
require_once(__DIR__ . '/../../db/db.php');

header('Content-Type: application/json');

$database = new db_connect();
$conn = $database->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode(['status' => 400, 'message' => 'Invalid request method.']);
    exit;
}

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
$MIN_PASSWORD_LENGTH = 6;

// Pre-fetch existing emails for duplicate detection
$existingEmails = [];
$fetchStmt = $conn->prepare("SELECT email FROM `web_users`");
if ($fetchStmt) {
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existingEmails[strtolower(trim($row['email']))] = true;
    }
    $fetchStmt->close();
}

fgetcsv($handle); // skip header row

$parsedRows = [];
$errors     = [];
$warnings   = [];
$rowIndex   = 2;
$seenInFile = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count(array_filter($row, 'trim')) === 0) { $rowIndex++; continue; }

    if (count($row) < 5) {
        $errors[] = "Row $rowIndex: Only " . count($row) . " column(s) found — expected 5 (first_name, middle_name, last_name, email, password).";
        $rowIndex++;
        continue;
    }

    $first_name  = trim($row[0]);
    $middle_name = trim($row[1]);
    $last_name   = trim($row[2]);
    $email       = trim($row[3]);
    $password    = trim($row[4]);

    $rowErrors = [];

    if (empty($first_name)) $rowErrors[] = "First name is required.";
    if (empty($last_name))  $rowErrors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $rowErrors[] = "Valid email is required.";
    if (empty($password) || strlen($password) < $MIN_PASSWORD_LENGTH) $rowErrors[] = "Password must be at least $MIN_PASSWORD_LENGTH characters.";

    $emailLower = strtolower($email);
    $isDuplicate = false;

    if (isset($existingEmails[$emailLower])) {
        $warnings[] = "Row $rowIndex: Skipped — email already exists in database ($email).";
        $isDuplicate = true;
    } elseif (isset($seenInFile[$emailLower])) {
        $warnings[] = "Row $rowIndex: Skipped — duplicate email within this CSV file ($email).";
        $isDuplicate = true;
    }

    if (!empty($rowErrors)) {
        foreach ($rowErrors as $err) { $errors[] = "Row $rowIndex: $err"; }
    } elseif (!$isDuplicate) {
        $seenInFile[$emailLower] = true;
        $parsedRows[] = [
            'first_name'  => $first_name,
            'middle_name' => $middle_name,
            'last_name'   => $last_name,
            'email'       => $email,
            'password'    => $password,
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
    $stmt = $conn->prepare("
        INSERT INTO `web_users` (`first_name`, `middle_name`, `last_name`, `email`, `password`, `role`, `grade_level`, `is_active`)
        VALUES (?, ?, ?, ?, ?, 'teacher', 7, 1)
    ");
    if (!$stmt) throw new Exception("Database prepare error: " . $conn->error);

    $levelStmt = $conn->prepare("INSERT INTO `levels` (`teacher_id`, `level`) VALUES (?, ?)");
    if (!$levelStmt) throw new Exception("Database prepare error: " . $conn->error);

    $insertedCount = 0;
    foreach ($parsedRows as $r) {
        $hashed = password_hash($r['password'], PASSWORD_DEFAULT);
        $stmt->bind_param(
            "sssss",
            $r['first_name'], $r['middle_name'], $r['last_name'], $r['email'], $hashed
        );
        if (!$stmt->execute()) {
            throw new Exception("Insert failed for {$r['email']}: " . $stmt->error);
        }

        $teacher_id = $stmt->insert_id;
        for ($i = 1; $i <= 4; $i++) {
            $levelStmt->bind_param("ii", $teacher_id, $i);
            $levelStmt->execute();
        }

        $insertedCount++;
    }

    $conn->commit();

    echo json_encode([
        'status'   => 200,
        'message'  => "Success! Inserted $insertedCount teacher(s)."
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