<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Lgin.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$pwd = $_POST['pwd'] ?? '';

if ($email === '' || $pwd === '') {
    header('Location: Lgin.php?error=' . urlencode('Please enter both email and password.'));
    exit;
}

$user = find_user_by_email($conn, $email);

if ($user === null || !passwords_match($pwd, $user['pwd'])) {
    header('Location: Lgin.php?error=' . urlencode('Invalid email or password.'));
    exit;
}

if (!password_is_hashed($user['pwd'])) {
    $hashedPassword = password_hash($pwd, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET pwd = ? WHERE user_id = ?');
    $stmt->bind_param('si', $hashedPassword, $user['user_id']);
    $stmt->execute();
    $stmt->close();
    $user['pwd'] = $hashedPassword;
}

login_user($user);

redirect_to_role_home(current_user());
