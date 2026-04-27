<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

if (IS_LOGGED_IN) {
    redirect_to_role_home();
}

$errorMessage = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign Up | Online Book Haven</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your Online Book Haven account and start exploring your next read.">
    <link rel="icon" href="img/fav.png" type="image/icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/guest-pages.css?v=20260427-1">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body class="guest-page signup-page">
    <header class="site-header">
        <a class="site-brand" href="index.html" aria-label="Online Book Haven home">
            <span class="site-brand__eyebrow">Online Book Haven</span>
            <span class="site-brand__title">Create your shelf</span>
        </a>

        <button class="site-toggle" type="button" aria-expanded="false" aria-controls="guest-nav" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" id="guest-nav">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="Lgin.php">Login</a></li>
                <li><a href="Sign.php" aria-current="page">Sign Up</a></li>
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
                <p class="page-kicker">New account</p>
                <h1>Start building a reading space that feels like yours.</h1>
                <p class="page-description">
                    Create an account to keep your profile in one place, move through the site more smoothly,
                    and stay connected to the shelves that match your interests.
                </p>

                <div class="aside-grid">
                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-library"></i></span>
                        <h2>Organized from the start</h2>
                        <p>Join with a simple form and begin exploring categories with less friction.</p>
                    </article>

                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-user-check"></i></span>
                        <h2>Easy to come back to</h2>
                        <p>Your account helps turn a one-time visit into a smoother long-term experience.</p>
                    </article>

                    <article class="aside-tile">
                        <span class="aside-tile__icon"><i class="bx bx-lock-alt"></i></span>
                        <h2>Basic password guidance</h2>
                        <p>Use at least 6 characters so your account can be created without delays.</p>
                    </article>
                </div>
            </aside>

            <section class="page-panel">
                <p class="panel-kicker">Sign up</p>
                <h2>Create your account</h2>
                <p class="panel-text">A few details are all you need to get started.</p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="status-message status-message--error" role="alert"><?= htmlspecialchars($errorMessage) ?></p>
                <?php endif; ?>

                <form action="signp.php" method="post" id="signup-form" class="guest-form">
                    <label class="field">
                        <span class="field__label">Username</span>
                        <span class="field__control">
                            <i class="bx bxs-user"></i>
                            <input class="input-control" type="text" name="username" placeholder="Choose a username" autocomplete="username" required>
                        </span>
                    </label>

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
                            <input class="input-control" type="password" name="pwd" placeholder="Create a password" autocomplete="new-password" required aria-describedby="password-hint" data-password-input>
                        </span>
                    </label>

                    <p class="field-note" id="password-hint" data-password-feedback>Password must be at least 6 characters long.</p>

                    <button type="submit" class="primary-button">Create Account</button>

                    <div class="panel-links">
                        <span>Already have an account?</span>
                        <a href="Lgin.php">Log in instead</a>
                    </div>
                </form>
            </section>
        </section>
    </main>

    <footer class="guest-footer">
        <p>Online Book Haven &copy; <?= date('Y') ?>. A friendlier way to begin browsing.</p>
    </footer>

    <script src="js/guest-pages.js?v=20260427-1"></script>
</body>
</html>
