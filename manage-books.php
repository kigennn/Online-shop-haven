<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

$currentUser = require_roles(['admin', 'staff']);
$isAdmin = ($currentUser['role'] ?? 'user') === 'admin';

$pageTitle = 'Manage Books';
$activeNav = 'manage-books';
$extraStyles = ['css/admin-panel.css?v=20260427-3'];
$bodyClass = 'portal-shell bg-light admin-panel-page';

function normalize_publish_state(string $value): string
{
    $allowed = ['all', 'dated', 'missing'];

    return in_array($value, $allowed, true) ? $value : 'all';
}

function normalize_book_sort(string $value): string
{
    $allowed = ['newest', 'oldest', 'title_az', 'title_za', 'stock_low', 'stock_high'];

    return in_array($value, $allowed, true) ? $value : 'newest';
}

function format_optional_date(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Not set';
    }

    $parsedDate = date_create_immutable($value);

    return $parsedDate instanceof DateTimeImmutable ? $parsedDate->format('M j, Y') : $value;
}

function manage_books_url(
    string $status = '',
    string $search = '',
    string $publishState = 'all',
    string $sort = 'newest',
    int $editBookId = 0
): string
{
    $params = [];

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($search !== '') {
        $params['book_search'] = $search;
    }

    if ($publishState !== 'all') {
        $params['publish_state'] = $publishState;
    }

    if ($sort !== 'newest') {
        $params['catalog_sort'] = $sort;
    }

    if ($editBookId > 0) {
        $params['edit'] = $editBookId;
    }

    return 'manage-books.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function manage_books_redirect(
    string $status,
    string $search = '',
    string $publishState = 'all',
    string $sort = 'newest',
    int $editBookId = 0
): void
{
    header('Location: ' . manage_books_url($status, $search, $publishState, $sort, $editBookId));
    exit;
}

function valid_optional_date(?string $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    $parsedDate = DateTime::createFromFormat('Y-m-d', $value);

    return $parsedDate !== false && $parsedDate->format('Y-m-d') === $value;
}

$bookSearch = trim((string) ($_GET['book_search'] ?? ''));
$publishState = normalize_publish_state((string) ($_GET['publish_state'] ?? 'all'));
$catalogSort = normalize_book_sort((string) ($_GET['catalog_sort'] ?? 'newest'));
$editingBookId = max(0, (int) ($_GET['edit'] ?? 0));

$feedback = null;
$feedbackType = 'success';
$statusMessages = [
    'book_added' => ['type' => 'success', 'message' => 'The book was added to the catalog successfully.'],
    'book_updated' => ['type' => 'success', 'message' => 'The book details were updated successfully.'],
    'book_deleted' => ['type' => 'success', 'message' => 'The book was removed from the storefront successfully.'],
    'publish_date_updated' => ['type' => 'success', 'message' => 'The publish date was saved successfully.'],
];

if (isset($_GET['status'], $statusMessages[$_GET['status']])) {
    $feedback = $statusMessages[$_GET['status']]['message'];
    $feedbackType = $statusMessages[$_GET['status']]['type'];
}

