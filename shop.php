<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

$currentUser = require_login();

$pageTitle = 'Shop Books';
$activeNav = 'shop';
$extraStyles = ['css/shop.css?v=20260427-2'];
$bodyClass = 'portal-shell shop-page';

function get_shop_cart(): array
{
    $cart = $_SESSION['shop_cart'] ?? ['buy' => [], 'borrow' => []];
    $normalized = ['buy' => [], 'borrow' => []];

    foreach (['buy', 'borrow'] as $mode) {
        $items = $cart[$mode] ?? [];

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $bookId => $quantity) {
            $safeBookId = (int) $bookId;
            $safeQuantity = (int) $quantity;

            if ($safeBookId > 0 && $safeQuantity > 0) {
                $normalized[$mode][$safeBookId] = $safeQuantity;
            }
        }
    }

    return $normalized;
}

function save_shop_cart(array $cart): void
{
    $_SESSION['shop_cart'] = $cart;
}

function shop_item_count(array $items): int
{
    return array_sum(array_map('intval', $items));
}

function shop_total_requested(array $cart, int $bookId): int
{
    return (int) ($cart['buy'][$bookId] ?? 0) + (int) ($cart['borrow'][$bookId] ?? 0);
}

function normalize_shop_sort(string $value): string
{
    $allowed = ['featured', 'newest', 'oldest', 'price_low', 'price_high', 'popular', 'title'];

    return in_array($value, $allowed, true) ? $value : 'featured';
}

function shop_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Date not set';
    }

    $parsedDate = date_create_immutable($value);

    return $parsedDate instanceof DateTimeImmutable ? $parsedDate->format('M j, Y') : $value;
}

function shop_release_label(?string $value): ?array
{
    if ($value === null || $value === '') {
        return null;
    }

    $releaseYear = (int) substr($value, 0, 4);
    $currentYear = (int) date('Y');

    if ($releaseYear >= $currentYear - 3) {
        return ['label' => 'Recent release', 'class' => 'is-fresh'];
    }

    if ($releaseYear <= $currentYear - 20) {
        return ['label' => 'Backlist pick', 'class' => 'is-classic'];
    }

    return ['label' => 'Shelf staple', 'class' => 'is-staple'];
}

