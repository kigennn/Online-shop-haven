<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Online Book Haven';
$sessionUser = null;
$activeNav = $activeNav ?? '';
$extraStyles = $extraStyles ?? [];
$bodyClass = $bodyClass ?? 'portal-shell bg-light';

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['site_user']) && is_array($_SESSION['site_user'])) {
    $sessionUser = $_SESSION['site_user'];
}

$sessionRole = $sessionUser['role'] ?? null;
$isPrivilegedUser = in_array($sessionRole, ['admin', 'staff'], true);
$panelLabel = $sessionRole === 'staff' ? 'Staff Panel' : 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Online Book Haven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/portal.css?v=20260427-2">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <?php foreach ($extraStyles as $stylePath): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylePath) ?>">
    <?php endforeach; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
    <nav class="navbar navbar-expand-lg navbar-light portal-nav shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.html">Online Book Haven</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link<?= $activeNav === 'home' ? ' active' : '' ?>" href="index.html">Home</a></li>
                    <?php if ($sessionUser !== null): ?>
                        <li class="nav-item"><a class="nav-link<?= $activeNav === 'shop' ? ' active' : '' ?>" href="shop.php">Shop</a></li>
                        <?php if ($isPrivilegedUser): ?>
                            <li class="nav-item"><a class="nav-link<?= $activeNav === 'admin' ? ' active' : '' ?>" href="admin.php"><?= htmlspecialchars($panelLabel) ?></a></li>
                            <li class="nav-item"><a class="nav-link<?= $activeNav === 'manage-books' ? ' active' : '' ?>" href="manage-books.php">Manage Books</a></li>
                            <li class="nav-item"><a class="nav-link<?= $activeNav === 'add-account' ? ' active' : '' ?>" href="adduser.php">Add Account</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link<?= $activeNav === 'profile' ? ' active' : '' ?>" href="user-profile.php">Profile</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="Lgin.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="Sign.php">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($sessionUser !== null): ?>
                        <span class="navbar-text">
                            Signed in as <?= htmlspecialchars($sessionUser['username']) ?><?= isset($sessionUser['role']) ? ' (' . htmlspecialchars(ucfirst((string) $sessionUser['role'])) . ')' : '' ?>
                        </span>
                        <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
                    <?php else: ?>
                        <a class="btn btn-outline-light btn-sm" href="Lgin.php">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
