<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/config.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch courses for the logged-in user
$courses = [];
$course_stmt = $conn->prepare("SELECT id, course_name FROM courses WHERE user_id = ?");
$course_stmt->bind_param("i", $user_id);
$course_stmt->execute();
$result = $course_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$course_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = (int) $_POST['course_id'];
    $topic = trim($_POST['topic']);
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $marks = (int) $_POST['marks'];

    // MCQ-specific
    $option_a = $option_b = $option_c = $option_d = $correct_option = null;
    if ($question_type === 'MCQ') {
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = $_POST['correct_option'];
    }

    // Validation
    if ($course_id <= 0 || empty($topic) || empty($question_text) || $marks <= 0) {
        $error_message = "All fields are required and marks must be greater than 0.";
    } elseif ($question_type === 'MCQ' && (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option))) {
        $error_message = "Please provide all MCQ options and select the correct answer.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO questions 
            (user_id, course_id, topic, question_text, question_type, marks, option_a, option_b, option_c, option_d, correct_option) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisssisssss",
            $user_id,
            $course_id,
            $topic,
            $question_text,
            $question_type,
            $marks,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_option
        );

        if ($stmt->execute()) {
            $success_message = "Question added successfully!";
            $_POST = []; // clear form
        } else {
            $error_message = "Failed to add question. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Question - Question Bank</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script>
        function toggleMCQFields() {
            var type = document.getElementById('question_type').value;
            var mcqFields = document.getElementById('mcq-fields');
            mcqFields.style.display = (type === 'MCQ') ? 'block' : 'none';
        }
        window.onload = toggleMCQFields;
    </script>
</head>
<body>
    <main>
        <div class="header">
            <h1>Create New Question</h1>
            <p>Add a question to your academic question bank</p>
        </div>
        
        <form method="post" role="form" aria-labelledby="create-heading">
            <h2 id="create-heading">Question Details</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message" role="alert">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message" role="alert">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="topic">Topic</label>
                <input type="text" id="topic" name="topic" placeholder="e.g., Data Structures" 
                       value="<?= isset($_POST['topic']) ? htmlspecialchars($_POST['topic']) : '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="question_text">Question Text</label>
                <textarea id="question_text" name="question_text" placeholder="Enter the question content..." required><?= isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="question_type">Question Type</label>
                <select id="question_type" name="question_type" onchange="toggleMCQFields()" required>
                    <option value="">Select question type</option>
                    <option value="MCQ" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'MCQ') ? 'selected' : '' ?>>Multiple Choice Question</option>
                    <option value="Short Answer" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'Short Answer') ? 'selected' : '' ?>>Short Answer</option>
                    <option value="Essay" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'Essay') ? 'selected' : '' ?>>Essay</option>
                    <option value="True/False" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'True/False') ? 'selected' : '' ?>>True/False</option>
                </select>
            </div>

            <!-- MCQ Fields -->
            <div id="mcq-fields" style="display:none;">
                <div class="form-group">
                    <label>Option A</label>
                    <input type="text" name="option_a" value="<?= $_POST['option_a'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Option B</label>
                    <input type="text" name="option_b" value="<?= $_POST['option_b'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Option C</label>
                    <input type="text" name="option_c" value="<?= $_POST['option_c'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Option D</label>
                    <input type="text" name="option_d" value="<?= $_POST['option_d'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Correct Option</label>
                    <select name="correct_option">
                        <option value="">-- Select --</option>
                        <option value="A" <?= (($_POST['correct_option'] ?? '') == 'A') ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= (($_POST['correct_option'] ?? '') == 'B') ? 'selected' : '' ?>>B</option>
                        <option value="C" <?= (($_POST['correct_option'] ?? '') == 'C') ? 'selected' : '' ?>>C</option>
                        <option value="D" <?= (($_POST['correct_option'] ?? '') == 'D') ? 'selected' : '' ?>>D</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="marks">Marks</label>
                <input type="number" id="marks" name="marks" placeholder="Enter marks (e.g., 5)" min="1" max="100" 
                       value="<?= isset($_POST['marks']) ? htmlspecialchars($_POST['marks']) : '' ?>" required>
            </div>
            
            <button type="submit">Add Question</button>
            
            <p class="text-center mt-lg">
                <a href="dashboard.php">← Back to Dashboard</a>
            </p>
        </form>
    </main>
</body>
</html>