<?php
include_once(__DIR__ . '/../../controller/AssessmentsController.php');

$requestType = $_POST['requestType'];

$controller = new AssesmentsController();

if ($requestType == "GetAssessment") {
    // THE FIX: Accept aralin_id instead of level_id
    $aralin_id = $_POST['aralin_id'];
    $controller->GetAssessment($aralin_id);
} elseif ($requestType == "CreateAssessment" || $requestType == "UpdateAssessment") {
    // THE FIX: Accept aralin_id instead of level_id
    $aralin_id = $_POST['aralin_id']; 
    $assessment_id = $_POST['assessment_id'] ?? null;
    $title = $_POST['title'];
    $description = $_POST['description'];

    $controller->CreateAssessment($aralin_id, $assessment_id, $title, $description);
} elseif ($requestType == "InsertMultipleChoice") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $choice_a = $_POST['choice_a'];
    $choice_b = $_POST['choice_b'];
    $choice_c = $_POST['choice_c'];
    $choice_d = $_POST['choice_d'];
    $correct_answer = $_POST['answer'];

    $controller->InsertMultipleChoice($assessment_id, $question, $choice_a, $choice_b, $choice_c, $choice_d, $correct_answer);
} elseif ($requestType == "InsertTrueOrFalse") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $answer = ($correct_answer == "true" ? 1 : 0);
    $controller->InsertTrueOrFalse($assessment_id, $question, $answer);
} elseif ($requestType == "InsertIdentification") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $controller->InsertIdentification($assessment_id, $question, $correct_answer);
} elseif ($requestType == "InsertJumbledWords") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $controller->InsertJumbledWords($assessment_id, $question, $correct_answer);
} elseif ($requestType == "GetMultiQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetMultipleChoiceQuestions($assessment_id);
} elseif ($requestType == "GetTrueOrFalseQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetTrueOrFalseQuestions($assessment_id);
} elseif ($requestType == "GetIdentificationQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetIdentificationQuestions($assessment_id);
} elseif ($requestType == "GetJumbledWordsQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetJumbledWordsQuestions($assessment_id);
} elseif ($requestType == "ImportMultipleChoices") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportMultipleChoices($assessment_id, $questions);
} elseif ($requestType == "ImportTrueOrFalse") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportTrueOrFalse($assessment_id, $questions);
} elseif ($requestType == "ImportIdentification") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportIdentification($assessment_id, $questions);
} elseif ($requestType == "ImportJumbledWords") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportJumbledWords($assessment_id, $questions);
} elseif ($requestType == "DeleteSingleQuestion") {
    require_once(__DIR__ . '/../../class.php');
    $db = new global_class();

    $question_id = $_POST['question_id'];

    $stmt = $db->conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Question deleted.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $db->conn->error]);
    }
} elseif ($requestType == "UpdateSingleQuestion") {
    require_once(__DIR__ . '/../../class.php');
    $db = new global_class();

    $question_id = $_POST['question_id'];
    $question_text = $_POST['question_text'];
    $correct_answer = $_POST['correct_answer'];
    $choices = $_POST['choices'] ?? null;

    // Accepts either wording ("easy/medium/hard" or "easy/avg/difficult"),
    // case-insensitive, and normalizes to the values actually stored in
    // the DB — same alias map used by upload-questions.php.
    $DIFFICULTY_ALIASES = [
        'easy'      => 'easy',
        'medium'    => 'medium',
        'avg'       => 'medium',
        'average'   => 'medium',
        'hard'      => 'hard',
        'difficult' => 'hard',
    ];
    $difficulty_raw = strtolower(trim($_POST['difficulty'] ?? 'easy'));
    $difficulty     = $DIFFICULTY_ALIASES[$difficulty_raw] ?? 'easy';

    if (!empty($choices)) {
        // Update MCQ with choices JSON and difficulty
        $stmt = $db->conn->prepare("UPDATE questions SET question_text = ?, correct_answer = ?, choices = ?, difficulty = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $question_text, $correct_answer, $choices, $difficulty, $question_id);
    } else {
        // Update regular questions and difficulty
        $stmt = $db->conn->prepare("UPDATE questions SET question_text = ?, correct_answer = ?, difficulty = ? WHERE id = ?");
        $stmt->bind_param("sssi", $question_text, $correct_answer, $difficulty, $question_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $db->conn->error]);
    }
} elseif ($requestType == "GetUnifiedQuestions") {
    $assessment_id = $_POST['assessment_id'];
    
    // THE FIX: We must import class.php before using global_class
    require_once(__DIR__ . '/../../class.php');
    $db = new global_class();
    
    $query = $db->conn->prepare("SELECT * FROM `questions` WHERE `assessment_id` = ?");
    $query->bind_param("i", $assessment_id);
    $query->execute();
    $result = $query->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(["status" => "success", "data" => $data]);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}