<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/config.php';

$user_id = $_SESSION['user_id'];

// Get user's questions grouped by course and topic
$stmt = $conn->prepare("SELECT id, course, topic, question_text, question_type, marks FROM questions WHERE user_id = ? ORDER BY course, topic, question_type");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group questions by course
$courses = [];
foreach ($questions as $question) {
    $courses[$question['course']][] = $question;
}

$generated_exam = null;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_exam'])) {
    $exam_title = trim($_POST['exam_title']);
    $selected_questions = $_POST['selected_questions'] ?? [];
    
    if (empty($exam_title) || empty($selected_questions)) {
        $error_message = "Please provide an exam title and select at least one question.";
    } else {
        // Generate exam paper
        $generated_exam = [
            'title' => $exam_title,
            'questions' => [],
            'total_marks' => 0
        ];
        
        foreach ($selected_questions as $question_id) {
            foreach ($questions as $question) {
                if ($question['id'] == $question_id) {
                    $generated_exam['questions'][] = $question;
                    $generated_exam['total_marks'] += $question['marks'];
                    break;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Exam Paper - Question Bank</title>
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
                    <li><a href="create_question.php">Create Question</a></li>
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="exam-container">
            <div class="header">
                <h1>Generate Exam Paper</h1>
                <p>Create an exam by selecting questions from your bank</p>
            </div>
            
            <?php if (empty($questions)): ?>
                <div class="empty-state text-center">
                    <h3>No questions available</h3>
                    <p>You need to create questions before generating an exam paper.</p>
                    <a href="create_question.php" class="btn btn-inline">Create Questions</a>
                </div>
            <?php else: ?>
                <?php if (!$generated_exam): ?>
                    <form method="post" role="form">
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="exam_title">Exam Title</label>
                            <input type="text" id="exam_title" name="exam_title" placeholder="e.g., Computer Science Midterm Exam" 
                                   value="<?php echo isset($_POST['exam_title']) ? htmlspecialchars($_POST['exam_title']) : ''; ?>" required>
                        </div>
                        
                        <h3>Select Questions</h3>
                        <p class="text-grey mb-lg">Choose the questions you want to include in your exam paper.</p>
                        
                        <?php foreach ($courses as $course_name => $course_questions): ?>
                            <div class="course-section">
                                <div class="course-header">
                                    <?php echo htmlspecialchars($course_name); ?> (<?php echo count($course_questions); ?> questions)
                                </div>
                                
                                <?php foreach ($course_questions as $question): ?>
                                    <div class="question-item">
                                        <input type="checkbox" id="q<?php echo $question['id']; ?>" name="selected_questions[]" 
                                               value="<?php echo $question['id']; ?>" class="question-checkbox">
                                        <div class="question-preview">
                                            <div class="question-meta">
                                                <strong><?php echo htmlspecialchars($question['topic']); ?></strong> • 
                                                <?php echo htmlspecialchars($question['question_type']); ?> • 
                                                <?php echo $question['marks']; ?> marks
                                            </div>
                                            <label for="q<?php echo $question['id']; ?>" style="cursor: pointer; font-weight: var(--font-weight-light);">
                                                <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); ?>
                                                <?php if (strlen($question['question_text']) > 100) echo '...'; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="generate_exam">Generate Exam Paper</button>
                    </form>
                <?php else: ?>
                    <!-- Generated Exam Display -->
                    <div class="generated-exam">
                        <div class="text-center mb-lg">
                            <h2><?php echo htmlspecialchars($generated_exam['title']); ?></h2>
                            <p>Total Marks: <?php echo $generated_exam['total_marks']; ?></p>
                            <hr style="border: none; border-top: 1px solid var(--divider-grey); margin: var(--spacing-md) 0;">
                        </div>
                        
                        <?php foreach ($generated_exam['questions'] as $index => $question): ?>
                            <div class="exam-question">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-sm);">
                                    <strong>Q<?php echo $index + 1; ?>.</strong>
                                    <span>[<?php echo $question['marks']; ?> marks]</span>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                <small style="color: var(--text-grey);">
                                    Type: <?php echo htmlspecialchars($question['question_type']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-lg">
                        <button onclick="window.print()" class="btn btn-inline" style="margin-right: var(--spacing-sm);">Print Exam</button>
                        <a href="generate_exam.php" class="btn btn-inline">Generate Another</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <p class="text-center mt-lg">
                <a href="dashboard.php">← Back to Dashboard</a>
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <p>&copy; 2024 Question Bank System. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/scripts.js"></script>
</body>
</html>
