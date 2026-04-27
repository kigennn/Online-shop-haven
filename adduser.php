<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

$currentUser = require_roles(['admin', 'staff']);
$isAdmin = ($currentUser['role'] ?? 'user') === 'admin';

$pageTitle = $isAdmin ? 'Add Account' : 'Add User';
$activeNav = 'add-account';
$extraStyles = ['css/admin-panel.css?v=20260427-2'];
$bodyClass = 'portal-shell bg-light admin-panel-page';

$allowedRoles = $isAdmin ? ['user', 'staff', 'admin'] : ['user'];
$error = null;

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$pwd = $_POST['pwd'] ?? substr(bin2hex(random_bytes(4)), 0, 8);
$role = $_POST['role'] ?? $allowedRoles[0];

if (!in_array($role, $allowedRoles, true)) {
    $role = $allowedRoles[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    if ($username === '' || $email === '' || $pwd === '') {
        $error = 'Please complete every field before saving the account.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $duplicateStmt = $conn->prepare(
            'SELECT user_id
             FROM users
             WHERE (username = ? OR email = ?) AND is_delete = 0
             LIMIT 1'
        );
        $duplicateStmt->bind_param('ss', $username, $email);
        $duplicateStmt->execute();
        $existingUser = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();

        if ($existingUser !== null) {
            $error = 'An account with that username or email already exists.';
        } else {
            $hashedPassword = password_hash($pwd, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare('INSERT INTO users (username, email, role, pwd) VALUES (?, ?, ?, ?)');
            $insertStmt->bind_param('ssss', $username, $email, $role, $hashedPassword);

            if ($insertStmt->execute()) {
                $insertStmt->close();
                header('Location: admin.php?status=account_added');
                exit;
            }

            $insertStmt->close();
            $error = 'The account could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/header.php';
?>
<main>
    <div class="container-fluid px-4 py-4">
        <div class="admin-page-header mb-4">
            <div>
                <span class="admin-page-kicker"><?= $isAdmin ? 'Admin workspace' : 'Staff workspace' ?></span>
                <h1 class="mt-3"><?= $isAdmin ? 'Add a User, Staff Member, or Admin' : 'Add a New Shopper' ?></h1>
                <p class="admin-page-lead mb-0">
                    <?= $isAdmin
                        ? 'Create customer, staff, or administrator accounts from the same operations flow.'
                        : 'Create shopper accounts quickly so the store team can bring new readers into the system.' ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="admin.php" class="btn btn-outline-secondary">Back to <?= $isAdmin ? 'Admin Panel' : 'Staff Panel' ?></a>
                <a href="manage-books.php" class="btn btn-outline-secondary">Manage Books</a>
            </div>
        </div>

        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="admin.php"><?= htmlspecialchars($pageTitle === 'Add Account' ? 'Admin Panel' : 'Staff Panel') ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($pageTitle) ?></li>
        </ol>

        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="card border-0 admin-glass-card">
                    <div class="card-body p-4 p-lg-5">
                        <?php if ($error !== null): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-4 align-items-start">
                            <div class="col-lg-7">
                                <form action="" method="post">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="username">Username</label>
                                            <input
                                                id="username"
                                                type="text"
                                                class="form-control"
                                                name="username"
                                                value="<?= htmlspecialchars($username) ?>"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="email">Email</label>
                                            <input
                                                id="email"
                                                type="email"
                                                class="form-control"
                                                name="email"
                                                value="<?= htmlspecialchars($email) ?>"
                                                required
                                            >
                                        </div>
                                        <?php if ($isAdmin): ?>
                                            <div class="col-md-6">
                                                <label class="form-label" for="role">Account Role</label>
                                                <select id="role" class="form-select" name="role" required>
                                                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>System User</option>
                                                    <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff Member</option>
                                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="role" value="user">
                                            <div class="col-md-6">
                                                <label class="form-label">Account Role</label>
                                                <div class="form-control">System User</div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="col-md-6">
                                            <label class="form-label" for="pwd">Temporary Password</label>
                                            <input
                                                id="pwd"
                                                type="text"
                                                class="form-control"
                                                name="pwd"
                                                value="<?= htmlspecialchars($pwd) ?>"
                                                required
                                            >
                                            <div class="form-text">Share this temporary password with the new account owner so they can log in.</div>
                                        </div>
                                    </div>

                                    <div class="mt-4 d-flex justify-content-end gap-2">
                                        <a href="admin.php" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary" name="submit">Save Account</button>
                                    </div>
                                </form>
                            </div>

                            <div class="col-lg-5">
                                <div class="admin-note-box h-100">
                                    <h2 class="h6 mb-3">Access on this page</h2>
                                    <ul class="mb-0">
                                        <?php if ($isAdmin): ?>
                                            <li>Create standard shopper accounts for customers.</li>
                                            <li>Create staff members who manage users and books.</li>
                                            <li>Create administrators who can oversee orders and borrowings too.</li>
                                        <?php else: ?>
                                            <li>Create shopper accounts for new customers.</li>
                                            <li>Keep staff access limited to user support and catalog work.</li>
                                            <li>Administrators remain the only role that can create staff or admin users.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
