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

// Get question ID
$question_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

// Fetch existing question
$stmt = $conn->prepare("SELECT * FROM questions WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $question_id, $user_id);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$question) {
    die("Question not found or you do not have permission to edit it.");
}

// Update question
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = (int) $_POST['course_id'];
    $topic = trim($_POST['topic']);
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $marks = (int) $_POST['marks'];

    $option_a = $option_b = $option_c = $option_d = $correct_option = null;
    if ($question_type === 'MCQ') {
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = $_POST['correct_option'];
    }

    if ($course_id <= 0 || empty($topic) || empty($question_text) || $marks <= 0) {
        $error_message = "All fields are required and marks must be greater than 0.";
    } elseif ($question_type === 'MCQ' && (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option))) {
        $error_message = "Please provide all MCQ options and select the correct answer.";
    } else {
        $stmt = $conn->prepare("
            UPDATE questions
            SET course_id = ?, topic = ?, question_text = ?, question_type = ?, marks = ?, 
                option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param(
            "isssissssssi",
            $course_id,
            $topic,
            $question_text,
            $question_type,
            $marks,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_option,
            $question_id,
            $user_id
        );

        if ($stmt->execute()) {
            $success_message = "Question updated successfully!";
        } else {
            $error_message = "Failed to update question. Please try again.";
        }
        $stmt->close();
    }
}

// Delete question
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_question'])) {
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $question_id, $user_id);
    if ($stmt->execute()) {
        header("Location: dashboard.php?deleted=1");
        exit();
    } else {
        $error_message = "Failed to delete question.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - Question Bank</title>
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
            <h1>Edit Question</h1>
            <p>Update the details of your question</p>
        </div>

        <form method="post" role="form" aria-labelledby="edit-heading">
            <h2 id="edit-heading">Question Details</h2>

            <?php if (!empty($error_message)): ?>
                <div class="error-message" role="alert"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message" role="alert"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= ($course['id'] == ($question['course_id'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="topic">Topic</label>
                <input type="text" id="topic" name="topic"
                    value="<?= htmlspecialchars($_POST['topic'] ?? $question['topic']) ?>" required>
            </div>

            <div class="form-group">
                <label for="question_text">Question Text</label>
                <textarea id="question_text" name="question_text"
                    required><?= htmlspecialchars($_POST['question_text'] ?? $question['question_text']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="question_type">Question Type</label>
                <select id="question_type" name="question_type" onchange="toggleMCQFields()" required>
                    <option value="">Select question type</option>
                    <option value="MCQ" <?= (($question['question_type'] ?? '') == 'MCQ') ? 'selected' : '' ?>>Multiple
                        Choice Question</option>
                    <option value="Short Answer" <?= (($question['question_type'] ?? '') == 'Short Answer') ? 'selected' : '' ?>>Short Answer</option>
                    <option value="Essay" <?= (($question['question_type'] ?? '') == 'Essay') ? 'selected' : '' ?>>Essay
                    </option>
                    <option value="True/False" <?= (($question['question_type'] ?? '') == 'True/False') ? 'selected' : '' ?>>True/False</option>
                </select>
            </div>

            <!-- MCQ Fields -->
            <div id="mcq-fields" style="display:none;">
                <div class="form-group">
                    <label>Option A</label>
                    <input type="text" name="option_a"
                        value="<?= htmlspecialchars($_POST['option_a'] ?? $question['option_a']) ?>">
                </div>
                <div class="form-group">
                    <label>Option B</label>
                    <input type="text" name="option_b"
                        value="<?= htmlspecialchars($_POST['option_b'] ?? $question['option_b']) ?>">
                </div>
                <div class="form-group">
                    <label>Option C</label>
                    <input type="text" name="option_c"
                        value="<?= htmlspecialchars($_POST['option_c'] ?? $question['option_c']) ?>">
                </div>
                <div class="form-group">
                    <label>Option D</label>
                    <input type="text" name="option_d"
                        value="<?= htmlspecialchars($_POST['option_d'] ?? $question['option_d']) ?>">
                </div>
                <div class="form-group">
                    <label>Correct Option</label>
                    <select name="correct_option">
                        <option value="">-- Select --</option>
                        <option value="A" <?= (($question['correct_option'] ?? '') == 'A') ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= (($question['correct_option'] ?? '') == 'B') ? 'selected' : '' ?>>B</option>
                        <option value="C" <?= (($question['correct_option'] ?? '') == 'C') ? 'selected' : '' ?>>C</option>
                        <option value="D" <?= (($question['correct_option'] ?? '') == 'D') ? 'selected' : '' ?>>D</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="marks">Marks</label>
                <input type="number" id="marks" name="marks" min="1" max="100"
                    value="<?= htmlspecialchars($_POST['marks'] ?? $question['marks']) ?>" required>
            </div>

            <button type="submit" style="margin-bottom:0;">Update Question</button>
        </form>
        <form method="post" style="padding-top:0;"
            onsubmit="return confirm('Are you sure you want to delete this question? This action cannot be undone.');">
            <input type="hidden" name="delete_question" value="1">
            <button type="submit" class="delete-btn" style="margin-top:0;">Delete Question</button>
        </form>

        <p class="text-center mt-lg">
            <a href="dashboard.php">← Back to Dashboard</a>
        </p>


    </main>
</body>

</html>