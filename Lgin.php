<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

if (IS_LOGGED_IN) {
    redirect_to_role_home();
}

$errorMessage = trim($_GET['error'] ?? '');
$successMessage = '';

if (isset($_GET['registered'])) {
    $successMessage = 'Your account was created successfully. Please log in.';
} elseif (isset($_GET['logged_out'])) {
    $successMessage = 'You have been logged out.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in to Online Book Haven and continue your reading journey.">
    <title>Login | Online Book Haven</title>
    <link rel="icon" href="img/fav.png" type="image/icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/guest-pages.css?v=20260427-1">
</head>
<body class="guest-page login-page">
    <header class="site-header">
        <a class="site-brand" href="index.html" aria-label="Online Book Haven home">
            <span class="site-brand__eyebrow">Online Book Haven</span>
            <span class="site-brand__title">Welcome back</span>
        </a>

        <button class="site-toggle" type="button" aria-expanded="false" aria-controls="guest-nav" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" id="guest-nav">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="Lgin.php" aria-current="page">Login</a></li>
                <li><a href="Sign.php">Sign Up</a></li>
                <li><a href="contact.html">Contact Us</a></li>
            </ul>
        </nav>

        <div class="site-social">
            <a href="https://www.facebook.com" target="_blank" rel="noreferrer" aria-label="Facebook">
                <i class="bx bxl-facebook-circle"></i>
            </a>
            <a href="https://www.instagram.com" target="_blank" rel="noreferrer" aria-label="Instagram">
                <i class="bx bxl-instagram-alt"></i>
            </a>
        </div>
    </header>

    <main class="guest-main">
        <section class="page-card">
            <aside class="page-aside">
                <p class="page-kicker">Account access</p>
                <h1>Pick up right where your reading left off.</h1>
                <p class="page-description">
                    Sign in to revisit saved favorites, manage your profile, and keep your reading journey moving
                    without starting over.
                </p>

                <div class="aside-grid">
                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-book-heart"></i></span>
                        <h2>Your shelves stay close</h2>
                        <p>Return to the books, categories, and profile details you care about most.</p>
                    </article>

                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-time-five"></i></span>
                        <h2>Fast and simple login</h2>
                        <p>Clear inputs and a focused form keep the next step easy for returning readers.</p>
                    </article>

                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-support"></i></span>
                        <h2>Need help?</h2>
                        <p>If anything feels off, the contact page is always one click away.</p>
                    </article>
                </div>
            </aside>

            <section class="page-panel">
                <p class="panel-kicker">Login</p>
                <h2>Welcome back</h2>
                <p class="panel-text">Use your email and password to continue.</p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="status-message status-message--error" role="alert"><?= htmlspecialchars($errorMessage) ?></p>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <p class="status-message status-message--success" role="status"><?= htmlspecialchars($successMessage) ?></p>
                <?php endif; ?>

                <form action="login.php" method="post" class="guest-form">
                    <label class="field">
                        <span class="field__label">Email address</span>
                        <span class="field__control">
                            <i class="bx bxs-envelope"></i>
                            <input class="input-control" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                        </span>
                    </label>

                    <label class="field">
                        <span class="field__label">Password</span>
                        <span class="field__control">
                            <i class="bx bxs-lock-alt"></i>
                            <input class="input-control" type="password" name="pwd" placeholder="Enter your password" autocomplete="current-password" required>
                        </span>
                    </label>

                    <button type="submit" class="primary-button">Log In</button>

                    <div class="panel-links">
                        <span>New to Online Book Haven?</span>
                        <a href="Sign.php">Create an account</a>
                    </div>
                </form>
            </section>
        </section>
    </main>

    <footer class="guest-footer">
        <p>Online Book Haven &copy; <?= date('Y') ?>. A calmer place to keep reading.</p>
    </footer>

    <script src="js/guest-pages.js?v=20260427-1"></script>
</body>
</html>
