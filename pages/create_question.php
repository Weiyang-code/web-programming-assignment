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

// Fetch the logged-in user's courses
$courses = [];
$course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE user_id = ?");
$course_stmt->bind_param("i", $user_id);
$course_stmt->execute();
$result = $course_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row['course_name'];
}
$course_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course = trim($_POST['course']);
    $topic = trim($_POST['topic']);
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $marks = (int) $_POST['marks'];

    $option_a = isset($_POST['option_a']) ? trim($_POST['option_a']) : null;
    $option_b = isset($_POST['option_b']) ? trim($_POST['option_b']) : null;
    $option_c = isset($_POST['option_c']) ? trim($_POST['option_c']) : null;
    $option_d = isset($_POST['option_d']) ? trim($_POST['option_d']) : null;
    $correct_option = isset($_POST['correct_option']) ? $_POST['correct_option'] : null;

    if (empty($course) || empty($topic) || empty($question_text) || empty($question_type) || $marks <= 0) {
        $error_message = "All fields are required and marks must be greater than 0.";
    } elseif ($question_type === "MCQ" && (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option))) {
        $error_message = "For MCQ, all options and the correct answer must be provided.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO questions 
            (user_id, course, topic, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isssssssssi",
            $user_id,
            $course,
            $topic,
            $question_text,
            $question_type,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_option,
            $marks
        );

        if ($stmt->execute()) {
            $success_message = "Question added successfully!";
            $_POST = [];
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
</head>

<body>
    <!-- Header Navigation -->
    <header class="site-header">
        <div class="header-container">
            <a href="dashboard.php" class="site-logo">Question Bank</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="create_question.php" class="active">Create Question</a></li>
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-container">
            <div class="header">
                <h1>Create New Question</h1>
                <p>Add a question to your academic question bank</p>
            </div>

            <?php if ($error_message): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <form method="post" role="form" aria-labelledby="create-heading">
                <h2 id="create-heading">Question Details</h2>
                
                <div class="form-group">
                    <label for="course">Course</label>
                    <select id="course" name="course" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= (isset($_POST['course']) && $_POST['course'] === $c) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
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
                    <label for="question_type">Question Type</label>
                    <select id="question_type" name="question_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="MCQ" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'MCQ') ? 'selected' : '' ?>>Multiple Choice Question</option>
                        <option value="Short Answer" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'Short Answer') ? 'selected' : '' ?>>Short Answer</option>
                        <option value="Essay" <?= (isset($_POST['question_type']) && $_POST['question_type'] == 'Essay') ? 'selected' : '' ?>>Essay</option>
                    </select>
                </div>

                <div class="mcq-options hidden" id="mcq-options">
                    <h3>Multiple Choice Options</h3>
                    <div class="form-group">
                        <label for="option_a">Option A</label>
                        <input type="text" id="option_a" name="option_a" placeholder="Enter option A"
                               value="<?= $_POST['option_a'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="option_b">Option B</label>
                        <input type="text" id="option_b" name="option_b" placeholder="Enter option B"
                               value="<?= $_POST['option_b'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="option_c">Option C</label>
                        <input type="text" id="option_c" name="option_c" placeholder="Enter option C"
                               value="<?= $_POST['option_c'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="option_d">Option D</label>
                        <input type="text" id="option_d" name="option_d" placeholder="Enter option D"
                               value="<?= $_POST['option_d'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="correct_option">Correct Option</label>
                        <select id="correct_option" name="correct_option">
                            <option value="">-- Select Correct Option --</option>
                            <option value="A" <?= (isset($_POST['correct_option']) && $_POST['correct_option'] == 'A') ? 'selected' : '' ?>>Option A</option>
                            <option value="B" <?= (isset($_POST['correct_option']) && $_POST['correct_option'] == 'B') ? 'selected' : '' ?>>Option B</option>
                            <option value="C" <?= (isset($_POST['correct_option']) && $_POST['correct_option'] == 'C') ? 'selected' : '' ?>>Option C</option>
                            <option value="D" <?= (isset($_POST['correct_option']) && $_POST['correct_option'] == 'D') ? 'selected' : '' ?>>Option D</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="question_text">Question Text</label>
                    <textarea id="question_text" name="question_text" placeholder="Enter the question content..." required><?= isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : '' ?></textarea>
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
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <p>&copy; 2024 Question Bank System. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/scripts.js"></script>
    <script>
        const questionType = document.getElementById('question_type');
        const mcqOptions = document.getElementById('mcq-options');

        function toggleMCQFields() {
            if (questionType.value === 'MCQ') {
                mcqOptions.classList.remove('hidden');
            } else {
                mcqOptions.classList.add('hidden');
            }
        }

        questionType.addEventListener('change', toggleMCQFields);
        window.addEventListener('load', toggleMCQFields);
    </script>
</body>
</html>