$title = trim($_POST['title'] ?? '');
$author = trim($_POST['author'] ?? '');
$genre = trim($_POST['genre'] ?? '');
$description = trim($_POST['description'] ?? '');
$coverImage = trim($_POST['cover_image'] ?? '');
$price = trim($_POST['price'] ?? '');
$stockQuantity = trim($_POST['stock_quantity'] ?? '');
$publishedDate = trim($_POST['published_date'] ?? '');
$availableForPurchase = isset($_POST['available_for_purchase']) ? (int) $_POST['available_for_purchase'] : 1;
$availableForBorrow = isset($_POST['available_for_borrow']) ? (int) $_POST['available_for_borrow'] : 1;
$returnSearch = trim((string) ($_POST['return_search'] ?? $bookSearch));
$returnPublishState = normalize_publish_state((string) ($_POST['return_publish_state'] ?? $publishState));
$returnSort = normalize_book_sort((string) ($_POST['return_sort'] ?? $catalogSort));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $priceValue = is_numeric($price) ? (float) $price : -1;
    $stockValue = filter_var($stockQuantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if ($action === 'add_book' || $action === 'update_book') {
        $bookId = (int) ($_POST['book_id'] ?? 0);

        if ($title === '' || $author === '') {
            $feedback = 'Please enter both a title and an author.';
            $feedbackType = 'danger';
        } elseif ($priceValue < 0) {
            $feedback = 'Please enter a valid non-negative price.';
            $feedbackType = 'danger';
        } elseif ($stockValue === false) {
            $feedback = 'Please enter a valid stock quantity.';
            $feedbackType = 'danger';
        } elseif (!in_array($availableForPurchase, [0, 1], true) || !in_array($availableForBorrow, [0, 1], true)) {
            $feedback = 'Please choose valid purchase and borrow options.';
            $feedbackType = 'danger';
        } elseif (!valid_optional_date($publishedDate)) {
            $feedback = 'Please enter a valid publication date.';
            $feedbackType = 'danger';
        } else {
            if ($action === 'add_book') {
                $insertStmt = $conn->prepare(
                    'INSERT INTO books (
                        title,
                        author,
                        genre,
                        description,
                        cover_image,
                        price,
                        stock_quantity,
                        available_for_purchase,
                        available_for_borrow,
                        published_date
                    ) VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?, ?, ?, NULLIF(?, \'\'))'
                );
                $insertStmt->bind_param(
                    'sssssdiiis',
                    $title,
                    $author,
                    $genre,
                    $description,
                    $coverImage,
                    $priceValue,
                    $stockValue,
                    $availableForPurchase,
                    $availableForBorrow,
                    $publishedDate
                );
                $wasSaved = $insertStmt->execute();
                $insertStmt->close();

                if ($wasSaved) {
                    manage_books_redirect('book_added', $returnSearch, $returnPublishState, $returnSort);
                }

                $feedback = 'The book could not be added right now.';
                $feedbackType = 'danger';
            } else {
                if ($bookId <= 0) {
                    $feedback = 'Please choose a valid book to update.';
                    $feedbackType = 'danger';
                } else {
                    $updateStmt = $conn->prepare(
                        'UPDATE books
                         SET title = ?, author = ?, genre = NULLIF(?, \'\'), description = NULLIF(?, \'\'),
                             cover_image = NULLIF(?, \'\'), price = ?, stock_quantity = ?,
                             available_for_purchase = ?, available_for_borrow = ?, published_date = NULLIF(?, \'\')
                         WHERE book_id = ? AND is_delete = 0'
                    );
                    $updateStmt->bind_param(
                        'sssssdiiisi',
                        $title,
                        $author,
                        $genre,
                        $description,
                        $coverImage,
                        $priceValue,
                        $stockValue,
                        $availableForPurchase,
                        $availableForBorrow,
                        $publishedDate,
                        $bookId
                    );
                    $wasSaved = $updateStmt->execute();
                    $updateStmt->close();

                    if ($wasSaved) {
                        manage_books_redirect('book_updated', $returnSearch, $returnPublishState, $returnSort);
                    }

                    $feedback = 'The book could not be updated right now.';
                    $feedbackType = 'danger';
                }
            }
        }
    } elseif ($action === 'delete_book') {
        $bookId = (int) ($_POST['book_id'] ?? 0);

        if ($bookId <= 0) {
            $feedback = 'Please choose a valid book to delete.';
            $feedbackType = 'danger';
        } else {
            $loanCheckStmt = $conn->prepare(
                "SELECT COALESCE(SUM(quantity), 0) AS active_loans
                 FROM borrowings
                 WHERE book_id = ? AND is_delete = 0 AND status IN ('borrowed', 'overdue')"
            );
            $loanCheckStmt->bind_param('i', $bookId);
            $loanCheckStmt->execute();
            $loanCheck = $loanCheckStmt->get_result()->fetch_assoc();
            $loanCheckStmt->close();

            if ((int) ($loanCheck['active_loans'] ?? 0) > 0) {
                $feedback = 'That book still has active borrowings, so it cannot be deleted yet.';
                $feedbackType = 'danger';
            } else {
                $deleteStmt = $conn->prepare('UPDATE books SET is_delete = 1 WHERE book_id = ? AND is_delete = 0');
                $deleteStmt->bind_param('i', $bookId);
                $wasDeleted = $deleteStmt->execute();
                $deleteStmt->close();

                if ($wasDeleted) {
                    manage_books_redirect('book_deleted', $returnSearch, $returnPublishState, $returnSort);
                }

                $feedback = 'The book could not be deleted right now.';
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'update_publish_date') {
        $bookId = (int) ($_POST['book_id'] ?? 0);

        if ($bookId <= 0) {
            $feedback = 'Please choose a valid book before updating the publish date.';
            $feedbackType = 'danger';
        } elseif (!valid_optional_date($publishedDate)) {
            $feedback = 'Please enter a valid publication date.';
            $feedbackType = 'danger';
        } else {
            $publishDateStmt = $conn->prepare(
                'UPDATE books
                 SET published_date = NULLIF(?, \'\')
                 WHERE book_id = ? AND is_delete = 0'
            );
            $publishDateStmt->bind_param('si', $publishedDate, $bookId);
            $dateWasSaved = $publishDateStmt->execute();
            $publishDateStmt->close();

            if ($dateWasSaved) {
                manage_books_redirect('publish_date_updated', $returnSearch, $returnPublishState, $returnSort);
            }

            $feedback = 'The publish date could not be updated right now.';
            $feedbackType = 'danger';
        }
    }
}

try {
    $catalogSummary = db_call_one($conn, 'CALL sp_get_catalog_summary()') ?? [
        'total_books' => 0,
        'total_stock' => 0,
        'purchase_ready' => 0,
        'borrow_ready' => 0,
    ];
    $books = db_call_all($conn, 'CALL sp_get_book_inventory()');
} catch (Throwable $exception) {
    $catalogSummary = [
        'total_books' => 0,
        'total_stock' => 0,
        'purchase_ready' => 0,
        'borrow_ready' => 0,
    ];
    $books = [];

    if ($feedback === null) {
        $feedback = 'We could not load the catalog insights right now.';
        $feedbackType = 'danger';
    }
}

$allBooks = $books;
$editingBook = null;

if ($editingBookId > 0) {
    foreach ($allBooks as $bookRow) {
        if ((int) ($bookRow['book_id'] ?? 0) === $editingBookId) {
            $editingBook = $bookRow;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editingBook !== null) {
    $title = (string) $editingBook['title'];
    $author = (string) $editingBook['author'];
    $genre = (string) $editingBook['genre'];
    $description = (string) $editingBook['description'];
    $coverImage = (string) $editingBook['cover_image'];
    $price = (string) $editingBook['price'];
    $stockQuantity = (string) $editingBook['stock_quantity'];
    $publishedDate = (string) ($editingBook['published_date'] ?? '');
    $availableForPurchase = (int) $editingBook['available_for_purchase'];
    $availableForBorrow = (int) $editingBook['available_for_borrow'];
}

$datedBooks = array_values(array_filter(
    $allBooks,
    static fn(array $book): bool => !empty($book['published_date'])
));
$missingDateCount = max(0, count($allBooks) - count($datedBooks));
$lowStockCount = count(array_filter(
    $allBooks,
    static fn(array $book): bool => (int) ($book['stock_quantity'] ?? 0) <= 3
));
$newestRelease = null;
$oldestRelease = null;

if ($datedBooks !== []) {
    usort(
        $datedBooks,
        static fn(array $left, array $right): int => strcmp((string) $left['published_date'], (string) $right['published_date'])
    );
    $oldestRelease = $datedBooks[0];
    $newestRelease = $datedBooks[count($datedBooks) - 1];
}

$books = array_values(array_filter(
    $allBooks,
    static function (array $book) use ($bookSearch, $publishState): bool {
        $hasDate = !empty($book['published_date']);

        if ($publishState === 'dated' && !$hasDate) {
            return false;
        }

        if ($publishState === 'missing' && $hasDate) {
            return false;
        }

        if ($bookSearch === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            (string) ($book['title'] ?? ''),
            (string) ($book['author'] ?? ''),
            (string) ($book['genre_label'] ?? ''),
            (string) ($book['description'] ?? ''),
        ]));

        return str_contains($haystack, strtolower($bookSearch));
    }
));

usort(
    $books,
    static function (array $left, array $right) use ($catalogSort): int {
        return match ($catalogSort) {
            'oldest' => strcmp(
                (string) ($left['published_date'] ?: '9999-12-31'),
                (string) ($right['published_date'] ?: '9999-12-31')
            ),
            'title_az' => strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? '')),
            'title_za' => strcasecmp((string) ($right['title'] ?? ''), (string) ($left['title'] ?? '')),
            'stock_low' => ((int) ($left['stock_quantity'] ?? 0) <=> (int) ($right['stock_quantity'] ?? 0))
                ?: strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? '')),
            'stock_high' => ((int) ($right['stock_quantity'] ?? 0) <=> (int) ($left['stock_quantity'] ?? 0))
                ?: strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? '')),
            default => strcmp(
                (string) ($right['published_date'] ?: '0000-00-00'),
                (string) ($left['published_date'] ?: '0000-00-00')
            ),
        };
    }
);

