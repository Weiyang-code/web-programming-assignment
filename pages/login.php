<?php
session_start();
include '../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim to remove spaces
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Prepared statement to avoid SQL injection
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            // Login success
            $_SESSION['user_id'] = $user_id;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password. Please try again.";
        }
    } else {
        $error = "No account found with that email.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Question Bank</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <div class="header-container">
            <div class="site-logo">Question Bank</div>
            <nav class="main-nav">
                <ul>
                    <li><a href="login.php" class="active">Sign In</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-container">
            <div class="header">
                <h1>Welcome Back</h1>
                <p>Sign in to your academic workspace</p>
            </div>
            
            <form method="post" role="form" aria-labelledby="login-heading">
                <h2 id="login-heading">Sign In</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit">Sign In</button>
                
                <p class="text-center mt-lg">
                    Don't have an account? <a href="register.php">Create one here</a>
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
</body>
</html>