<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
include '../includes/config.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Question Bank</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <!-- Header Navigation -->
    <header class="site-header">
        <div class="header-container">
            <a href="dashboard.php" class="site-logo">Question Bank</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="create_question.php">Create Question</a></li>
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-container">
            <div class="header">
                <h1>Welcome, <?php echo htmlspecialchars($user_name ?? 'Lecturer'); ?></h1>
                <p>Manage your question bank and create academic assessments</p>
            </div>
            
            <nav aria-label="Main navigation">
                <ul class="nav-menu">
                    <li>
                        <a href="create_question.php">
                            Create Question
                            <small style="display: block; color: var(--text-grey); font-size: 0.875rem; margin-top: 4px;">
                                Add new questions to your bank
                            </small>
                        </a>
                    </li>
                    <li>
                        <a href="manage_questions.php">
                            Manage Questions
                            <small style="display: block; color: var(--text-grey); font-size: 0.875rem; margin-top: 4px;">
                                View and edit existing questions
                            </small>
                        </a>
                    </li>
                    <li>
                        <a href="generate_exam.php">
                            Generate Exam Paper
                            <small style="display: block; color: var(--text-grey); font-size: 0.875rem; margin-top: 4px;">
                                Create exam papers from your questions
                            </small>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" style="color: var(--error-red);">
                            Sign Out
                            <small style="display: block; color: var(--text-grey); font-size: 0.875rem; margin-top: 4px;">
                                End your session
                            </small>
                        </a>
                    </li>
                </ul>
            </nav>
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