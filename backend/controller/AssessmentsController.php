<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class AssesmentsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function GetAssessment($aralin_id)
    {
        // THE FIX: Query the new aralin_id column
        $q = $this->conn->prepare("SELECT * FROM `assessments` WHERE aralin_id = ?");

        if (!$q) {
            echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed: ' . $this->conn->error]);
            return;
        }

        $q->bind_param("i", $aralin_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $levels = [];
            while ($row = $result->fetch_assoc()) {
                $levels[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $levels]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $q->error]);
        }
        $q->close();
    }

    public function CreateAssessment($aralin_id, $assessment_id, $title, $description)
    {
        if (empty($assessment_id)) {
            // THE FIX: Insert using aralin_id
            $stmt = $this->conn->prepare("INSERT INTO assessments (aralin_id, assessment_title, description) VALUES (?, ?, ?)");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
                return;
            }

            $stmt->bind_param("iss", $aralin_id, $title, $description);

            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Assessment created successfully.',
                    'assessment_id' => $stmt->insert_id
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // THE FIX: Update using aralin_id
            $stmt = $this->conn->prepare("UPDATE assessments SET assessment_title = ?, description = ?, aralin_id = ? WHERE id = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
                return;
            }

            $stmt->bind_param("ssii", $title, $description, $aralin_id, $assessment_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Assessment updated successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
            }
            $stmt->close();
        }
    }

    public function InsertMultipleChoice($assessment_id, $question, $choice_a, $choice_b, $choice_c, $choice_d, $answer)
    {
        if (empty($assessment_id) || empty($question) || empty($choice_a) || empty($choice_b) || empty($choice_c) || empty($choice_d) || empty($answer)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO multiple_choices (assessment_id, question, choice_a, choice_b, choice_c, choice_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("issssss", $assessment_id, $question, $choice_a, $choice_b, $choice_c, $choice_d, $answer);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Multiple choice question inserted successfully.',
                'question_id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }

        $stmt->close();
    }

    public function GetMultipleChoiceQuestions($assessment_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM multiple_choices WHERE assessment_id = ?");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $questions = [];

        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }

        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'data' => $questions
        ]);
    }

    public function InsertTrueOrFalse($assessment_id, $question, $answer)
    {
        $answer = ($answer == 1) ? 1 : 0;

        $stmt = $this->conn->prepare("INSERT INTO true_or_false (assessment_id, question, answer) VALUES (?, ?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("isi", $assessment_id, $question, $answer);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'True/False question inserted successfully.',
                'id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }

        $stmt->close();
    }

    public function InsertIdentification($assessment_id, $question, $answer)
    {
        // $answer = ($answer == 1) ? 1 : 0;

        $stmt = $this->conn->prepare("INSERT INTO identifications (assessment_id, question, answer) VALUES (?, ?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("iss", $assessment_id, $question, $answer);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Identification question inserted successfully.',
                'id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }

        $stmt->close();
    }

    public function InsertJumbledWords($assessment_id, $question, $answer)
    {

        $stmt = $this->conn->prepare("INSERT INTO jumbled_words (assessment_id, question, answer) VALUES (?, ?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("iss", $assessment_id, $question, $answer);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Identification question inserted successfully.',
                'id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }

        $stmt->close();
    }

    public function GetTrueOrFalseQuestions($assessment_id)
    {
        $stmt = $this->conn->prepare("SELECT id, question, answer FROM true_or_false WHERE assessment_id = ?");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $row['answer'] = (int)$row['answer']; // convert to int for frontend
            $questions[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $questions
        ]);

        $stmt->close();
    }

    public function GetIdentificationQuestions($assessment_id)
    {
        $stmt = $this->conn->prepare("SELECT id, question, answer FROM identifications WHERE assessment_id = ?");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $row['answer'] = $row['answer'];
            $questions[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $questions
        ]);

        $stmt->close();
    }

    public function GetJumbledWordsQuestions($assessment_id)
    {
        $stmt = $this->conn->prepare("SELECT id, question, answer FROM jumbled_words WHERE assessment_id = ?");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $row['answer'] = $row['answer'];
            $questions[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $questions
        ]);

        $stmt->close();
    }

    public function ImportMultipleChoices($assessment_id, $questions)
    {
        $deleteStmt = $this->conn->prepare("DELETE FROM `multiple_choices` WHERE `assessment_id` = ?");
        if (!$deleteStmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare DELETE statement.'
            ]);
            return;
        }

        $deleteStmt->bind_param("i", $assessment_id);
        if (!$deleteStmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete existing questions: ' . $deleteStmt->error
            ]);
            $deleteStmt->close();
            return;
        }
        $deleteStmt->close();

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($questions as $question) {
            $reqQuestion = $question['question'];
            $choice_a = $question['choice_a'];
            $choice_b = $question['choice_b'];
            $choice_c = $question['choice_c'];
            $choice_d = $question['choice_d'];
            $answer = $question['answer'];

            $insertStmt = $this->conn->prepare("INSERT INTO `multiple_choices` (`assessment_id`, `question`, `choice_a`, `choice_b`, `choice_c`, `choice_d`, `correct_answer`)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for question $reqQuestion";
                continue;
            }

            $insertStmt->bind_param("issssss", $assessment_id, $reqQuestion, $choice_a, $choice_b, $choice_c, $choice_d, $answer);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for question $reqQuestion: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount inserted, $failCount failed.",
            'errors' => $errors
        ]);
    }


    public function ImportTrueOrFalse($assessment_id, $questions)
    {
        $deleteStmt = $this->conn->prepare("DELETE FROM `true_or_false` WHERE `assessment_id` = ?");
        if (!$deleteStmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare DELETE statement.'
            ]);
            return;
        }

        $deleteStmt->bind_param("i", $assessment_id);
        if (!$deleteStmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete existing questions: ' . $deleteStmt->error
            ]);
            $deleteStmt->close();
            return;
        }
        $deleteStmt->close();

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($questions as $question) {
            $reqQuestion = $question['question'];
            $answer = $question['answer'];

            $insertStmt = $this->conn->prepare("INSERT INTO `true_or_false` (`assessment_id`, `question`, `answer`)
        VALUES (?, ?, ?)");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for question $reqQuestion";
                continue;
            }

            $insertStmt->bind_param("iss", $assessment_id, $reqQuestion, $answer);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for question $reqQuestion: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount inserted, $failCount failed.",
            'errors' => $errors
        ]);
    }


    public function ImportIdentification($assessment_id, $questions)
    {
        $deleteStmt = $this->conn->prepare("DELETE FROM `identifications` WHERE `assessment_id` = ?");
        if (!$deleteStmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare DELETE statement.'
            ]);
            return;
        }

        $deleteStmt->bind_param("i", $assessment_id);
        if (!$deleteStmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete existing questions: ' . $deleteStmt->error
            ]);
            $deleteStmt->close();
            return;
        }
        $deleteStmt->close();

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($questions as $question) {
            $reqQuestion = $question['question'];
            $answer = $question['answer'];

            $insertStmt = $this->conn->prepare("INSERT INTO `identifications` (`assessment_id`, `question`, `answer`)
        VALUES (?, ?, ?)");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for question $reqQuestion";
                continue;
            }

            $insertStmt->bind_param("iss", $assessment_id, $reqQuestion, $answer);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for question $reqQuestion: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount inserted, $failCount failed.",
            'errors' => $errors
        ]);
    }

    public function ImportJumbledWords($assessment_id, $questions)
    {
        $deleteStmt = $this->conn->prepare("DELETE FROM `jumbled_words` WHERE `assessment_id` = ?");
        if (!$deleteStmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare DELETE statement.'
            ]);
            return;
        }

        $deleteStmt->bind_param("i", $assessment_id);
        if (!$deleteStmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete existing questions: ' . $deleteStmt->error
            ]);
            $deleteStmt->close();
            return;
        }
        $deleteStmt->close();

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($questions as $question) {
            $reqQuestion = $question['question'];
            $answer = $question['answer'];

            $insertStmt = $this->conn->prepare("INSERT INTO `jumbled_words` (`assessment_id`, `question`, `answer`)
        VALUES (?, ?, ?)");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for question $reqQuestion";
                continue;
            }

            $insertStmt->bind_param("iss", $assessment_id, $reqQuestion, $answer);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for question $reqQuestion: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount inserted, $failCount failed.",
            'errors' => $errors
        ]);
    }
}
