<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

$currentUser = require_roles(['admin', 'staff']);
$isAdmin = ($currentUser['role'] ?? 'user') === 'admin';

$pageTitle = $isAdmin ? 'Admin Panel' : 'Staff Panel';
$activeNav = 'admin';
$extraStyles = ['css/admin-panel.css?v=20260427-2'];
$bodyClass = 'portal-shell bg-light admin-panel-page';

function panel_redirect(string $status): void
{
    header('Location: admin.php?status=' . urlencode($status));
    exit;
}

function format_panel_date(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y', $timestamp);
}

function status_pill_class(string $status): string
{
    return match ($status) {
        'returned' => 'is-success',
        'overdue' => 'is-danger',
        default => 'is-warning',
    };
}

$feedback = null;
$feedbackType = 'success';
$statusMessages = [
    'account_added' => ['type' => 'success', 'message' => 'The account was created successfully.'],
    'account_deleted' => ['type' => 'success', 'message' => 'The account was deleted successfully.'],
    'account_updated' => ['type' => 'success', 'message' => 'The account details were updated successfully.'],
    'borrowing_recorded' => ['type' => 'success', 'message' => 'The borrowing record was saved and stock was updated.'],
    'borrowing_returned' => ['type' => 'success', 'message' => 'The borrowing record was marked as returned and stock was restored.'],
];