function shop_url(string $genre = 'All', string $status = '', string $search = '', string $sort = 'featured'): string
{
    $params = [];

    if ($genre !== '' && strcasecmp($genre, 'All') !== 0) {
        $params['genre'] = $genre;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($sort !== 'featured') {
        $params['sort'] = $sort;
    }

    return 'shop.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function shop_redirect(string $genre = 'All', string $status = '', string $search = '', string $sort = 'featured'): void
{
    header('Location: ' . shop_url($genre, $status, $search, $sort));
    exit;
}

function shop_cart_to_json_items(array $items): string
{
    $payload = [];

    foreach ($items as $bookId => $quantity) {
        $safeBookId = (int) $bookId;
        $safeQuantity = (int) $quantity;

        if ($safeBookId > 0 && $safeQuantity > 0) {
            $payload[] = [
                'book_id' => $safeBookId,
                'quantity' => $safeQuantity,
            ];
        }
    }

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

$cart = get_shop_cart();
$selectedGenre = trim((string) ($_GET['genre'] ?? 'All'));
$selectedGenre = $selectedGenre === '' ? 'All' : $selectedGenre;
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$selectedSort = normalize_shop_sort((string) ($_GET['sort'] ?? 'featured'));

$feedback = null;
$feedbackType = 'success';
$statusMessages = [
    'added' => ['type' => 'success', 'message' => 'The book was added to your cart.'],
    'removed' => ['type' => 'success', 'message' => 'The book was removed from your cart.'],
    'cleared' => ['type' => 'success', 'message' => 'Your cart was cleared.'],
    'checked_out' => ['type' => 'success', 'message' => 'Checkout completed. Your purchase and borrowing records were saved.'],
];

if (isset($_GET['status'], $statusMessages[$_GET['status']])) {
    $feedback = $statusMessages[$_GET['status']]['message'];
    $feedbackType = $statusMessages[$_GET['status']]['type'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnGenre = trim((string) ($_POST['return_genre'] ?? $selectedGenre));
    $returnGenre = $returnGenre === '' ? 'All' : $returnGenre;
    $returnSearch = trim((string) ($_POST['return_search'] ?? $searchTerm));
    $returnSort = normalize_shop_sort((string) ($_POST['return_sort'] ?? $selectedSort));

    if ($action === 'add_to_cart') {
        $bookId = (int) ($_POST['book_id'] ?? 0);
        $mode = $_POST['cart_mode'] ?? '';
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        if ($bookId <= 0 || !in_array($mode, ['buy', 'borrow'], true)) {
            $feedback = 'Please choose a valid book action.';
            $feedbackType = 'danger';
        } else {
            try {
                $book = db_fetch_one(
                    $conn,
                    'SELECT book_id, title, stock_quantity, available_for_purchase, available_for_borrow
                     FROM vw_book_catalog_metrics
                     WHERE book_id = ?
                     LIMIT 1',
                    'i',
                    [$bookId]
                );

                if ($book === null) {
                    $feedback = 'That book is no longer available.';
                    $feedbackType = 'danger';
                } elseif ($mode === 'buy' && (int) ($book['available_for_purchase'] ?? 0) !== 1) {
                    $feedback = 'That title is not available to buy right now.';
                    $feedbackType = 'danger';
                } elseif ($mode === 'borrow' && (int) ($book['available_for_borrow'] ?? 0) !== 1) {
                    $feedback = 'That title is not available to borrow right now.';
                    $feedbackType = 'danger';
                } elseif (shop_total_requested($cart, $bookId) + $quantity > (int) ($book['stock_quantity'] ?? 0)) {
                    $feedback = 'There is not enough stock left for that quantity.';
                    $feedbackType = 'danger';
                } else {
                    $cart[$mode][$bookId] = (int) ($cart[$mode][$bookId] ?? 0) + $quantity;
                    save_shop_cart($cart);
                    shop_redirect($returnGenre, 'added', $returnSearch, $returnSort);
                }
            } catch (Throwable $exception) {
                $feedback = 'We could not add that book right now.';
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'remove_from_cart') {
        $bookId = (int) ($_POST['book_id'] ?? 0);
        $mode = $_POST['cart_mode'] ?? '';

        if ($bookId > 0 && in_array($mode, ['buy', 'borrow'], true)) {
            unset($cart[$mode][$bookId]);
            save_shop_cart($cart);
            shop_redirect($returnGenre, 'removed', $returnSearch, $returnSort);
        }
    } elseif ($action === 'clear_cart') {
        $cart = ['buy' => [], 'borrow' => []];
        save_shop_cart($cart);
        shop_redirect($returnGenre, 'cleared', $returnSearch, $returnSort);
    } elseif ($action === 'checkout') {
        if ($cart['buy'] === [] && $cart['borrow'] === []) {
            $feedback = 'Add at least one book to your cart before checking out.';
            $feedbackType = 'danger';
        } else {
            try {
                $checkoutResult = db_call_one(
                    $conn,
                    'CALL sp_process_checkout(?, ?, ?)',
                    'iss',
                    [
                        (int) $currentUser['uid'],
                        shop_cart_to_json_items($cart['buy']),
                        shop_cart_to_json_items($cart['borrow']),
                    ]
                );

                if ($checkoutResult === null) {
                    throw new RuntimeException('Checkout did not return a confirmation payload.');
                }

                $cart = ['buy' => [], 'borrow' => []];
                save_shop_cart($cart);
                shop_redirect($returnGenre, 'checked_out', $returnSearch, $returnSort);
            } catch (Throwable $exception) {
                $feedback = $exception->getMessage() !== '' ? $exception->getMessage() : 'Checkout could not be completed right now. Please try again.';
                $feedbackType = 'danger';
            }
        }
    }
}

try {
    $genres = db_call_all($conn, 'CALL sp_get_shop_genres()');
} catch (Throwable $exception) {
    $genres = [];
}

$genreLabels = array_map(static fn(array $genreRow): string => (string) $genreRow['genre_label'], $genres);

if (strcasecmp($selectedGenre, 'All') !== 0 && !in_array($selectedGenre, $genreLabels, true)) {
    $selectedGenre = 'All';
}

try {
    $catalogBooks = db_call_all($conn, 'CALL sp_get_shop_catalog(?)', 's', [$selectedGenre]);
} catch (Throwable $exception) {
    $catalogBooks = [];
    if ($feedback === null) {
        $feedback = 'We could not load the shop catalog right now.';
        $feedbackType = 'danger';
    }
}

$catalogBooks = array_values(array_filter(
    $catalogBooks,
    static function (array $book) use ($searchTerm): bool {
        if ($searchTerm === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            (string) ($book['title'] ?? ''),
            (string) ($book['author'] ?? ''),
            (string) ($book['genre_label'] ?? ''),
            (string) ($book['description'] ?? ''),
        ]));

        return str_contains($haystack, strtolower($searchTerm));
    }
));

usort(
    $catalogBooks,
    static function (array $left, array $right) use ($selectedSort): int {
        $titleCompare = strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));

        return match ($selectedSort) {
            'newest' => strcmp(
                (string) ($right['published_date'] ?: '0000-00-00'),
                (string) ($left['published_date'] ?: '0000-00-00')
            ),
            'oldest' => strcmp(
                (string) ($left['published_date'] ?: '9999-12-31'),
                (string) ($right['published_date'] ?: '9999-12-31')
            ),
            'price_low' => ((float) ($left['price'] ?? 0) <=> (float) ($right['price'] ?? 0)) ?: $titleCompare,
            'price_high' => ((float) ($right['price'] ?? 0) <=> (float) ($left['price'] ?? 0)) ?: $titleCompare,
            'popular' => ((int) ($right['total_sold'] ?? 0) <=> (int) ($left['total_sold'] ?? 0)) ?: $titleCompare,
            'title' => $titleCompare,
            default => (((int) ($right['total_sold'] ?? 0)) + ((int) ($right['active_loans'] ?? 0)))
                <=> (((int) ($left['total_sold'] ?? 0)) + ((int) ($left['active_loans'] ?? 0)))
                ?: strcmp(
                    (string) ($right['published_date'] ?: '0000-00-00'),
                    (string) ($left['published_date'] ?: '0000-00-00')
                ),
        };
    }
);

$recentReleaseCount = count(array_filter(
    $catalogBooks,
    static function (array $book): bool {
        if (empty($book['published_date'])) {
            return false;
        }

        return (int) substr((string) $book['published_date'], 0, 4) >= ((int) date('Y') - 3);
    }
));
$datedCatalogCount = count(array_filter(
    $catalogBooks,
    static fn(array $book): bool => !empty($book['published_date'])
));

$cartIds = array_values(array_unique(array_merge(array_keys($cart['buy']), array_keys($cart['borrow']))));
$cartBookMap = [];

if ($cartIds !== []) {
    $cartIdList = implode(',', array_map('intval', $cartIds));

    try {
        $cartDetails = db_fetch_all(
            $conn,
            "SELECT
                book_id,
                title,
                author,
                genre_label,
                cover_image,
                price,
                stock_quantity
             FROM vw_book_catalog_metrics
             WHERE book_id IN ({$cartIdList})"
        );

        foreach ($cartDetails as $bookRow) {
            $cartBookMap[(int) $bookRow['book_id']] = $bookRow;
        }

        $cartChanged = false;

        foreach (['buy', 'borrow'] as $mode) {
            foreach ($cart[$mode] as $bookId => $quantity) {
                if (!isset($cartBookMap[(int) $bookId])) {
                    unset($cart[$mode][(int) $bookId]);
                    $cartChanged = true;
                }
            }
        }

        if ($cartChanged) {
            save_shop_cart($cart);
        }
    } catch (Throwable $exception) {
        $cartBookMap = [];
    }
}

$buyCount = shop_item_count($cart['buy']);
$borrowCount = shop_item_count($cart['borrow']);
$buyTotal = 0.0;

foreach ($cart['buy'] as $bookId => $quantity) {
    if (isset($cartBookMap[(int) $bookId])) {
        $buyTotal += ((float) $cartBookMap[(int) $bookId]['price']) * (int) $quantity;
    }
}

require_once __DIR__ . '/header.php';
?>
<main>
    <section class="shop-hero">
        <div class="container-fluid px-4">
            <div class="shop-hero-panel">
                <div>
                    <span class="shop-kicker">Reader storefront</span>
                    <h1>Browse by genre, then decide whether to buy or borrow.</h1>
                    <p class="shop-lead mb-0">Your cart stays in view while you explore, so it is easy to compare shelves, mix checkout modes, and move from discovery to action.</p>
                </div>
                <div class="shop-hero-stats">
                    <div class="shop-stat-card">
                        <span>Available genres</span>
                        <strong><?= number_format(count($genres)) ?></strong>
                    </div>
                    <div class="shop-stat-card">
                        <span>Recent releases</span>
                        <strong><?= number_format($recentReleaseCount) ?></strong>
                    </div>
                    <div class="shop-stat-card">
                        <span>Buy cart</span>
                        <strong><?= number_format($buyCount) ?></strong>
                    </div>
                    <div class="shop-stat-card">
                        <span>Dated titles</span>
                        <strong><?= number_format($datedCatalogCount) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="shop-rail-wrap">
        <div class="container-fluid px-4">
            <div class="shop-rail">
                <div class="shop-rail-section">
                    <span class="shop-rail-label">Genres</span>
                    <div class="shop-genre-chips">
                        <a class="shop-chip<?= strcasecmp($selectedGenre, 'All') === 0 ? ' is-active' : '' ?>" href="<?= htmlspecialchars(shop_url('All', '', $searchTerm, $selectedSort)) ?>">All</a>
                        <?php foreach ($genres as $genre): ?>
                            <a
                                class="shop-chip<?= $selectedGenre === $genre['genre_label'] ? ' is-active' : '' ?>"
                                href="<?= htmlspecialchars(shop_url((string) $genre['genre_label'], '', $searchTerm, $selectedSort)) ?>"
                            >
                                <?= htmlspecialchars((string) $genre['genre_label']) ?>
                                <span><?= number_format((int) $genre['total_books']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="shop-rail-section shop-rail-cart">
                    <div class="shop-mini-stat">
                        <span>Buy list</span>
                        <strong><?= number_format($buyCount) ?> book<?= $buyCount === 1 ? '' : 's' ?></strong>
                    </div>
                    <div class="shop-mini-stat">
                        <span>Borrow list</span>
                        <strong><?= number_format($borrowCount) ?> book<?= $borrowCount === 1 ? '' : 's' ?></strong>
                    </div>
                    <div class="shop-mini-stat">
                        <span>Buy total</span>
                        <strong>$<?= number_format($buyTotal, 2) ?></strong>
                    </div>
                    <a class="btn btn-primary btn-sm" href="#shop-cart">Open Cart</a>
                </div>
                <div class="shop-rail-section shop-rail-tools">
                    <span class="shop-rail-label">Catalog tools</span>
                    <form method="get" class="shop-tools-form">
                        <?php if (strcasecmp($selectedGenre, 'All') !== 0): ?>
                            <input type="hidden" name="genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                        <?php endif; ?>
                        <input
                            type="search"
                            class="form-control"
                            name="search"
                            value="<?= htmlspecialchars($searchTerm) ?>"
                            placeholder="Search title, author, or genre"
                        >
                        <select class="form-select" name="sort" aria-label="Sort books">
                            <option value="featured" <?= $selectedSort === 'featured' ? 'selected' : '' ?>>Featured mix</option>
                            <option value="newest" <?= $selectedSort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                            <option value="oldest" <?= $selectedSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                            <option value="price_low" <?= $selectedSort === 'price_low' ? 'selected' : '' ?>>Lowest price</option>
                            <option value="price_high" <?= $selectedSort === 'price_high' ? 'selected' : '' ?>>Highest price</option>
                            <option value="popular" <?= $selectedSort === 'popular' ? 'selected' : '' ?>>Most popular</option>
                            <option value="title" <?= $selectedSort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button>
                        <a class="btn btn-link btn-sm shop-tools-reset" href="<?= htmlspecialchars(shop_url($selectedGenre)) ?>">Reset</a>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="shop-cart-shell" id="shop-cart">
        <div class="container-fluid px-4">
            <?php if ($feedback !== null): ?>
                <div class="alert alert-<?= htmlspecialchars($feedbackType) ?>" role="alert">
                    <?= htmlspecialchars($feedback) ?>
                </div>
            <?php endif; ?>

            <div class="shop-cart-panel">
                <div class="shop-cart-header">
                    <div>
                        <span class="shop-kicker">Your cart</span>
                        <h2 class="mb-1">Buy now or borrow for two weeks</h2>
                        <p class="text-muted mb-0">Borrowed books are recorded with a 14-day due date. Purchased books create order records for the admin panel.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post">
                            <input type="hidden" name="action" value="clear_cart">
                            <input type="hidden" name="return_genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($selectedSort) ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" <?= $buyCount + $borrowCount === 0 ? 'disabled' : '' ?>>Clear Cart</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="return_genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                            <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($selectedSort) ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?= $buyCount + $borrowCount === 0 ? 'disabled' : '' ?>>Checkout</button>
                        </form>
                    </div>
                </div>

                <div class="shop-cart-columns">
                    <div class="shop-cart-column">
                        <h3>Buy Cart</h3>
                        <?php if ($cart['buy'] !== []): ?>
                            <?php foreach ($cart['buy'] as $bookId => $quantity): ?>
                                <?php if (!isset($cartBookMap[(int) $bookId])) { continue; } ?>
                                <?php $book = $cartBookMap[(int) $bookId]; ?>
                                <div class="shop-cart-item">
                                    <div>
                                        <strong><?= htmlspecialchars((string) $book['title']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars((string) $book['author']) ?> - <?= htmlspecialchars((string) $book['genre_label']) ?></div>
                                        <div class="text-muted small">Qty: <?= (int) $quantity ?> - $<?= number_format((float) $book['price'], 2) ?> each</div>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="cart_mode" value="buy">
                                        <input type="hidden" name="book_id" value="<?= (int) $bookId ?>">
                                        <input type="hidden" name="return_genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchTerm) ?>">
                                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($selectedSort) ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">Your buy cart is empty.</p>
                        <?php endif; ?>
                    </div>

                    <div class="shop-cart-column">
                        <h3>Borrow Cart</h3>
                        <?php if ($cart['borrow'] !== []): ?>
                            <?php foreach ($cart['borrow'] as $bookId => $quantity): ?>
                                <?php if (!isset($cartBookMap[(int) $bookId])) { continue; } ?>
                                <?php $book = $cartBookMap[(int) $bookId]; ?>
                                <div class="shop-cart-item">
                                    <div>
                                        <strong><?= htmlspecialchars((string) $book['title']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars((string) $book['author']) ?> - <?= htmlspecialchars((string) $book['genre_label']) ?></div>
                                        <div class="text-muted small">Qty: <?= (int) $quantity ?> - Due in 14 days after checkout</div>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="cart_mode" value="borrow">
                                        <input type="hidden" name="book_id" value="<?= (int) $bookId ?>">
                                        <input type="hidden" name="return_genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchTerm) ?>">
                                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($selectedSort) ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">Your borrow cart is empty.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="shop-catalog">
        <div class="container-fluid px-4">
            <div class="shop-section-head">
                <div>
                    <span class="shop-kicker">Catalog</span>
                    <h2><?= $selectedGenre === 'All' ? 'Browse every shelf' : htmlspecialchars($selectedGenre . ' shelf') ?></h2>
                    <p class="text-muted mb-0">
                        <?= number_format(count($catalogBooks)) ?> title<?= count($catalogBooks) === 1 ? '' : 's' ?> available in this view.
                        <?= $searchTerm !== '' ? ' Search: "' . htmlspecialchars($searchTerm) . '".' : '' ?>
                    </p>
                </div>
            </div>

            <div class="shop-book-grid">
                <?php if ($catalogBooks !== []): ?>
                    <?php foreach ($catalogBooks as $book): ?>
                        <?php
                        $bookId = (int) $book['book_id'];
                        $coverText = strtoupper(substr((string) $book['title'], 0, 1));
                        $inCart = shop_total_requested($cart, $bookId);
                        $stockRemaining = max(0, (int) $book['stock_quantity'] - $inCart);
                        $releaseBadge = shop_release_label($book['published_date'] !== null ? (string) $book['published_date'] : null);
                        ?>
                        <article class="shop-book-card">
                            <div class="shop-book-cover-wrap">
                                <?php if ($book['cover_image'] !== ''): ?>
                                    <img class="shop-book-cover" src="<?= htmlspecialchars((string) $book['cover_image']) ?>" alt="<?= htmlspecialchars((string) $book['title']) ?>">
                                <?php else: ?>
                                    <div class="shop-book-cover shop-book-cover-fallback"><?= htmlspecialchars($coverText) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="shop-book-copy">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="shop-chip is-static"><?= htmlspecialchars((string) $book['genre_label']) ?></span>
                                    <?php if ((int) $book['available_for_purchase'] === 1): ?>
                                        <span class="shop-availability is-buy">Buy</span>
                                    <?php endif; ?>
                                    <?php if ((int) $book['available_for_borrow'] === 1): ?>
                                        <span class="shop-availability is-borrow">Borrow</span>
                                    <?php endif; ?>
                                    <?php if ($releaseBadge !== null): ?>
                                        <span class="shop-availability <?= htmlspecialchars($releaseBadge['class']) ?>"><?= htmlspecialchars($releaseBadge['label']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($stockRemaining > 0 && $stockRemaining <= 3): ?>
                                        <span class="shop-availability is-low-stock">Low stock</span>
                                    <?php endif; ?>
                                </div>
                                <h3><?= htmlspecialchars((string) $book['title']) ?></h3>
                                <p class="shop-book-author"><?= htmlspecialchars((string) $book['author']) ?></p>
                                <p class="shop-book-description"><?= htmlspecialchars((string) ($book['description'] !== '' ? $book['description'] : 'No description has been added for this title yet.')) ?></p>
                            </div>
                            <div class="shop-book-meta">
                                <div>
                                    <span>Price</span>
                                    <strong>$<?= number_format((float) $book['price'], 2) ?></strong>
                                </div>
                                <div>
                                    <span>In stock</span>
                                    <strong><?= number_format((int) $book['stock_quantity']) ?></strong>
                                </div>
                                <div>
                                    <span>Sold</span>
                                    <strong><?= number_format((int) $book['total_sold']) ?></strong>
                                </div>
                                <div>
                                    <span>Active loans</span>
                                    <strong><?= number_format((int) $book['active_loans']) ?></strong>
                                </div>
                            </div>
                            <form method="post" class="shop-card-actions">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                                <input type="hidden" name="return_genre" value="<?= htmlspecialchars($selectedGenre) ?>">
                                <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchTerm) ?>">
                                <input type="hidden" name="return_sort" value="<?= htmlspecialchars($selectedSort) ?>">
                                <label class="form-label mb-0" for="quantity-<?= $bookId ?>">Qty</label>
                                <input
                                    id="quantity-<?= $bookId ?>"
                                    type="number"
                                    class="form-control"
                                    name="quantity"
                                    min="1"
                                    max="<?= max(1, $stockRemaining) ?>"
                                    value="1"
                                    <?= $stockRemaining === 0 ? 'disabled' : '' ?>
                                >
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm"
                                    name="cart_mode"
                                    value="buy"
                                    <?= (int) $book['available_for_purchase'] !== 1 || $stockRemaining === 0 ? 'disabled' : '' ?>
                                >
                                    Add to Buy
                                </button>
                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary btn-sm"
                                    name="cart_mode"
                                    value="borrow"
                                    <?= (int) $book['available_for_borrow'] !== 1 || $stockRemaining === 0 ? 'disabled' : '' ?>
                                >
                                    Add to Borrow
                                </button>
                            </form>
                            <div class="shop-card-footnote">
                                <?php if ($stockRemaining === 0): ?>
                                    This title is fully allocated in stock right now.
                                <?php elseif ($inCart > 0): ?>
                                    <?= number_format($inCart) ?> copy<?= $inCart === 1 ? '' : 'ies' ?> already in your cart.
                                <?php else: ?>
                                    Published: <?= htmlspecialchars(shop_format_date($book['published_date'] !== null ? (string) $book['published_date'] : null)) ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="shop-empty-state">
                        <h3>No books in this genre yet</h3>
                        <p class="mb-0"><?= $searchTerm !== '' ? 'Try a different search or reset the catalog tools to widen the shelf.' : 'Try another shelf or ask staff to add more titles to the catalog.' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
