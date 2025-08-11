<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/config.php';

$user_id = $_SESSION['user_id'];

// Get user's questions
$stmt = $conn->prepare("SELECT id, course_id, topic, question_text, question_type, marks, created_at FROM questions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - Question Bank</title>
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
                    <li><a href="manage_questions.php" class="active">Manage Questions</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="questions-container">
            <div class="header">
                <h1>Manage Questions</h1>
                <p>View and organize your question collection</p>
            </div>

            <?php if (empty($questions)): ?>
                <div class="empty-state">
                    <h3>No questions yet</h3>
                    <p>You haven't created any questions. Start building your question bank!</p>
                    <a href="create_question.php" class="btn btn-inline">Create Your First Question</a>
                </div>
            <?php else: ?>
                <div class="mb-lg">
                    <p><?php echo count($questions); ?> question<?php echo count($questions) != 1 ? 's' : ''; ?> in your
                        bank</p>
                </div>

                <?php foreach ($questions as $question): ?>
                    <a href="edit_question.php?id=<?php echo $question['id']; ?>" class="question-card-link">
                        <div class="question-card">
                            <div class="question-meta">
                                <span><strong><?php echo htmlspecialchars($question['course_id']); ?></strong> •
                                    <?php echo htmlspecialchars($question['topic']); ?></span>
                                <span><?php echo date('M j, Y', strtotime($question['created_at'])); ?></span>
                            </div>

                            <div class="question-content">
                                <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                            </div>

                            <div class="question-details">
                                <span>Type: <?php echo htmlspecialchars($question['question_type']); ?></span>
                                <span>Marks: <?php echo htmlspecialchars($question['marks']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>

            <?php endif; ?>

            <p class="text-center mt-lg">
                <a href="dashboard.php">← Back to Dashboard</a> |
                <a href="create_question.php">Create New Question</a>
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <p>&copy; 2024 Question Bank System. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>