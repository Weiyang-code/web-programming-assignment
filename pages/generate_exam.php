<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/config.php';

$user_id = $_SESSION['user_id'];

/**
 * 1) Load all questions for this user (with course name) to drive the UI and
 *    also to validate ownership when saving.
 */
$stmt = $conn->prepare("
    SELECT q.id, q.course_id, q.topic, q.question_text, q.question_type, q.marks,
           q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
           c.course_name
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

/** Build a quick in-memory index by id for validation and rendering */
$questionIndex = [];
foreach ($questions as $q) {
    $questionIndex[(int)$q['id']] = $q;
}

/** Group questions by course for the selection UI */
$courses = [];
foreach ($questions as $q) {
    $courses[$q['course_name']][] = $q;
}

$generated_exam = null;
$error_message = '';
$new_exam_id = null;

/**
 * 2) If user is loading a previously saved exam (?exam_id=...), fetch it for preview/print
 */
if (isset($_GET['exam_id'])) {
    $exam_id = (int) $_GET['exam_id'];

    // Fetch exam metadata (must belong to this user)
    $meta = null;
    $m = $conn->prepare("SELECT id, title, created_at FROM exam_papers WHERE id = ? AND user_id = ?");
    $m->bind_param("ii", $exam_id, $user_id);
    $m->execute();
    $metaRes = $m->get_result();
    $meta = $metaRes->fetch_assoc();
    $m->close();

    if ($meta) {
        // Fetch its questions via the junction table
        $qstmt = $conn->prepare("
            SELECT q.id, q.course_id, q.topic, q.question_text, q.question_type, q.marks,
                   q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, c.course_name
            FROM exam_questions eq
            JOIN questions q ON q.id = eq.question_id
            JOIN courses c ON c.id = q.course_id
            WHERE eq.exam_id = ?
            ORDER BY c.course_name, q.topic, q.question_type
        ");
        $qstmt->bind_param("i", $exam_id);
        $qstmt->execute();
        $qres = $qstmt->get_result();
        $savedQs = $qres->fetch_all(MYSQLI_ASSOC);
        $qstmt->close();

        $total = 0;
        foreach ($savedQs as $q) {
            $total += (int)$q['marks'];
        }

        $generated_exam = [
            'id' => $meta['id'],
            'title' => $meta['title'],
            'questions' => $savedQs,
            'total_marks' => $total,
            'created_at' => $meta['created_at'],
        ];
    } else {
        $error_message = "Exam not found or you do not have access to it.";
    }
}

/**
 * 3) Handle POST to create & SAVE a new exam (exam_papers + exam_questions)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_exam'])) {
    $exam_title = trim($_POST['exam_title']);
    $selected_questions = $_POST['selected_questions'] ?? [];

    if ($exam_title === '' || empty($selected_questions)) {
        $error_message = "Please provide an exam title and select at least one question.";
    } else {
        // Normalize & validate the selected IDs actually belong to this user
        $selected_ids = [];
        foreach ($selected_questions as $id) {
            $iid = (int)$id;
            if ($iid > 0 && isset($questionIndex[$iid])) {
                $selected_ids[$iid] = true; // de-duplicate
            }
        }

        if (empty($selected_ids)) {
            $error_message = "No valid questions selected.";
        } else {
            // 3a) Save to DB within a transaction
            $conn->begin_transaction();

            try {
                // Insert exam_papers
                $insExam = $conn->prepare("INSERT INTO exam_papers (user_id, title) VALUES (?, ?)");
                $insExam->bind_param("is", $user_id, $exam_title);
                if (!$insExam->execute()) {
                    throw new Exception("Failed to create exam.");
                }
                $new_exam_id = (int)$conn->insert_id;
                $insExam->close();

                // Insert exam_questions mappings
                $insEQ = $conn->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)");
                foreach (array_keys($selected_ids) as $qid) {
                    $insEQ->bind_param("ii", $new_exam_id, $qid);
                    if (!$insEQ->execute()) {
                        throw new Exception("Failed to add a question to the exam.");
                    }
                }
                $insEQ->close();

                $conn->commit();

                // Build $generated_exam for immediate preview (from selected IDs we already validated)
                $preview_list = [];
                $total = 0;
                foreach (array_keys($selected_ids) as $qid) {
                    $preview_list[] = $questionIndex[$qid];
                    $total += (int)$questionIndex[$qid]['marks'];
                }
                $generated_exam = [
                    'id' => $new_exam_id,
                    'title' => $exam_title,
                    'questions' => $preview_list,
                    'total_marks' => $total
                ];
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to save the exam. Please try again.";
                $new_exam_id = null;
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

            <?php if (empty($questions) && !$generated_exam): ?>
                <div class="empty-state text-center">
                    <h3>No questions available</h3>
                    <p>You need to create questions before generating an exam paper.</p>
                    <a href="create_question.php" class="btn btn-inline">Create Questions</a>
                </div>
            <?php else: ?>

                <?php if (!$generated_exam): ?>
                    <!-- Exam Builder Form -->
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
                                                <?php echo (int)$question['marks']; ?> marks
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

                        <button type="submit" name="generate_exam">Save & Preview Exam</button>
                    </form>

                <?php else: ?>
                    <!-- Saved Exam Preview -->
                    <div class="generated-exam">
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
                                        <div class="info-item"><label>Student Name:</label><div class="underline-field"></div></div>
                                        <div class="info-item"><label>Student ID:</label><div class="underline-field"></div></div>
                                        <div class="info-item"><label>Course:</label><div class="underline-field"></div></div>
                                        <div class="info-item"><label>Date:</label><div class="underline-field"></div></div>
                                        <div class="info-item"><label>Time:</label><div class="underline-field"></div></div>
                                    </div>
                                </div>

                                <div class="exam-summary">
                                    <div class="summary-item"><strong>Total Questions:</strong> <?php echo count($generated_exam['questions']); ?></div>
                                    <div class="summary-item"><strong>Total Marks:</strong> <?php echo (int)$generated_exam['total_marks']; ?></div>
                                </div>

                                <?php if (!empty($generated_exam['id'])): ?>
                                    <div class="summary-item" style="margin-top:6px;">
                                        <strong>Saved Exam ID:</strong> <?php echo (int)$generated_exam['id']; ?> —
                                        <a href="generate_exam.php?exam_id=<?php echo (int)$generated_exam['id']; ?>">Open this exam</a>
                                    </div>
                                <?php endif; ?>

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

                        <div class="questions-section">
                            <h3 class="questions-header">Questions</h3>

                            <?php foreach ($generated_exam['questions'] as $index => $q): ?>
                                <div class="exam-question">
                                    <div class="question-header">
                                        <span class="question-number">Q<?php echo $index + 1; ?>.</span>
                                        <span class="question-marks">[<?php echo (int)$q['marks']; ?> marks]</span>
                                    </div>

                                    <div class="question-text">
                                        <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                                    </div>

                                    <?php if ($q['question_type'] === 'MCQ' && !empty($q['option_a'])): ?>
                                        <div class="mcq-options-print">
                                            <div class="option"><span class="option-label">A.</span> <?php echo htmlspecialchars($q['option_a']); ?></div>
                                            <div class="option"><span class="option-label">B.</span> <?php echo htmlspecialchars($q['option_b']); ?></div>
                                            <div class="option"><span class="option-label">C.</span> <?php echo htmlspecialchars($q['option_c']); ?></div>
                                            <div class="option"><span class="option-label">D.</span> <?php echo htmlspecialchars($q['option_d']); ?></div>
                                        </div>
                                    <?php endif; ?>
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

            <?php if (!empty($error_message)): ?>
                <p class="text-center" style="color: var(--error-red); margin-top: 1rem;">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>
            <?php endif; ?>

            <p class="text-center mt-lg">
                <a href="dashboard.php">← Back to Dashboard</a>
            </p>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <p>&copy; 2025 Question Bank System. All rights reserved.</p>
        </div>
    </footer>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>
