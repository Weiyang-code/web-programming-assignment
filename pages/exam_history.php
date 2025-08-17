<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include '../includes/config.php';

$user_id = $_SESSION['user_id'];

/* ---------------------------
   Handle delete (optional)
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_exam'], $_POST['exam_id'])) {
    $exam_id = (int) $_POST['exam_id'];

    // Only allow deleting your own exam
    $chk = $conn->prepare("SELECT 1 FROM exam_papers WHERE id = ? AND user_id = ?");
    $chk->bind_param("ii", $exam_id, $user_id);
    $chk->execute();
    $has = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($has) {
        $conn->begin_transaction();
        try {
            // Remove mappings first, then the exam
            $d1 = $conn->prepare("DELETE FROM exam_questions WHERE exam_id = ?");
            $d1->bind_param("i", $exam_id);
            $d1->execute();
            $d1->close();

            $d2 = $conn->prepare("DELETE FROM exam_papers WHERE id = ? AND user_id = ?");
            $d2->bind_param("ii", $exam_id, $user_id);
            $d2->execute();
            $d2->close();

            $conn->commit();
            $msg = "Exam deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Could not delete exam. Please try again.";
        }
    } else {
        $err = "Exam not found or not yours.";
    }
}

/* ---------------------------
   Filters (optional)
---------------------------- */
$search = trim($_GET['q'] ?? '');

/* ---------------------------
   Fetch exams for this user
---------------------------- */
$sql = "
SELECT
  e.id,
  e.title,
  e.created_at,
  COUNT(eq.question_id) AS question_count,
  COALESCE(SUM(q.marks), 0) AS total_marks
FROM exam_papers e
LEFT JOIN exam_questions eq ON eq.exam_id = e.id
LEFT JOIN questions q ON q.id = eq.question_id
WHERE e.user_id = ?
";
$params = [$user_id];
$types  = "i";

if ($search !== '') {
    $sql .= " AND e.title LIKE ? ";
    $params[] = "%{$search}%";
    $types .= "s";
}

$sql .= " GROUP BY e.id ORDER BY e.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Exam History - Question Bank</title>
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <!-- Add this small page-scoped stylesheet AFTER your global CSS -->
    <link rel="stylesheet" href="../assets/css/exam_history.css" />
</head>

<body>
    <header class="site-header">
        <div class="header-container">
            <a href="dashboard.php" class="site-logo">Question Bank</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="create_question.php">Create Question</a></li>
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="generate_exam.php">Generate Exam</a></li>
                    <li><a href="exam_history.php" class="active">Exam History</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content exam-history">
        <div class="eh-container">
            <header class="eh-header">
                <h1>Exam History</h1>
                <p>Find and re-open previously saved exam papers.</p>
            </header>

            <?php if (!empty($msg)): ?>
                <div class="eh-alert eh-alert--success" role="alert"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="eh-alert eh-alert--error" role="alert"><?php echo htmlspecialchars($err); ?></div>
            <?php endif; ?>

            <form method="get" class="eh-search">
                <label for="q" class="eh-search__label">Search by title</label>
                <div class="eh-search__row">
                    <input type="text" id="q" name="q" class="eh-input" placeholder="e.g., Midterm, Final" value="<?php echo htmlspecialchars($search); ?>" />
                    <button type="submit" class="eh-btn eh-btn--primary">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="exam_history.php" class="eh-btn eh-btn--ghost">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (empty($exams)): ?>
                <section class="eh-empty">
                    <div class="eh-empty__icon">🗂️</div>
                    <h3>No saved exam papers</h3>
                    <p>When you save an exam, it will appear here.</p>
                    <a href="generate_exam.php" class="eh-btn eh-btn--primary">Create a new exam</a>
                </section>
            <?php else: ?>
                <div class="eh-tablewrap">
                    <table class="eh-table" aria-label="List of saved exam papers">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Questions</th>
                                <th scope="col">Total Marks</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="eh-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $e): ?>
                                <tr>
                                    <td class="eh-ellipsis" title="<?php echo htmlspecialchars($e['title'] ?? 'Untitled'); ?>">
                                        <?php echo htmlspecialchars($e['title'] ?? 'Untitled'); ?>
                                    </td>
                                    <td><?php echo (int)$e['question_count']; ?></td>
                                    <td><?php echo (int)$e['total_marks']; ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($e['created_at'])); ?></td>
                                    <td class="eh-actions">
                                        <a class="eh-btn eh-btn--small eh-btn--primary"
                                            href="generate_exam.php?exam_id=<?php echo (int)$e['id']; ?>">Open</a>

                                        <form method="post" class="eh-inlineform"
                                            onsubmit="return confirm('Delete this exam paper?');">
                                            <input type="hidden" name="exam_id" value="<?php echo (int)$e['id']; ?>" />
                                            <button type="submit" name="delete_exam"
                                                class="eh-btn eh-btn--small eh-btn--danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <footer class="eh-footerlinks">
                <a href="dashboard.php" class="eh-link">← Back to Dashboard</a>
                <a href="generate_exam.php" class="eh-link">Generate Exam</a>
            </footer>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <p>&copy; 2025 Question Bank System. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>