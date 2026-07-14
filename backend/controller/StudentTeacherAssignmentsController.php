<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class StudentTeacherAssignmentsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetAssignedStudents($section_id)
    {
        $q = $this->conn->prepare("
        SELECT sta.*, u.first_name, u.middle_name, u.last_name, u.birth_date, u.gender, u.email AS student_email, u.contact_no
        FROM `student_teacher_assignments` AS sta 
        LEFT JOIN `users` AS u ON sta.student_lrn = u.lrn
        WHERE sta.section_id = ?
    ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $section_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $students = [];

            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $students
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }


    // FIX: AssignStudent now ONLY reserves the LRN in
    // student_teacher_assignments. It no longer creates a row in `users`
    // (with a hashed password) up front. The student sets up their own
    // account — including their own password — via the mobile app's
    // Sign Up / OTP flow (verify-email.php -> register.php), which
    // requires the LRN to already exist in student_teacher_assignments.
    //
    // If this method also inserts into `users`, the student's self-signup
    // will always fail with "Email or LRN already exists" even though
    // they've never actually registered yet.
    public function AssignStudent($post)
    {
        $section_id = $post['section_id'] ?? null;
        $lrn = trim($post['lrn'] ?? '');
        $first_name = trim($post['first_name'] ?? '');
        $middle_name = trim($post['middle_name'] ?? '');
        $last_name = trim($post['last_name'] ?? '');
        $birth_date = trim($post['birth_date'] ?? '');
        $gender = trim($post['gender'] ?? '');
        $contact_no = trim($post['contact_no'] ?? '');
        $email = trim($post['email'] ?? '');
        // NOTE: $password is intentionally no longer used to create a
        // users row here. Kept accepted (but ignored) for backward
        // compatibility with any caller still sending it.
        $password = $post['password'] ?? '';

        $errors = [];

        // These fields are validated for data-entry sanity even though
        // most of them are no longer persisted here — the student will
        // provide their own values for name/email/etc. when they sign up.
        if (empty($first_name)) $errors[] = "First name is required.";
        if (empty($last_name)) $errors[] = "Last name is required.";

        if (empty($lrn) || !preg_match('/^\d{12}$/', $lrn)) {
            $errors[] = "LRN must be exactly 12 digits.";
        }

        if (empty($birth_date)) $errors[] = "Birth date is required.";
        if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $errors[] = "Valid gender is required.";

        if (empty($contact_no) || !ctype_digit($contact_no) || strlen($contact_no) !== 11) {
            $errors[] = "Contact no must be exactly 11 digits.";
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($section_id)) $errors[] = "Section ID is required.";

        if (!empty($errors)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors
            ]);
            return;
        }

        // Block if this LRN already belongs to a fully registered account
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
        $stmt->bind_param("ss", $email, $lrn);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email or LRN already has an account.'
            ]);
            return;
        }

        $stmt->close();

        $stmt = $this->conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE section_id = ? AND student_lrn = ?");
        $stmt->bind_param("is", $section_id, $lrn);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Student is already assigned to this section.'
            ]);
            return;
        }

        $stmt->close();

        $stmt = $this->conn->prepare("
        INSERT INTO `student_teacher_assignments` (`section_id`, `student_lrn`)
        VALUES (?, ?)
    ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare assignment statement.'
            ]);
            return;
        }

        $stmt->bind_param("is", $section_id, $lrn);

        if (!$stmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Insert failed: ' . $stmt->error
            ]);
            return;
        }

        $stmt->close();

        // NOTE: The `users` INSERT that used to happen here has been
        // removed on purpose. Do not re-add it — see comment above.

        echo json_encode([
            'status' => 'success',
            'message' => 'Student assigned successfully. They can now sign up in the app.',
        ]);
    }


    public function ImportLrns($lrnArray, $section_id)
    {
        $successCount = 0;
        $skippedCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($lrnArray as $lrn) {
            $checkStmt = $this->conn->prepare("
            SELECT 1 FROM `student_teacher_assignments`
            WHERE `student_lrn` = ?
        ");

            if (!$checkStmt) {
                $failCount++;
                $errors[] = "Failed to prepare SELECT for LRN $lrn";
                continue;
            }

            $checkStmt->bind_param("s", $lrn);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $skippedCount++;
                $checkStmt->close();
                continue;
            }

            $checkStmt->close();

            $insertStmt = $this->conn->prepare("
            INSERT INTO `student_teacher_assignments` (`section_id`, `student_lrn`)
            VALUES (?, ?)
        ");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for LRN $lrn";
                continue;
            }

            $insertStmt->bind_param("is", $section_id, $lrn);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for LRN $lrn: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount assigned, $skippedCount skipped (already exists), $failCount failed.",
            'errors' => $errors
        ]);
    }

    // FIX: ImportStudents now ONLY reserves each LRN in
    // student_teacher_assignments. It no longer creates rows in `users`
    // with a hashed password. Students set up their own accounts via the
    // app's Sign Up / OTP flow, which depends on the LRN already being
    // present here (and NOT already present in `users`).
    public function ImportStudents($students, $section_id)
    {
        $successCount = 0;
        $skippedCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($students as $index => $student) {
            $lrn = trim($student['lrn'] ?? '');
            $first_name = trim($student['first_name'] ?? '');
            $middle_name = trim($student['middle_name'] ?? '');
            $last_name = trim($student['last_name'] ?? '');
            $birth_date = trim($student['birth_date'] ?? '');
            $gender = trim($student['gender'] ?? '');
            $email = trim($student['email'] ?? '');
            // NOTE: $password is intentionally no longer used to create a
            // users row here. Kept accepted (but ignored) for backward
            // compatibility with any caller still sending it.
            $password = $student['password'] ?? '';

            $studentErrors = [];

            // Still validated for data-entry sanity even though most of
            // these values are no longer persisted — the student supplies
            // their own values when they sign up.
            if (empty($first_name)) $studentErrors[] = "First name is required.";
            if (empty($last_name)) $studentErrors[] = "Last name is required.";
            if (empty($lrn) || !preg_match('/^\d{12,}$/', $lrn)) $studentErrors[] = "LRN must be at least 12 digits.";
            if (empty($birth_date)) $studentErrors[] = "Birth date is required.";
            if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $studentErrors[] = "Valid gender is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $studentErrors[] = "Valid email is required.";

            if (!empty($studentErrors)) {
                $failCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => $studentErrors
                ];
                continue;
            }

            // Block if this LRN/email already belongs to a registered account
            $checkUserStmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
            $checkUserStmt->bind_param("ss", $email, $lrn);
            $checkUserStmt->execute();
            $checkUserStmt->store_result();

            if ($checkUserStmt->num_rows > 0) {
                $skippedCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Email or LRN already has an account."]
                ];
                $checkUserStmt->close();
                continue;
            }

            $checkUserStmt->close();

            $checkAssignStmt = $this->conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE section_id = ? AND student_lrn = ?");
            $checkAssignStmt->bind_param("is", $section_id, $lrn);
            $checkAssignStmt->execute();
            $checkAssignStmt->store_result();

            if ($checkAssignStmt->num_rows > 0) {
                $skippedCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Already assigned to section."]
                ];
                $checkAssignStmt->close();
                continue;
            }

            $checkAssignStmt->close();

            // Reserve the LRN only — no `users` row created here anymore.
            $insertAssignStmt = $this->conn->prepare("INSERT INTO student_teacher_assignments (section_id, student_lrn) VALUES (?, ?)");
            $insertAssignStmt->bind_param("is", $section_id, $lrn);

            if (!$insertAssignStmt->execute()) {
                $failCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Assignment insert failed: " . $insertAssignStmt->error]
                ];
                $insertAssignStmt->close();
                continue;
            }

            $insertAssignStmt->close();
            $successCount++;
        }

        echo json_encode([
            'status' => 'done',
            'message' => "$successCount reserved, $skippedCount skipped, $failCount failed. Students can now sign up in the app.",
            'errors' => $errors
        ]);
    }
}