$visibleBookCount = count($books);

require_once __DIR__ . '/header.php';
?>
<main>
    <div class="container-fluid px-4 py-4">
        <div class="admin-page-header mb-4">
            <div>
                <span class="admin-page-kicker"><?= $isAdmin ? 'Admin workspace' : 'Staff workspace' ?></span>
                <h1 class="mt-3">Manage the Book Catalog</h1>
                <p class="admin-page-lead mb-0">Add new titles, keep stock accurate, and keep every publish date ready for storefront sorting, release highlights, and reader discovery.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="admin.php" class="btn btn-outline-secondary">Back to <?= $isAdmin ? 'Admin Panel' : 'Staff Panel' ?></a>
                <a href="shop.php" class="btn btn-dark">Open Shop</a>
            </div>
        </div>

        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="admin.php"><?= $isAdmin ? 'Admin Panel' : 'Staff Panel' ?></a></li>
            <li class="breadcrumb-item active">Manage Books</li>
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
                        <p class="admin-summary-label">Titles</p>
                        <h2><?= number_format((int) ($catalogSummary['total_books'] ?? 0)) ?></h2>
                        <p class="mb-0 text-muted">Live books currently visible in the catalog.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Stock</p>
                        <h2><?= number_format((int) ($catalogSummary['total_stock'] ?? 0)) ?></h2>
                        <p class="mb-0 text-muted">Copies available across all active titles.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Buy Ready</p>
                        <h2><?= number_format((int) ($catalogSummary['purchase_ready'] ?? 0)) ?></h2>
                        <p class="mb-0 text-muted">Titles currently available for purchase.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Borrow Ready</p>
                        <h2><?= number_format((int) ($catalogSummary['borrow_ready'] ?? 0)) ?></h2>
                        <p class="mb-0 text-muted">Titles currently available for borrowing.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Dates Set</p>
                        <h2><?= number_format(count($datedBooks)) ?></h2>
                        <p class="mb-0 text-muted">Books with a recorded publish date.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Need Dates</p>
                        <h2><?= number_format($missingDateCount) ?></h2>
                        <p class="mb-0 text-muted">Titles still missing a release date.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Low Stock</p>
                        <h2><?= number_format($lowStockCount) ?></h2>
                        <p class="mb-0 text-muted">Books with 3 copies or fewer remaining.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 admin-summary-card h-100">
                    <div class="card-body">
                        <p class="admin-summary-label">Newest Release</p>
                        <h2 class="admin-summary-textual"><?= $newestRelease !== null ? htmlspecialchars(format_optional_date((string) $newestRelease['published_date'])) : 'No date yet' ?></h2>
                        <p class="mb-0 text-muted"><?= $newestRelease !== null ? htmlspecialchars((string) $newestRelease['title']) : 'Add publish dates to unlock release tracking.' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="admin-glass-card admin-tools-bar mb-3">
                    <div>
                        <span class="admin-summary-label">Catalog Focus</span>
                        <h2 class="h5 mb-1">Showing <?= number_format($visibleBookCount) ?> of <?= number_format(count($allBooks)) ?> titles</h2>
                        <p class="mb-0 text-muted">
                            <?= $publishState === 'missing' ? 'You are looking at books that still need a publish date.' : ($publishState === 'dated' ? 'You are looking at books with release dates already set.' : 'You are looking at the full catalog.') ?>
                        </p>
                    </div>
                    <div class="admin-tools-meta">
                        <div>
                            <span class="book-manager-metric-label">Oldest recorded</span>
                            <strong><?= $oldestRelease !== null ? htmlspecialchars(format_optional_date((string) $oldestRelease['published_date'])) : 'Not available' ?></strong>
                        </div>
                        <div>
                            <span class="book-manager-metric-label">Quick win</span>
                            <strong><?= $missingDateCount > 0 ? number_format($missingDateCount) . ' date' . ($missingDateCount === 1 ? '' : 's') . ' to fill' : 'All dates recorded' ?></strong>
                        </div>
                    </div>
                </div>

                <div class="book-manager-grid">
                    <?php if ($books !== []): ?>
                        <?php foreach ($books as $book): ?>
                            <?php
                            $bookId = (int) $book['book_id'];
                            $genreLabel = $book['genre_label'] !== '' ? $book['genre_label'] : 'General';
                            $coverText = strtoupper(substr((string) $book['title'], 0, 1));
                            $hasPublishedDate = !empty($book['published_date']);
                            $stockLevel = (int) $book['stock_quantity'];
                            ?>
                            <article class="card border-0 admin-glass-card book-manager-card">
                                <div class="card-body">
                                    <div class="book-manager-top">
                                        <?php if ($book['cover_image'] !== ''): ?>
                                            <img class="book-cover" src="<?= htmlspecialchars((string) $book['cover_image']) ?>" alt="<?= htmlspecialchars((string) $book['title']) ?>">
                                        <?php else: ?>
                                            <div class="book-cover book-cover-fallback"><?= htmlspecialchars($coverText) ?></div>
                                        <?php endif; ?>
                                        <div class="book-manager-copy">
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="admin-role-pill is-user"><?= htmlspecialchars($genreLabel) ?></span>
                                                <?php if ((int) $book['available_for_purchase'] === 1): ?>
                                                    <span class="admin-status-pill is-success">Buy enabled</span>
                                                <?php endif; ?>
                                                <?php if ((int) $book['available_for_borrow'] === 1): ?>
                                                    <span class="admin-status-pill is-warning">Borrow enabled</span>
                                                <?php endif; ?>
                                                <?php if (!$hasPublishedDate): ?>
                                                    <span class="admin-status-pill is-danger">Date missing</span>
                                                <?php endif; ?>
                                                <?php if ($stockLevel <= 3): ?>
                                                    <span class="admin-status-pill is-warning">Low stock</span>
                                                <?php endif; ?>
                                            </div>
                                            <h2 class="h5 mb-1"><?= htmlspecialchars((string) $book['title']) ?></h2>
                                            <p class="text-muted mb-2"><?= htmlspecialchars((string) $book['author']) ?></p>
                                            <p class="mb-0"><?= htmlspecialchars((string) ($book['description'] !== '' ? $book['description'] : 'No description has been added for this title yet.')) ?></p>
                                        </div>
                                    </div>

                                    <div class="book-manager-metrics">
                                        <div>
                                            <span class="book-manager-metric-label">Price</span>
                                            <strong>$<?= number_format((float) $book['price'], 2) ?></strong>
                                        </div>
                                        <div>
                                            <span class="book-manager-metric-label">In stock</span>
                                            <strong><?= number_format((int) $book['stock_quantity']) ?></strong>
                                        </div>
                                        <div>
                                            <span class="book-manager-metric-label">Sold</span>
                                            <strong><?= number_format((int) $book['total_sold']) ?></strong>
                                        </div>
                                        <div>
                                            <span class="book-manager-metric-label">Active loans</span>
                                            <strong><?= number_format((int) $book['active_loans']) ?></strong>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                                        <div class="book-manager-release-label">
                                            <span class="book-manager-metric-label">Published</span>
                                            <strong><?= htmlspecialchars(format_optional_date($book['published_date'] !== null ? (string) $book['published_date'] : null)) ?></strong>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a
                                                class="btn btn-dark btn-sm"
                                                href="<?= htmlspecialchars(manage_books_url('', $bookSearch, $publishState, $catalogSort, $bookId)) ?>"
                                            >
                                                Edit in Panel
                                            </a>
                                            <button
                                                class="btn btn-outline-secondary btn-sm"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-book-<?= $bookId ?>"
                                                aria-expanded="false"
                                                aria-controls="edit-book-<?= $bookId ?>"
                                            >
                                                Inline Editor
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this book from the storefront?');">
                                                <input type="hidden" name="action" value="delete_book">
                                                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                                                <input type="hidden" name="return_search" value="<?= htmlspecialchars($bookSearch) ?>">
                                                <input type="hidden" name="return_publish_state" value="<?= htmlspecialchars($publishState) ?>">
                                                <input type="hidden" name="return_sort" value="<?= htmlspecialchars($catalogSort) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="admin-subcard book-release-editor mt-4">
                                        <div>
                                            <h3 class="h6 mb-1">Quick Publish Date Update</h3>
                                            <p class="text-muted mb-0">Change the release date here without reopening the full catalog editor. Leave it empty and save if the date is unknown.</p>
                                        </div>
                                        <form method="post" class="book-release-form">
                                            <input type="hidden" name="action" value="update_publish_date">
                                            <input type="hidden" name="book_id" value="<?= $bookId ?>">
                                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($bookSearch) ?>">
                                            <input type="hidden" name="return_publish_state" value="<?= htmlspecialchars($publishState) ?>">
                                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($catalogSort) ?>">
                                            <input
                                                type="date"
                                                class="form-control"
                                                name="published_date"
                                                value="<?= htmlspecialchars((string) ($book['published_date'] ?? '')) ?>"
                                                aria-label="Publish date for <?= htmlspecialchars((string) $book['title']) ?>"
                                            >
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Save Date</button>
                                        </form>
                                    </div>

                                    <div class="collapse mt-4" id="edit-book-<?= $bookId ?>">
                                        <div class="admin-subcard">
                                            <h3 class="h6 mb-3">Update Book</h3>
                                            <form method="post" class="row g-3">
                                                <input type="hidden" name="action" value="update_book">
                                                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                                                <input type="hidden" name="return_search" value="<?= htmlspecialchars($bookSearch) ?>">
                                                <input type="hidden" name="return_publish_state" value="<?= htmlspecialchars($publishState) ?>">
                                                <input type="hidden" name="return_sort" value="<?= htmlspecialchars($catalogSort) ?>">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="title-<?= $bookId ?>">Title</label>
                                                    <input id="title-<?= $bookId ?>" type="text" class="form-control" name="title" value="<?= htmlspecialchars((string) $book['title']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="author-<?= $bookId ?>">Author</label>
                                                    <input id="author-<?= $bookId ?>" type="text" class="form-control" name="author" value="<?= htmlspecialchars((string) $book['author']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="genre-<?= $bookId ?>">Genre</label>
                                                    <input id="genre-<?= $bookId ?>" type="text" class="form-control" name="genre" value="<?= htmlspecialchars((string) $book['genre']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="published_date-<?= $bookId ?>">Published Date</label>
                                                    <input id="published_date-<?= $bookId ?>" type="date" class="form-control" name="published_date" value="<?= htmlspecialchars((string) ($book['published_date'] ?? '')) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="price-<?= $bookId ?>">Price</label>
                                                    <input id="price-<?= $bookId ?>" type="number" class="form-control" name="price" min="0" step="0.01" value="<?= htmlspecialchars((string) $book['price']) ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="stock_quantity-<?= $bookId ?>">Stock Quantity</label>
                                                    <input id="stock_quantity-<?= $bookId ?>" type="number" class="form-control" name="stock_quantity" min="0" step="1" value="<?= htmlspecialchars((string) $book['stock_quantity']) ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="cover_image-<?= $bookId ?>">Cover Image</label>
                                                    <input id="cover_image-<?= $bookId ?>" type="text" class="form-control" name="cover_image" value="<?= htmlspecialchars((string) $book['cover_image']) ?>" placeholder="img/cover.jpg or URL">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="available_for_purchase-<?= $bookId ?>">Available to Buy</label>
                                                    <select id="available_for_purchase-<?= $bookId ?>" class="form-select" name="available_for_purchase">
                                                        <option value="1" <?= (int) $book['available_for_purchase'] === 1 ? 'selected' : '' ?>>Yes</option>
                                                        <option value="0" <?= (int) $book['available_for_purchase'] === 0 ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="available_for_borrow-<?= $bookId ?>">Available to Borrow</label>
                                                    <select id="available_for_borrow-<?= $bookId ?>" class="form-select" name="available_for_borrow">
                                                        <option value="1" <?= (int) $book['available_for_borrow'] === 1 ? 'selected' : '' ?>>Yes</option>
                                                        <option value="0" <?= (int) $book['available_for_borrow'] === 0 ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="description-<?= $bookId ?>">Description</label>
                                                    <textarea id="description-<?= $bookId ?>" class="form-control" name="description" rows="3"><?= htmlspecialchars((string) $book['description']) ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">Save Book Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card border-0 admin-glass-card">
                            <div class="card-body text-center py-5 text-muted">
                                <?= count($allBooks) === 0 ? 'No books are in the catalog yet. Use the form on the right to add the first title.' : 'No books match these filters yet. Try clearing the search or switching the publish-date view.' ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border-0 admin-glass-card mb-3">
                    <div class="card-header border-0">
                        <h2 class="h5 mb-1">Catalog Tools</h2>
                        <p class="text-muted mb-0">Find books that still need release dates, then sort the shelf the way your team works.</p>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="book_search">Search books</label>
                                <input id="book_search" type="text" class="form-control" name="book_search" value="<?= htmlspecialchars($bookSearch) ?>" placeholder="Title, author, genre, or description">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="publish_state">Publish dates</label>
                                <select id="publish_state" class="form-select" name="publish_state">
                                    <option value="all" <?= $publishState === 'all' ? 'selected' : '' ?>>All books</option>
                                    <option value="dated" <?= $publishState === 'dated' ? 'selected' : '' ?>>Dates already set</option>
                                    <option value="missing" <?= $publishState === 'missing' ? 'selected' : '' ?>>Missing dates</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="catalog_sort">Sort shelf</label>
                                <select id="catalog_sort" class="form-select" name="catalog_sort">
                                    <option value="newest" <?= $catalogSort === 'newest' ? 'selected' : '' ?>>Newest releases</option>
                                    <option value="oldest" <?= $catalogSort === 'oldest' ? 'selected' : '' ?>>Oldest releases</option>
                                    <option value="title_az" <?= $catalogSort === 'title_az' ? 'selected' : '' ?>>Title A-Z</option>
                                    <option value="title_za" <?= $catalogSort === 'title_za' ? 'selected' : '' ?>>Title Z-A</option>
                                    <option value="stock_low" <?= $catalogSort === 'stock_low' ? 'selected' : '' ?>>Lowest stock first</option>
                                    <option value="stock_high" <?= $catalogSort === 'stock_high' ? 'selected' : '' ?>>Highest stock first</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                                <a href="manage-books.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 admin-glass-card sticky-xl-top" style="top: 1.5rem;">
                    <div class="card-header border-0">
                        <h2 class="h5 mb-1"><?= $editingBook !== null ? 'Edit Book Details' : 'Add a New Book' ?></h2>
                        <p class="text-muted mb-0">
                            <?= $editingBook !== null
                                ? 'Update this title from one focused form, then save your changes back to the catalog.'
                                : 'Anything you add here can appear immediately in the reader storefront once it is enabled.' ?>
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="<?= $editingBook !== null ? 'update_book' : 'add_book' ?>">
                            <?php if ($editingBook !== null): ?>
                                <input type="hidden" name="book_id" value="<?= (int) $editingBook['book_id'] ?>">
                            <?php endif; ?>
                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($bookSearch) ?>">
                            <input type="hidden" name="return_publish_state" value="<?= htmlspecialchars($publishState) ?>">
                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($catalogSort) ?>">
                            <?php if ($editingBook !== null): ?>
                                <div class="col-12">
                                    <div class="admin-subcard">
                                        <span class="admin-summary-label">Currently editing</span>
                                        <h3 class="h6 mb-1"><?= htmlspecialchars((string) $editingBook['title']) ?></h3>
                                        <p class="text-muted mb-3"><?= htmlspecialchars((string) $editingBook['author']) ?></p>
                                        <a href="<?= htmlspecialchars(manage_books_url('', $bookSearch, $publishState, $catalogSort)) ?>" class="btn btn-outline-secondary btn-sm">Switch to Add Mode</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label class="form-label" for="title">Title</label>
                                <input id="title" type="text" class="form-control" name="title" value="<?= htmlspecialchars($title) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="author">Author</label>
                                <input id="author" type="text" class="form-control" name="author" value="<?= htmlspecialchars($author) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="genre">Genre</label>
                                <input id="genre" type="text" class="form-control" name="genre" value="<?= htmlspecialchars($genre) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="published_date">Published Date</label>
                                <input id="published_date" type="date" class="form-control" name="published_date" value="<?= htmlspecialchars($publishedDate) ?>">
                                <div class="form-text">Leave this blank if the release date is unknown. You can set or change it later from each book card.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="price">Price</label>
                                <input id="price" type="number" class="form-control" name="price" min="0" step="0.01" value="<?= htmlspecialchars($price) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="stock_quantity">Stock Quantity</label>
                                <input id="stock_quantity" type="number" class="form-control" name="stock_quantity" min="0" step="1" value="<?= htmlspecialchars($stockQuantity) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cover_image">Cover Image</label>
                                <input id="cover_image" type="text" class="form-control" name="cover_image" value="<?= htmlspecialchars($coverImage) ?>" placeholder="img/cover.jpg or URL">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="available_for_purchase">Available to Buy</label>
                                <select id="available_for_purchase" class="form-select" name="available_for_purchase">
                                    <option value="1" <?= $availableForPurchase === 1 ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= $availableForPurchase === 0 ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="available_for_borrow">Available to Borrow</label>
                                <select id="available_for_borrow" class="form-select" name="available_for_borrow">
                                    <option value="1" <?= $availableForBorrow === 1 ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= $availableForBorrow === 0 ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">Description</label>
                                <textarea id="description" class="form-control" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100"><?= $editingBook !== null ? 'Save Book Changes' : 'Add Book' ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
