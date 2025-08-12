<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/config.php';

$user_id = $_SESSION['user_id'];

// Get user's questions with course names
$stmt = $conn->prepare("
    SELECT q.id, q.course_id, q.topic, q.question_text, q.question_type, q.marks, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, c.course_name 
    FROM questions q 
    JOIN courses c ON q.course_id = c.id 
    WHERE q.user_id = ? 
    ORDER BY c.course_name, q.topic, q.question_type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group questions by course name
$courses = [];
foreach ($questions as $question) {
    $courses[$question['course_name']][] = $question;
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
    <title>UCSI University</title>
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
                        <!-- Exam Header Page -->
                        <div class="exam-header-page">
                            <div class="exam-header-content">
                                <div class="school-logo-section">
                                    <div class="logo-placeholder">
                                    <img src="/question-bank/assets/images/ucsi_logo.png" alt="UCSI University Logo" />
                                    </div>
                                </div>
                                
                                <div class="exam-title-section">
                                    <h2 class="exam-main-title"><?php echo htmlspecialchars($generated_exam['title']); ?></h2>
                                    <p class="exam-subtitle">Academic Examination Paper</p>
                                </div>
                                
                                <div class="student-info-section">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Student Name:</label>
                                            <div class="underline-field">_________________________________</div>
                                        </div>
                                        <div class="info-item">
                                            <label>Student ID:</label>
                                            <div class="underline-field">_________________________________</div>
                                        </div>
                                        <div class="info-item">
                                            <label>Course:</label>
                                            <div class="underline-field">_________________________________</div>
                                        </div>
                                        <div class="info-item">
                                            <label>Date:</label>
                                            <div class="underline-field"><?php echo date('d/m/Y'); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <label>Time:</label>
                                            <div class="underline-field">_________________________________</div>
                                        </div>
                                        <!-- <div class="info-item">
                                            <label>Duration:</label>
                                            <div class="underline-field">_________________________________</div>
                                        </div> -->
                                    </div>
                                </div>
                                
                                <div class="exam-summary">
                                    <div class="summary-item">
                                        <strong>Total Questions:</strong> <?php echo count($generated_exam['questions']); ?>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Total Marks:</strong> <?php echo $generated_exam['total_marks']; ?>
                                    </div>
                                </div>
                                
                                <div class="instructions-section">
                                    <h3>Instructions:</h3>
                                    <ul class="instruction-list">
                                        <li>Write your name and student ID clearly at the top of this page</li>
                                        <li>Answer all questions in the spaces provided</li>
                                        <li>Show all your working for calculation questions</li>
                                        <li>Write clearly and legibly</li>
                                        <li>Do not use pencil or erasable ink</li>
                                    </ul>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- Questions Section -->
                        <div class="questions-section">
                            <h3 class="questions-header">Questions</h3>
                            
                            <?php foreach ($generated_exam['questions'] as $index => $question): ?>
                                <div class="exam-question">
                                    <div class="question-header">
                                        <span class="question-number">Q<?php echo $index + 1; ?>.</span>
                                        <span class="question-marks">[<?php echo $question['marks']; ?> marks]</span>
                                    </div>
                                    
                                    <div class="question-text">
                                        <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                    </div>
                                    
                                    <?php if ($question['question_type'] === 'MCQ' && !empty($question['option_a'])): ?>
                                        <div class="mcq-options-print">
                                            <div class="option">
                                                <span class="option-label">A.</span>
                                                <?php echo htmlspecialchars($question['option_a']); ?>
                                            </div>
                                            <div class="option">
                                                <span class="option-label">B.</span>
                                                <?php echo htmlspecialchars($question['option_b']); ?>
                                            </div>
                                            <div class="option">
                                                <span class="option-label">C.</span>
                                                <?php echo htmlspecialchars($question['option_c']); ?>
                                            </div>
                                            <div class="option">
                                                <span class="option-label">D.</span>
                                                <?php echo htmlspecialchars($question['option_d']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- <div class="question-type">
                                        Type: <?php echo htmlspecialchars($question['question_type']); ?>
                                    </div> -->
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="print-section">
                      
                        <button onclick="window.print()" class="btn btn-inline">Print Exam</button>
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
