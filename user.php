<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/admin-schema.php';
require_once __DIR__ . '/data-layer.php';

// Keep runtime schema upgrades automatic so older local databases still work with newer pages.
ensure_admin_schema($conn);

function normalize_session_user(array $user): array
{
    // Only persist the fields the UI and access-control layer need in session storage.
    return [
        'uid' => (int) $user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'user',
    ];
}

function current_user(): ?array
{
    $user = $_SESSION['site_user'] ?? null;

    if ($user !== null && !isset($user['role'])) {
        $user['role'] = 'user';
        $_SESSION['site_user'] = $user;
    }

    return $user;
}

function current_user_role(): string
{
    $user = current_user();

    return $user['role'] ?? 'user';
}

function has_any_role(array $roles): bool
{
    return in_array(current_user_role(), $roles, true);
}

function find_user_by_email(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare(
        'SELECT user_id, username, email, role, phone_number, profile_image, bio, pwd
         FROM users
         WHERE email = ? AND is_delete = 0
         LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function find_user_by_id(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT user_id, username, email, role, phone_number, profile_image, bio, pwd
         FROM users
         WHERE user_id = ? AND is_delete = 0
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function password_is_hashed(string $storedPassword): bool
{
    return password_get_info($storedPassword)['algo'] !== null;
}

function passwords_match(string $plainPassword, string $storedPassword): bool
{
    // Support both hashed passwords and older plain-text seed data while the project transitions.
    if (password_is_hashed($storedPassword)) {
        return password_verify($plainPassword, $storedPassword);
    }

    return hash_equals($storedPassword, $plainPassword);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['site_user'] = normalize_session_user($user);
}

function refresh_user_session(mysqli $conn, int $userId): ?array
{
    $user = find_user_by_id($conn, $userId);

    if ($user === null) {
        unset($_SESSION['site_user']);
        return null;
    }

    $_SESSION['site_user'] = normalize_session_user($user);
    return $_SESSION['site_user'];
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: Lgin.php');
        exit;
    }

    return $user;
}

function redirect_to_role_home(?array $user = null): void
{
    $user = $user ?? current_user();
    $role = $user['role'] ?? 'user';
    // Readers land in the storefront, while staff/admin users return to the operations workspace.
    $destination = in_array($role, ['admin', 'staff'], true) ? 'admin.php' : 'shop.php';

    header('Location: ' . $destination);
    exit;
}

function require_roles(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'] ?? 'user', $roles, true)) {
        redirect_to_role_home($user);
    }

    return $user;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    setcookie('site_user', '', time() - 3600, '/');
    session_destroy();
}

if (!defined('IS_LOGGED_IN')) {
    define('IS_LOGGED_IN', current_user() !== null);
}