if (isset($_GET['status'], $statusMessages[$_GET['status']])) {
    $feedback = $statusMessages[$_GET['status']]['message'];
    $feedbackType = $statusMessages[$_GET['status']]['type'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_account') {
        $userId = (int) ($_POST['uid'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($userId <= 0 || $username === '' || $email === '') {
            $feedback = 'Please complete the username and email before saving.';
            $feedbackType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedback = 'Please enter a valid email address.';
            $feedbackType = 'danger';
        } else {
            $targetUser = find_user_by_id($conn, $userId);

            if ($targetUser === null) {
                $feedback = 'That account could not be found.';
                $feedbackType = 'danger';
            } elseif (!$isAdmin && ($targetUser['role'] ?? 'user') !== 'user') {
                $feedback = 'Staff members can only edit shopper accounts.';
                $feedbackType = 'danger';
            } else {
                $duplicateStmt = $conn->prepare(
                    'SELECT user_id
                     FROM users
                     WHERE (username = ? OR email = ?) AND user_id <> ? AND is_delete = 0
                     LIMIT 1'
                );
                $duplicateStmt->bind_param('ssi', $username, $email, $userId);
                $duplicateStmt->execute();
                $duplicateUser = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();

                if ($duplicateUser !== null) {
                    $feedback = 'Another account already uses that username or email.';
                    $feedbackType = 'danger';
                } else {
                    if ($isAdmin) {
                        $newRole = $_POST['role'] ?? ($targetUser['role'] ?? 'user');
                        $allowedRoleOptions = ['user', 'staff', 'admin'];

                        if (!in_array($newRole, $allowedRoleOptions, true)) {
                            $feedback = 'Please choose a valid role.';
                            $feedbackType = 'danger';
                        } elseif ((int) $currentUser['uid'] === $userId && $newRole !== ($currentUser['role'] ?? 'admin')) {
                            $feedback = 'You cannot change your own role from this page.';
                            $feedbackType = 'danger';
                        } else {
                            $updateStmt = $conn->prepare(
                                'UPDATE users
                                 SET username = ?, email = ?, role = ?
                                 WHERE user_id = ? AND is_delete = 0'
                            );
                            $updateStmt->bind_param('sssi', $username, $email, $newRole, $userId);
                            $wasUpdated = $updateStmt->execute();
                            $updateStmt->close();

                            if ($wasUpdated) {
                                if ((int) $currentUser['uid'] === $userId) {
                                    refresh_user_session($conn, $userId);
                                }

                                panel_redirect('account_updated');
                            }

                            $feedback = 'We could not update that account right now.';
                            $feedbackType = 'danger';
                        }
                    } else {
                        $updateStmt = $conn->prepare(
                            'UPDATE users
                             SET username = ?, email = ?
                             WHERE user_id = ? AND role = \'user\' AND is_delete = 0'
                        );
                        $updateStmt->bind_param('ssi', $username, $email, $userId);
                        $wasUpdated = $updateStmt->execute();
                        $updateStmt->close();

                        if ($wasUpdated) {
                            panel_redirect('account_updated');
                        }

                        $feedback = 'We could not update that account right now.';
                        $feedbackType = 'danger';
                    }
                }
            }
        }
    } elseif ($action === 'delete_account') {
        $userId = (int) ($_POST['uid'] ?? 0);

        if ($userId <= 0) {
            $feedback = 'Please choose a valid account to delete.';
            $feedbackType = 'danger';
        } elseif ((int) $currentUser['uid'] === $userId) {
            $feedback = 'You cannot delete the account that is currently signed in.';
            $feedbackType = 'danger';
        } else {
            $targetUser = find_user_by_id($conn, $userId);

            if ($targetUser === null) {
                $feedback = 'That account could not be found.';
                $feedbackType = 'danger';
            } elseif (!$isAdmin && ($targetUser['role'] ?? 'user') !== 'user') {
                $feedback = 'Staff members can only delete shopper accounts.';
                $feedbackType = 'danger';
            } else {
                $deleteStmt = $conn->prepare('UPDATE users SET is_delete = 1 WHERE user_id = ? AND is_delete = 0');
                $deleteStmt->bind_param('i', $userId);
                $wasDeleted = $deleteStmt->execute();
                $deleteStmt->close();

                if ($wasDeleted) {
                    panel_redirect('account_deleted');
                }

                $feedback = 'We could not delete that account right now.';
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'record_borrowing') {
        if (!$isAdmin) {
            $feedback = 'Only administrators can record borrowing transactions.';
            $feedbackType = 'danger';
        } else {
            $userId = (int) ($_POST['borrow_user_id'] ?? 0);
            $bookId = (int) ($_POST['borrow_book_id'] ?? 0);
            $quantity = max(1, (int) ($_POST['borrow_quantity'] ?? 1));
            $notes = trim($_POST['notes'] ?? '');
            $dueDateInput = trim($_POST['due_date'] ?? '');
            $dueDate = $dueDateInput === '' ? date('Y-m-d', strtotime('+14 days')) : $dueDateInput;

            $parsedDate = DateTime::createFromFormat('Y-m-d', $dueDate);

            if ($userId <= 0 || $bookId <= 0) {
                $feedback = 'Choose both an account and a book before saving the borrowing.';
                $feedbackType = 'danger';
            } elseif ($parsedDate === false || $parsedDate->format('Y-m-d') !== $dueDate) {
                $feedback = 'Please enter a valid due date.';
                $feedbackType = 'danger';
            } else {
                $personStmt = $conn->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE user_id = ? AND is_delete = 0 AND role IN ('user', 'staff')
                     LIMIT 1"
                );
                $personStmt->bind_param('i', $userId);
                $personStmt->execute();
                $personExists = $personStmt->get_result()->fetch_assoc() !== null;
                $personStmt->close();

                $bookStmt = $conn->prepare(
                    'SELECT book_id, stock_quantity, available_for_borrow
                     FROM books
                     WHERE book_id = ? AND is_delete = 0
                     LIMIT 1'
                );
                $bookStmt->bind_param('i', $bookId);
                $bookStmt->execute();
                $bookData = $bookStmt->get_result()->fetch_assoc();
                $bookStmt->close();

                if (!$personExists || $bookData === null) {
                    $feedback = 'The selected user or book could not be found.';
                    $feedbackType = 'danger';
                } elseif ((int) ($bookData['available_for_borrow'] ?? 0) !== 1) {
                    $feedback = 'That title is not available for borrowing.';
                    $feedbackType = 'danger';
                } elseif ((int) ($bookData['stock_quantity'] ?? 0) < $quantity) {
                    $feedback = 'There is not enough stock left to record that borrowing.';
                    $feedbackType = 'danger';
                } else {
                    try {
                        $conn->begin_transaction();

                        $updateStockStmt = $conn->prepare(
                            'UPDATE books
                             SET stock_quantity = stock_quantity - ?
                             WHERE book_id = ? AND is_delete = 0 AND stock_quantity >= ?'
                        );
                        $updateStockStmt->bind_param('iii', $quantity, $bookId, $quantity);
                        $updateStockStmt->execute();
                        $stockChanged = $updateStockStmt->affected_rows > 0;
                        $updateStockStmt->close();

                        if (!$stockChanged) {
                            throw new RuntimeException('Stock could not be updated.');
                        }

                        $insertBorrowingStmt = $conn->prepare(
                            'INSERT INTO borrowings (user_id, book_id, quantity, due_date, notes, status)
                             VALUES (?, ?, ?, ?, NULLIF(?, \'\'), \'borrowed\')'
                        );
                        $insertBorrowingStmt->bind_param('iiiss', $userId, $bookId, $quantity, $dueDate, $notes);
                        $insertBorrowingStmt->execute();
                        $insertBorrowingStmt->close();

                        $conn->commit();
                        panel_redirect('borrowing_recorded');
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $feedback = 'We could not save that borrowing record right now.';
                        $feedbackType = 'danger';
                    }
                }
            }
        }
    } elseif ($action === 'mark_returned') {
        if (!$isAdmin) {
            $feedback = 'Only administrators can mark borrowings as returned.';
            $feedbackType = 'danger';
        } else {
            $borrowingId = (int) ($_POST['borrowing_id'] ?? 0);

            if ($borrowingId <= 0) {
                $feedback = 'Please choose a valid borrowing record.';
                $feedbackType = 'danger';
            } else {
                $borrowingStmt = $conn->prepare(
                    'SELECT borrowing_id, book_id, quantity, status
                     FROM borrowings
                     WHERE borrowing_id = ? AND is_delete = 0
                     LIMIT 1'
                );
                $borrowingStmt->bind_param('i', $borrowingId);
                $borrowingStmt->execute();
                $borrowingData = $borrowingStmt->get_result()->fetch_assoc();
                $borrowingStmt->close();

                if ($borrowingData === null) {
                    $feedback = 'That borrowing record could not be found.';
                    $feedbackType = 'danger';
                } elseif (($borrowingData['status'] ?? 'borrowed') === 'returned') {
                    $feedback = 'That borrowing record has already been completed.';
                    $feedbackType = 'danger';
                } else {
                    $bookId = (int) $borrowingData['book_id'];
                    $quantity = max(1, (int) $borrowingData['quantity']);

                    try {
                        $conn->begin_transaction();

                        $returnStmt = $conn->prepare(
                            "UPDATE borrowings
                             SET status = 'returned', returned_at = CURDATE()
                             WHERE borrowing_id = ? AND is_delete = 0"
                        );
                        $returnStmt->bind_param('i', $borrowingId);
                        $returnStmt->execute();
                        $wasReturned = $returnStmt->affected_rows > 0;
                        $returnStmt->close();

                        if (!$wasReturned) {
                            throw new RuntimeException('Borrowing status was not updated.');
                        }

                        $restoreStockStmt = $conn->prepare(
                            'UPDATE books
                             SET stock_quantity = stock_quantity + ?
                             WHERE book_id = ? AND is_delete = 0'
                        );
                        $restoreStockStmt->bind_param('ii', $quantity, $bookId);
                        $restoreStockStmt->execute();
                        $restoreStockStmt->close();

                        $conn->commit();
                        panel_redirect('borrowing_returned');
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $feedback = 'We could not update that borrowing record right now.';
                        $feedbackType = 'danger';
                    }
                }
            }
        }
    }
}

try {
    $dashboardTotals = db_call_one($conn, 'CALL sp_get_admin_dashboard_totals(?)', 's', [(string) ($currentUser['role'] ?? 'user')]) ?? [];
    $people = db_call_all($conn, 'CALL sp_get_account_directory(?)', 's', [(string) ($currentUser['role'] ?? 'user')]);
} catch (Throwable $exception) {
    $dashboardTotals = [];
    $people = [];

    if ($feedback === null) {
        $feedback = 'We could not load the dashboard summaries right now.';
        $feedbackType = 'danger';
    }
}

$peopleSummary = [
    'total_accounts' => (int) ($dashboardTotals['total_accounts'] ?? 0),
    'total_users' => (int) ($dashboardTotals['total_users'] ?? 0),
    'total_staff' => (int) ($dashboardTotals['total_staff'] ?? 0),
    'total_admins' => (int) ($dashboardTotals['total_admins'] ?? 0),
];

$catalogSummary = [
    'total_books' => (int) ($dashboardTotals['total_books'] ?? 0),
    'total_stock' => (int) ($dashboardTotals['total_stock'] ?? 0),
    'purchase_ready' => (int) ($dashboardTotals['purchase_ready'] ?? 0),
    'borrow_ready' => (int) ($dashboardTotals['borrow_ready'] ?? 0),
];

$salesSummary = [
    'total_orders' => (int) ($dashboardTotals['total_orders'] ?? 0),
    'total_books_sold' => (int) ($dashboardTotals['total_books_sold'] ?? 0),
    'total_sales' => (float) ($dashboardTotals['total_sales'] ?? 0),
];

$borrowSummary = [
    'total_borrow_records' => (int) ($dashboardTotals['total_borrow_records'] ?? 0),
    'active_loans' => (int) ($dashboardTotals['active_loans'] ?? 0),
];

$activityByUser = [];

if ($isAdmin) {
    foreach ($people as $person) {
        $activityByUser[(int) $person['user_id']] = [
            'purchases' => [],
            'borrowings' => [],
        ];
    }

    $purchaseActivitySql = "
        SELECT
            o.user_id,
            o.order_id,
            o.order_status,
            o.created_at AS order_date,
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) AS line_total,
            COALESCE(b.title, 'Unknown book') AS title,
            COALESCE(b.author, 'Unknown author') AS author
        FROM orders o
        INNER JOIN orderitems oi ON oi.order_id = o.order_id
        LEFT JOIN books b ON b.book_id = oi.book_id
        WHERE o.is_delete = 0
        ORDER BY o.created_at DESC, o.order_id DESC, oi.order_item_id DESC
    ";
    $purchaseActivity = $conn->query($purchaseActivitySql);

    if ($purchaseActivity instanceof mysqli_result) {
        while ($purchase = $purchaseActivity->fetch_assoc()) {
            $userId = (int) $purchase['user_id'];

            if (isset($activityByUser[$userId])) {
                $activityByUser[$userId]['purchases'][] = $purchase;
            }
        }
    }

    $borrowingActivitySql = "
        SELECT
            br.borrowing_id,
            br.user_id,
            br.quantity,
            br.borrowed_at,
            br.due_date,
            br.returned_at,
            br.status,
            br.notes,
            COALESCE(b.title, 'Unknown book') AS title,
            COALESCE(b.author, 'Unknown author') AS author
        FROM borrowings br
        LEFT JOIN books b ON b.book_id = br.book_id
        WHERE br.is_delete = 0
        ORDER BY br.borrowed_at DESC, br.borrowing_id DESC
    ";
    $borrowingActivity = $conn->query($borrowingActivitySql);

    if ($borrowingActivity instanceof mysqli_result) {
        while ($borrowing = $borrowingActivity->fetch_assoc()) {
            $userId = (int) $borrowing['user_id'];

            if (isset($activityByUser[$userId])) {
                $activityByUser[$userId]['borrowings'][] = $borrowing;
            }
        }
    }
}

$bookOptions = [];

if ($isAdmin) {
    try {
        $bookOptions = db_fetch_all(
            $conn,
            'SELECT book_id, title, author, stock_quantity
             FROM vw_book_catalog_metrics
             WHERE available_for_borrow = 1
             ORDER BY title ASC'
        );
    } catch (Throwable $exception) {
        $bookOptions = [];
    }
}

require_once __DIR__ . '/header.php';
?>
<main>
    <div class="container-fluid px-4 py-4">
        <div class="admin-page-header mb-4">
            <div>
                <span class="admin-page-kicker"><?= $isAdmin ? 'Admin workspace' : 'Staff workspace' ?></span>
                <h1 class="mt-3"><?= $isAdmin ? 'Operations, Accounts, and Borrowing Oversight' : 'Staff Access for Users and Books' ?></h1>
                <p class="admin-page-lead mb-0">
                    <?= $isAdmin
                        ? 'Track shoppers, staff, purchases, and borrowed books from one polished operations hub.'
                        : 'Manage shopper accounts and keep the catalog up to date without exposing the full admin controls.' ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="adduser.php" class="btn btn-dark"><?= $isAdmin ? 'Add Account' : 'Add User' ?></a>
                <a href="manage-books.php" class="btn btn-outline-secondary">Manage Books</a>
                <?php if ($isAdmin): ?>
                    <a href="#borrowingForm" class="btn btn-outline-secondary">Record Borrowing</a>
                <?php endif; ?>
            </div>
        </div>

        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active"><?= htmlspecialchars($pageTitle) ?></li>
        </ol>

        <?php if ($feedback !== null): ?>
            <div class="alert alert-<?= htmlspecialchars($feedbackType) ?>" role="alert">
                <?= htmlspecialchars($feedback) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label"><?= $isAdmin ? 'Accounts' : 'Shoppers' ?></p>
                        <h2><?= number_format((int) ($isAdmin ? ($peopleSummary['total_accounts'] ?? 0) : ($peopleSummary['total_users'] ?? 0))) ?></h2>
                        <p class="mb-0 text-muted">
                            <?php if ($isAdmin): ?>
                                <?= number_format((int) ($peopleSummary['total_users'] ?? 0)) ?> users,
                                <?= number_format((int) ($peopleSummary['total_staff'] ?? 0)) ?> staff,
                                <?= number_format((int) ($peopleSummary['total_admins'] ?? 0)) ?> admins
                            <?php else: ?>
                                Only shopper accounts appear in the staff view.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Catalog</p>
                        <h2><?= number_format((int) ($catalogSummary['total_books'] ?? 0)) ?></h2>
                        <p class="mb-0 text-muted"><?= number_format((int) ($catalogSummary['total_stock'] ?? 0)) ?> copies currently in stock</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label"><?= $isAdmin ? 'Sales' : 'Purchase Ready' ?></p>
                        <h2><?= number_format((int) ($isAdmin ? ($salesSummary['total_orders'] ?? 0) : ($catalogSummary['purchase_ready'] ?? 0))) ?></h2>
                        <p class="mb-0 text-muted">
                            <?php if ($isAdmin): ?>
                                <?= number_format((int) ($salesSummary['total_books_sold'] ?? 0)) ?> sold,
                                $<?= number_format((float) ($salesSummary['total_sales'] ?? 0), 2) ?> total
                            <?php else: ?>
                                Titles that can be bought from the shop.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label"><?= $isAdmin ? 'Borrowings' : 'Borrow Ready' ?></p>
                        <h2><?= number_format((int) ($isAdmin ? ($borrowSummary['active_loans'] ?? 0) : ($catalogSummary['borrow_ready'] ?? 0))) ?></h2>
                        <p class="mb-0 text-muted">
                            <?php if ($isAdmin): ?>
                                <?= number_format((int) ($borrowSummary['total_borrow_records'] ?? 0)) ?> borrowing records tracked
                            <?php else: ?>
                                Titles available for library-style borrowing.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-xl-<?= $isAdmin ? '8' : '9' ?>">
                <div class="card border-0 admin-glass-card">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h2 class="h5 mb-1"><?= $isAdmin ? 'Account Directory' : 'User Directory' ?></h2>
                            <p class="text-muted mb-0">
                                <?= $isAdmin
                                    ? 'Open any row to update account details and review that person’s activity.'
                                    : 'Staff can edit or remove shopper accounts and then move to book management for the catalog.' ?>
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle admin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Person</th>
                                        <th scope="col">Status</th>
                                        <th scope="col"><?= $isAdmin ? 'Activity' : 'Joined' ?></th>
                                        <th scope="col" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($people !== []): ?>
                                        <?php foreach ($people as $person): ?>
                                            <?php $personId = (int) $person['user_id']; ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($person['username']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($person['email']) ?></div>
                                                    <div class="text-muted small">
                                                        <?= $person['city'] !== '' || $person['country'] !== ''
                                                            ? htmlspecialchars(trim($person['city'] . ', ' . $person['country'], ', '))
                                                            : 'No address saved' ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="admin-role-pill is-<?= htmlspecialchars((string) $person['role']) ?>">
                                                        <?= htmlspecialchars(ucfirst((string) $person['role'])) ?>
                                                    </span>
                                                    <?php if ($personId === (int) $currentUser['uid']): ?>
                                                        <div class="text-muted small mt-2">Current session account</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isAdmin): ?>
                                                        <div class="fw-semibold"><?= (int) $person['purchase_orders'] ?> orders placed</div>
                                                        <div class="text-muted small"><?= (int) $person['books_bought'] ?> bought, <?= (int) $person['borrowed_total'] ?> borrowed</div>
                                                        <div class="text-muted small">$<?= number_format((float) $person['amount_spent'], 2) ?> spent, <?= (int) $person['active_loans'] ?> active loans</div>
                                                    <?php else: ?>
                                                        <div class="fw-semibold"><?= format_panel_date((string) $person['created_at']) ?></div>
                                                        <div class="text-muted small">Shopper account</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                        <button
                                                            class="btn btn-outline-secondary btn-sm"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#account-panel-<?= $personId ?>"
                                                            aria-expanded="false"
                                                            aria-controls="account-panel-<?= $personId ?>"
                                                        >
                                                            Manage
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this account?');">
                                                            <input type="hidden" name="action" value="delete_account">
                                                            <input type="hidden" name="uid" value="<?= $personId ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class="activity-row">
                                                <td colspan="4" class="border-0 pt-0">
                                                    <div class="collapse" id="account-panel-<?= $personId ?>">
                                                        <div class="admin-activity-panel">
                                                            <div class="row g-4">
                                                                <div class="col-lg-<?= $isAdmin ? '4' : '12' ?>">
                                                                    <div class="admin-subcard h-100">
                                                                        <h3 class="h6 mb-3">Edit Account</h3>
                                                                        <form method="post" class="row g-3">
                                                                            <input type="hidden" name="action" value="save_account">
                                                                            <input type="hidden" name="uid" value="<?= $personId ?>">
                                                                            <div class="col-12">
                                                                                <label class="form-label" for="username-<?= $personId ?>">Username</label>
                                                                                <input
                                                                                    id="username-<?= $personId ?>"
                                                                                    type="text"
                                                                                    class="form-control"
                                                                                    name="username"
                                                                                    value="<?= htmlspecialchars($person['username']) ?>"
                                                                                    required
                                                                                >
                                                                            </div>
                                                                            <div class="col-12">
                                                                                <label class="form-label" for="email-<?= $personId ?>">Email</label>
                                                                                <input
                                                                                    id="email-<?= $personId ?>"
                                                                                    type="email"
                                                                                    class="form-control"
                                                                                    name="email"
                                                                                    value="<?= htmlspecialchars($person['email']) ?>"
                                                                                    required
                                                                                >
                                                                            </div>
                                                                            <?php if ($isAdmin): ?>
                                                                                <div class="col-12">
                                                                                    <label class="form-label" for="role-<?= $personId ?>">Role</label>
                                                                                    <select
                                                                                        id="role-<?= $personId ?>"
                                                                                        class="form-select"
                                                                                        name="role"
                                                                                        <?= $personId === (int) $currentUser['uid'] ? 'disabled' : '' ?>
                                                                                    >
                                                                                        <option value="user" <?= $person['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                                                        <option value="staff" <?= $person['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                                                                        <option value="admin" <?= $person['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                                    </select>
                                                                                    <?php if ($personId === (int) $currentUser['uid']): ?>
                                                                                        <input type="hidden" name="role" value="<?= htmlspecialchars((string) $person['role']) ?>">
                                                                                        <div class="form-text">Your own role stays locked on this page for safety.</div>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                            <div class="col-12">
                                                                                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>

                                                                <?php if ($isAdmin): ?>
                                                                    <div class="col-lg-4">
                                                                        <div class="admin-subcard h-100">
                                                                            <h3 class="h6 mb-3">Books Bought</h3>
                                                                            <?php if ($activityByUser[$personId]['purchases'] !== []): ?>
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm align-middle mb-0">
                                                                                        <thead>
                                                                                            <tr>
                                                                                                <th>Book</th>
                                                                                                <th>Order</th>
                                                                                                <th>Total</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <?php foreach ($activityByUser[$personId]['purchases'] as $purchase): ?>
                                                                                                <tr>
                                                                                                    <td>
                                                                                                        <div class="fw-semibold"><?= htmlspecialchars((string) $purchase['title']) ?></div>
                                                                                                        <div class="text-muted small"><?= htmlspecialchars((string) $purchase['author']) ?></div>
                                                                                                        <div class="text-muted small">Qty: <?= (int) $purchase['quantity'] ?></div>
                                                                                                    </td>
                                                                                                    <td>
                                                                                                        <div>#<?= (int) $purchase['order_id'] ?></div>
                                                                                                        <div class="text-muted small"><?= htmlspecialchars((string) $purchase['order_status']) ?></div>
                                                                                                        <div class="text-muted small"><?= format_panel_date((string) $purchase['order_date']) ?></div>
                                                                                                    </td>
                                                                                                    <td>$<?= number_format((float) $purchase['line_total'], 2) ?></td>
                                                                                                </tr>
                                                                                            <?php endforeach; ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <p class="text-muted mb-0">No purchases recorded for this account yet.</p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-lg-4">
                                                                        <div class="admin-subcard h-100">
                                                                            <h3 class="h6 mb-3">Books Borrowed</h3>
                                                                            <?php if ($activityByUser[$personId]['borrowings'] !== []): ?>
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm align-middle mb-0">
                                                                                        <thead>
                                                                                            <tr>
                                                                                                <th>Book</th>
                                                                                                <th>Status</th>
                                                                                                <th class="text-end">Action</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <?php foreach ($activityByUser[$personId]['borrowings'] as $borrowing): ?>
                                                                                                <tr>
                                                                                                    <td>
                                                                                                        <div class="fw-semibold"><?= htmlspecialchars((string) $borrowing['title']) ?></div>
                                                                                                        <div class="text-muted small"><?= htmlspecialchars((string) $borrowing['author']) ?></div>
                                                                                                        <div class="text-muted small">Qty: <?= (int) $borrowing['quantity'] ?></div>
                                                                                                        <div class="text-muted small">Due: <?= $borrowing['due_date'] !== null ? format_panel_date((string) $borrowing['due_date']) : 'Not set' ?></div>
                                                                                                    </td>
                                                                                                    <td>
                                                                                                        <span class="admin-status-pill <?= status_pill_class((string) $borrowing['status']) ?>">
                                                                                                            <?= htmlspecialchars(ucfirst((string) $borrowing['status'])) ?>
                                                                                                        </span>
                                                                                                        <div class="text-muted small mt-2"><?= format_panel_date((string) $borrowing['borrowed_at']) ?></div>
                                                                                                        <?php if ($borrowing['returned_at'] !== null): ?>
                                                                                                            <div class="text-muted small">Returned <?= format_panel_date((string) $borrowing['returned_at']) ?></div>
                                                                                                        <?php endif; ?>
                                                                                                        <?php if (!empty($borrowing['notes'])): ?>
                                                                                                            <div class="text-muted small mt-1"><?= htmlspecialchars((string) $borrowing['notes']) ?></div>
                                                                                                        <?php endif; ?>
                                                                                                    </td>
                                                                                                    <td class="text-end">
                                                                                                        <?php if (($borrowing['status'] ?? 'borrowed') !== 'returned'): ?>
                                                                                                            <form method="post">
                                                                                                                <input type="hidden" name="action" value="mark_returned">
                                                                                                                <input type="hidden" name="borrowing_id" value="<?= (int) $borrowing['borrowing_id'] ?>">
                                                                                                                <button type="submit" class="btn btn-outline-primary btn-sm">Mark Returned</button>
                                                                                                            </form>
                                                                                                        <?php else: ?>
                                                                                                            <span class="text-muted small">Completed</span>
                                                                                                        <?php endif; ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            <?php endforeach; ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <p class="text-muted mb-0">No borrowing records recorded for this account yet.</p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No accounts are available in this view yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-<?= $isAdmin ? '4' : '3' ?>">
                <?php if ($isAdmin): ?>
                    <div class="card border-0 admin-glass-card sticky-xl-top" id="borrowingForm" style="top: 1.5rem;">
                        <div class="card-header border-0">
                            <h2 class="h5 mb-1">Record a Borrowed Book</h2>
                            <p class="text-muted mb-0">Manual borrowings also adjust stock so the catalog and staff screens stay accurate.</p>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="action" value="record_borrowing">
                                <div class="col-12">
                                    <label class="form-label" for="borrow_user_id">User or Staff Member</label>
                                    <select id="borrow_user_id" class="form-select" name="borrow_user_id" required>
                                        <option value="">Choose an account</option>
                                        <?php foreach ($people as $person): ?>
                                            <?php if (($person['role'] ?? 'user') === 'admin') { continue; } ?>
                                            <option value="<?= (int) $person['user_id'] ?>">
                                                <?= htmlspecialchars($person['username']) ?> (<?= htmlspecialchars(ucfirst((string) $person['role'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="borrow_book_id">Book</label>
                                    <select id="borrow_book_id" class="form-select" name="borrow_book_id" required>
                                        <option value="">Choose a book</option>
                                        <?php foreach ($bookOptions as $book): ?>
                                            <option value="<?= (int) $book['book_id'] ?>">
                                                <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?> (Stock: <?= (int) $book['stock_quantity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="borrow_quantity">Quantity</label>
                                    <input id="borrow_quantity" type="number" class="form-control" name="borrow_quantity" min="1" value="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="due_date">Due Date</label>
                                    <input id="due_date" type="date" class="form-control" name="due_date" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+14 days'))) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="notes">Notes</label>
                                    <textarea id="notes" class="form-control" name="notes" rows="3" placeholder="Optional note for this borrowing"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100">Save Borrowing Record</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 admin-glass-card sticky-xl-top" style="top: 1.5rem;">
                        <div class="card-header border-0">
                            <h2 class="h5 mb-1">Staff Permissions</h2>
                            <p class="text-muted mb-0">This page keeps staff focused on day-to-day catalog and shopper support work.</p>
                        </div>
                        <div class="card-body">
                            <div class="admin-note-box">
                                <h3 class="h6 mb-2">You can</h3>
                                <ul class="mb-0">
                                    <li>Add, edit, and delete shopper accounts.</li>
                                    <li>Add, update, and delete books from the catalog.</li>
                                    <li>Keep the storefront ready for buying and borrowing.</li>
                                </ul>
                            </div>
                            <div class="admin-note-box mt-3">
                                <h3 class="h6 mb-2">You cannot</h3>
                                <ul class="mb-0">
                                    <li>Manage administrator accounts.</li>
                                    <li>Create staff or admin accounts.</li>
                                    <li>Record or complete borrowing transactions.</li>
                                </ul>
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <a href="adduser.php" class="btn btn-primary">Add User</a>
                                <a href="manage-books.php" class="btn btn-outline-secondary">Open Book Manager</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
