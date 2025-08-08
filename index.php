<?php
header("Location: pages/login.php");
exit();
?>

<!-- File: includes/config.php -->
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "question_bank";
$port = 3307;
$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>