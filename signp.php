<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Sign.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$pwd = $_POST['pwd'] ?? '';

if ($username === '' || $email === '' || $pwd === '') {
    header('Location: Sign.php?error=' . urlencode('Please fill in all fields.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: Sign.php?error=' . urlencode('Please enter a valid email address.'));
    exit;
}

if (strlen($pwd) < 6) {
    header('Location: Sign.php?error=' . urlencode('Password must be at least 6 characters long.'));
    exit;
}

$duplicateStmt = $conn->prepare(
    'SELECT user_id
     FROM users
     WHERE username = ? OR email = ?
     LIMIT 1'
);
$duplicateStmt->bind_param('ss', $username, $email);
$duplicateStmt->execute();
$existingUser = $duplicateStmt->get_result()->fetch_assoc();
$duplicateStmt->close();

if ($existingUser !== null) {
    header('Location: Sign.php?error=' . urlencode('That username or email is already registered.'));
    exit;
}

$hashedPassword = password_hash($pwd, PASSWORD_DEFAULT);
$insertStmt = $conn->prepare('INSERT INTO users (username, email, pwd) VALUES (?, ?, ?)');
$insertStmt->bind_param('sss', $username, $email, $hashedPassword);
$wasSaved = $insertStmt->execute();
$insertStmt->close();

if (!$wasSaved) {
    header('Location: Sign.php?error=' . urlencode('We could not create your account right now.'));
    exit;
}

header('Location: Lgin.php?registered=1');
exit;
