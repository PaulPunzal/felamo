<?php
// backend/controller/StudentsController.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class StudentsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    // --- GET STUDENTS ---
    public function GetStudents($user_id, $isSuperAdmin, $sectionId)
    {
        $hasSectionFilter = !empty($sectionId);
        $sql = "";
        $types = "";
        $params = [];

        $sql = "SELECT u.*, sta.teacher_id, sta.student_lrn, s.section_name,
                       (u.password = '' OR u.password IS NULL) AS is_pending
                FROM student_teacher_assignments AS sta
                LEFT JOIN users AS u ON sta.student_lrn = u.lrn
                LEFT JOIN sections AS s ON sta.section_id = s.id";

        if ($hasSectionFilter) {
            $sql .= " WHERE s.id = ?";
            $types .= "i";
            $params[] = $sectionId;
        } else {
            if ($isSuperAdmin === "true") {
                // Super Admin sees ALL students (No WHERE clause needed)
            } else {
                $sql .= " WHERE s.teacher_id = ?";
                $types .= "i";
                $params[] = $user_id;
            }
        }

        $q = $this->conn->prepare($sql);

        if (!$q) {
            echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $this->conn->error]);
            return;
        }

        if (!empty($params)) {
            $q->bind_param($types, ...$params);
        }

        if ($q->execute()) {
            $result = $q->get_result();
            $students = [];
            while ($row = $result->fetch_assoc()) {
                // Never leak the password hash (or empty-string sentinel) to the frontend
                unset($row['password']);
                $students[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $students]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Execute Error: ' . $q->error]);
        }
        $q->close();
    }

    // --- INSERT STUDENT ---
    // FIX: Stores the teacher-provided profile as a PENDING placeholder row
    // in `users` (password = ''), plus reserves the LRN in
    // student_teacher_assignments. The student later "claims" this exact
    // row (via verify-email.php -> register.php), which UPDATEs the
    // placeholder's email/password instead of inserting a brand-new row.
    //
    // A row only counts as a REAL, already-registered account once its
    // password column is non-empty. Never insert with a non-empty password
    // here — that would immediately lock the student out of self-signup.
    public function InsertStudent($lrn, $fname, $mname, $lname, $bdate, $gender, $contact, $email, $sectionId)
    {
        // 1. Get Teacher ID
        $qSection = $this->conn->prepare("SELECT teacher_id FROM sections WHERE id = ?");
        $qSection->bind_param("i", $sectionId);
        $qSection->execute();
        $res = $qSection->get_result();

        if ($res->num_rows === 0) return ['status' => 'error', 'message' => 'Section not found'];
        $teacherId = $res->fetch_assoc()['teacher_id'];
        $qSection->close();

        // 2. Block only if this LRN already has a REAL (password-set) account
        $qCheckUser = $this->conn->prepare("SELECT id, password FROM users WHERE lrn = ?");
        $qCheckUser->bind_param("s", $lrn);
        $qCheckUser->execute();
        $existingUser = $qCheckUser->get_result()->fetch_assoc();
        $qCheckUser->close();

        if ($existingUser && !empty($existingUser['password'])) {
            return ['status' => 'error', 'message' => 'LRN already has an account.'];
        }

        // 3. Block if the email is already used by a DIFFERENT LRN
        $qCheckEmail = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND lrn != ?");
        $qCheckEmail->bind_param("ss", $email, $lrn);
        $qCheckEmail->execute();
        if ($qCheckEmail->get_result()->num_rows > 0) {
            $qCheckEmail->close();
            return ['status' => 'error', 'message' => 'Email is already used by another student.'];
        }
        $qCheckEmail->close();

        // 4. Block if the LRN is already assigned to a section
        $qCheckAssign = $this->conn->prepare("SELECT id FROM student_teacher_assignments WHERE student_lrn = ?");
        $qCheckAssign->bind_param("s", $lrn);
        $qCheckAssign->execute();
        if ($qCheckAssign->get_result()->num_rows > 0) {
            $qCheckAssign->close();
            return ['status' => 'error', 'message' => 'LRN is already assigned to a section.'];
        }
        $qCheckAssign->close();

        $this->conn->begin_transaction();

        try {
            if ($existingUser) {
                // A pending placeholder somehow already exists for this LRN
                // (no password set) — just refresh its profile fields.
                $stmtUser = $this->conn->prepare("
                    UPDATE users
                    SET first_name = ?, middle_name = ?, last_name = ?, birth_date = ?, gender = ?, contact_no = ?, email = ?
                    WHERE id = ?
                ");
                $stmtUser->bind_param("sssssssi", $fname, $mname, $lname, $bdate, $gender, $contact, $email, $existingUser['id']);
            } else {
                // Create the pending placeholder. password = '' marks it as
                // "not yet claimed" — this is intentional, not a bug.
                $stmtUser = $this->conn->prepare("
                    INSERT INTO users (lrn, first_name, middle_name, last_name, birth_date, gender, contact_no, email, password, points, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', 0, 1)
                ");
                $stmtUser->bind_param("ssssssss", $lrn, $fname, $mname, $lname, $bdate, $gender, $contact, $email);
            }

            if (!$stmtUser->execute()) {
                throw new Exception("Failed to save student profile: " . $stmtUser->error);
            }
            $stmtUser->close();

            $stmtAssign = $this->conn->prepare("INSERT INTO student_teacher_assignments (student_lrn, section_id, teacher_id) VALUES (?, ?, ?)");
            $stmtAssign->bind_param("sii", $lrn, $sectionId, $teacherId);

            if (!$stmtAssign->execute()) {
                throw new Exception("DB Error: " . $stmtAssign->error);
            }
            $stmtAssign->close();

            $this->conn->commit();

            return [
                'status' => 'success',
                'message' => 'Student added! They can sign up in the app anytime using this LRN and their own password.'
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
?>