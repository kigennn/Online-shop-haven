CREATE DATABASE IF NOT EXISTS bookstore;

USE bookstore;

-- Core identity data for shoppers, staff, and admins.
CREATE TABLE IF NOT EXISTS users (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    phone_number VARCHAR(30) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    pwd TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_delete TINYINT(1) DEFAULT 0,
    PRIMARY KEY (user_id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
);

CREATE TABLE IF NOT EXISTS addresses (
    address_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    post_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_delete TINYINT(1) DEFAULT 0,
    PRIMARY KEY (address_id)
);

-- Catalog records support both buying and borrowing from the same storefront.
CREATE TABLE IF NOT EXISTS books (
    book_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    available_for_purchase TINYINT(1) NOT NULL DEFAULT 1,
    available_for_borrow TINYINT(1) NOT NULL DEFAULT 1,
    published_date DATE DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_delete TINYINT(1) DEFAULT 0,
    PRIMARY KEY (book_id)
);

CREATE TABLE IF NOT EXISTS orders (
    order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    order_status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_delete TINYINT(1) DEFAULT 0,
    PRIMARY KEY (order_id)
);

CREATE TABLE IF NOT EXISTS orderitems (
    order_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT DEFAULT NULL,
    book_id INT DEFAULT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_item_id)
);

CREATE TABLE IF NOT EXISTS reviews (
    review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    book_id INT DEFAULT NULL,
    rating INT DEFAULT NULL,
    review_text TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_delete TINYINT(1) DEFAULT 0,
    PRIMARY KEY (review_id),
    CHECK (rating >= 1 AND rating <= 5)
);

CREATE TABLE IF NOT EXISTS borrowings (
    borrowing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    book_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    borrowed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATE DEFAULT NULL,
    returned_at DATE DEFAULT NULL,
    status ENUM('borrowed', 'returned', 'overdue') NOT NULL DEFAULT 'borrowed',
    notes VARCHAR(255) DEFAULT NULL,
    is_delete TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (borrowing_id),
    KEY idx_borrowings_user (user_id),
    KEY idx_borrowings_book (book_id),
    KEY idx_borrowings_status (status)
);

INSERT INTO users (username, email, role, pwd)
SELECT 'john_doe', 'john@example.com', 'admin', 'password123'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'john@example.com'
);

INSERT INTO users (username, email, role, pwd)
SELECT 'staff_member', 'staff@example.com', 'staff', 'password123'
WHERE NOT EXISTS (
    SELECT 1 FROM  users WHERE email = 'staff@example.com'
);

INSERT INTO users (username, email, role, pwd)
SELECT 'reader_one', 'reader1@example.com', 'user', 'password123'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'reader1@example.com'
);

INSERT INTO users (username, email, role, pwd)
SELECT 'reader_two', 'reader2@example.com', 'user', 'password123'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'reader2@example.com'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'The Great Gatsby', 'F. Scott Fitzgerald', 'Classic', 'A portrait of ambition, class, and longing in the Jazz Age.', NULL, 10.99, 100, 1, 1, '1925-04-10'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'The Great Gatsby'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'A Court of Thorns and Roses', 'Sarah J. Maas', 'Fantasy', 'A romantic fantasy filled with danger, magic, and court politics.', NULL, 18.50, 90, 1, 1, '2015-05-05'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'A Court of Thorns and Roses'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'The Silent Patient', 'Alex Michaelides', 'Fiction', 'A psychological story built around mystery, obsession, and hidden truths.', NULL, 15.25, 75, 1, 1, '2019-02-05'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'The Silent Patient'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'Smith and Hogan''s Criminal Law', 'David Ormerod', 'Criminal Law', 'A structured legal reference widely used for criminal law study.', NULL, 34.00, 45, 1, 1, '2023-08-01'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'Smith and Hogan''s Criminal Law'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'The Midnight Library', 'Matt Haig', 'Fiction', 'A reflective novel about regret, hope, and the lives we imagine.', NULL, 14.75, 85, 1, 1, '2020-08-13'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'The Midnight Library'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'Dune', 'Frank Herbert', 'Science Fiction', 'An epic political and ecological saga set on the desert world of Arrakis.', NULL, 16.40, 70, 1, 1, '1965-08-01'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'Dune'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'Atomic Habits', 'James Clear', 'Self-Help', 'A practical guide to building systems, habits, and steady personal improvement.', NULL, 19.25, 60, 1, 0, '2018-10-16'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'Atomic Habits'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'Sapiens', 'Yuval Noah Harari', 'History', 'A sweeping history of humankind from early societies to modern systems.', NULL, 21.10, 55, 1, 1, '2011-01-01'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'Sapiens'
);

INSERT INTO books (title, author, genre, description, cover_image, price, stock_quantity, available_for_purchase, available_for_borrow, published_date)
SELECT 'The Song of Achilles', 'Madeline Miller', 'Romance', 'A lyrical retelling of the bond between Achilles and Patroclus.', NULL, 17.80, 65, 1, 1, '2011-09-20'
WHERE NOT EXISTS (
    SELECT 1 FROM books WHERE title = 'The Song of Achilles'
);

INSERT INTO orders (user_id, total_price, order_status)
SELECT u.user_id, 25.74, 'Completed'
FROM users u
WHERE u.email = 'reader1@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM orders o
      WHERE o.user_id = u.user_id
        AND o.total_price = 25.74
        AND o.order_status = 'Completed'
  );

INSERT INTO orderitems (order_id, book_id, quantity, price)
SELECT o.order_id, b.book_id, 1, 10.99
FROM orders o
INNER JOIN users u ON u.user_id = o.user_id AND u.email = 'reader1@example.com'
INNER JOIN books b ON b.title = 'The Great Gatsby'
WHERE o.total_price = 25.74
  AND NOT EXISTS (
      SELECT 1
      FROM orderitems oi
      WHERE oi.order_id = o.order_id
        AND oi.book_id = b.book_id
  );

INSERT INTO orderitems (order_id, book_id, quantity, price)
SELECT o.order_id, b.book_id, 1, 14.75
FROM orders o
INNER JOIN users u ON u.user_id = o.user_id AND u.email = 'reader1@example.com'
INNER JOIN books b ON b.title = 'The Midnight Library'
WHERE o.total_price = 25.74
  AND NOT EXISTS (
      SELECT 1
      FROM orderitems oi
      WHERE oi.order_id = o.order_id
        AND oi.book_id = b.book_id
  );

INSERT INTO borrowings (user_id, book_id, quantity, due_date, status, notes)
SELECT u.user_id, b.book_id, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'borrowed', 'Seed borrowing record'
FROM users u
INNER JOIN books b ON b.title = 'The Silent Patient'
WHERE u.email = 'reader2@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM borrowings br
      WHERE br.user_id = u.user_id
        AND br.book_id = b.book_id
        AND br.notes = 'Seed borrowing record'
  );

CREATE OR REPLACE VIEW vw_book_catalog_metrics AS
SELECT
    b.book_id,
    b.title,
    b.author,
    COALESCE(NULLIF(b.genre, ''), 'General') AS genre_label,
    COALESCE(b.genre, '') AS genre,
    COALESCE(b.description, '') AS description,
    COALESCE(b.cover_image, '') AS cover_image,
    b.price,
    b.stock_quantity,
    b.available_for_purchase,
    b.available_for_borrow,
    b.published_date,
    b.created_at,
    COALESCE(sales.total_sold, 0) AS total_sold,
    COALESCE(loans.active_loans, 0) AS active_loans
FROM books b
LEFT JOIN (
    SELECT oi.book_id, COALESCE(SUM(oi.quantity), 0) AS total_sold
    FROM orders o
    INNER JOIN orderitems oi ON oi.order_id = o.order_id
    WHERE o.is_delete = 0
    GROUP BY oi.book_id
) sales ON sales.book_id = b.book_id
LEFT JOIN (
    SELECT
        br.book_id,
        COALESCE(SUM(CASE WHEN br.status IN ('borrowed', 'overdue') THEN br.quantity ELSE 0 END), 0) AS active_loans
    FROM borrowings br
    WHERE br.is_delete = 0
    GROUP BY br.book_id
) loans ON loans.book_id = b.book_id
WHERE b.is_delete = 0;

CREATE OR REPLACE VIEW vw_user_account_overview AS
SELECT
    u.user_id,
    u.username,
    u.email,
    u.role,
    COALESCE(u.phone_number, '') AS phone_number,
    COALESCE(u.profile_image, '') AS profile_image,
    COALESCE(u.bio, '') AS bio,
    u.created_at,
    COALESCE(addr.city, '') AS city,
    COALESCE(addr.country, '') AS country,
    COALESCE(addr.address_line1, '') AS address_line1,
    COALESCE(addr.address_line2, '') AS address_line2,
    COALESCE(addr.post_code, '') AS post_code,
    COALESCE(purchase_data.purchase_orders, 0) AS purchase_orders,
    COALESCE(purchase_data.books_bought, 0) AS books_bought,
    COALESCE(purchase_data.amount_spent, 0) AS amount_spent,
    COALESCE(borrow_data.borrow_records, 0) AS borrow_records,
    COALESCE(borrow_data.borrowed_total, 0) AS borrowed_total,
    COALESCE(borrow_data.active_loans, 0) AS active_loans
FROM users u
LEFT JOIN (
    SELECT a.user_id, a.city, a.country, a.address_line1, a.address_line2, a.post_code
    FROM addresses a
    INNER JOIN (
        SELECT user_id, MAX(address_id) AS latest_address_id
        FROM addresses
        WHERE is_delete = 0
        GROUP BY user_id
    ) latest_addr ON latest_addr.latest_address_id = a.address_id
) addr ON addr.user_id = u.user_id
LEFT JOIN (
    SELECT
        o.user_id,
        COUNT(DISTINCT o.order_id) AS purchase_orders,
        COALESCE(SUM(oi.quantity), 0) AS books_bought,
        COALESCE(SUM(oi.quantity * oi.price), 0) AS amount_spent
    FROM orders o
    LEFT JOIN orderitems oi ON oi.order_id = o.order_id
    WHERE o.is_delete = 0
    GROUP BY o.user_id
) purchase_data ON purchase_data.user_id = u.user_id
LEFT JOIN (
    SELECT
        br.user_id,
        COUNT(*) AS borrow_records,
        COALESCE(SUM(br.quantity), 0) AS borrowed_total,
        COALESCE(SUM(CASE WHEN br.status IN ('borrowed', 'overdue') THEN br.quantity ELSE 0 END), 0) AS active_loans
    FROM borrowings br
    WHERE br.is_delete = 0
    GROUP BY br.user_id
) borrow_data ON borrow_data.user_id = u.user_id
WHERE u.is_delete = 0;

DROP FUNCTION IF EXISTS fn_user_total_spent;
DROP FUNCTION IF EXISTS fn_user_active_loans;
DROP FUNCTION IF EXISTS fn_user_total_books_borrowed;
DROP PROCEDURE IF EXISTS sp_get_shop_genres;
DROP PROCEDURE IF EXISTS sp_get_shop_catalog;
DROP PROCEDURE IF EXISTS sp_get_account_directory;
DROP PROCEDURE IF EXISTS sp_get_admin_dashboard_totals;
DROP PROCEDURE IF EXISTS sp_get_book_inventory;
DROP PROCEDURE IF EXISTS sp_get_catalog_summary;
DROP PROCEDURE IF EXISTS sp_get_profile_dashboard;
DROP PROCEDURE IF EXISTS sp_get_profile_recent_purchases;
DROP PROCEDURE IF EXISTS sp_get_profile_recent_borrowings;
DROP PROCEDURE IF EXISTS sp_process_checkout;

DELIMITER $$

CREATE FUNCTION fn_user_total_spent(p_user_id BIGINT UNSIGNED)
RETURNS DECIMAL(12,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(12,2) DEFAULT 0.00;
    SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
    INTO v_total
    FROM orders o
    LEFT JOIN orderitems oi ON oi.order_id = o.order_id
    WHERE o.user_id = p_user_id AND o.is_delete = 0;
    RETURN v_total;
END$$

CREATE FUNCTION fn_user_active_loans(p_user_id BIGINT UNSIGNED)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    SELECT COALESCE(SUM(quantity), 0)
    INTO v_total
    FROM borrowings
    WHERE user_id = p_user_id
      AND is_delete = 0
      AND status IN ('borrowed', 'overdue');
    RETURN v_total;
END$$

CREATE FUNCTION fn_user_total_books_borrowed(p_user_id BIGINT UNSIGNED)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    SELECT COALESCE(SUM(quantity), 0)
    INTO v_total
    FROM borrowings
    WHERE user_id = p_user_id
      AND is_delete = 0;
    RETURN v_total;
END$$

CREATE PROCEDURE sp_get_shop_genres()
BEGIN
    SELECT
        genre_label,
        COUNT(*) AS total_books
    FROM vw_book_catalog_metrics
    GROUP BY genre_label
    ORDER BY genre_label ASC;
END$$

CREATE PROCEDURE sp_get_shop_catalog(IN p_genre VARCHAR(100))
BEGIN
    IF p_genre IS NULL OR p_genre = '' OR LOWER(p_genre) = 'all' THEN
        SELECT *
        FROM vw_book_catalog_metrics
        ORDER BY genre_label ASC, title ASC;
    ELSE
        SELECT *
        FROM vw_book_catalog_metrics
        WHERE genre_label = p_genre
        ORDER BY title ASC;
    END IF;
END$$

CREATE PROCEDURE sp_get_account_directory(IN p_viewer_role VARCHAR(20))
BEGIN
    IF LOWER(COALESCE(p_viewer_role, '')) = 'admin' THEN
        SELECT *
        FROM vw_user_account_overview
        ORDER BY FIELD(role, 'admin', 'staff', 'user'), user_id DESC;
    ELSE
        SELECT *
        FROM vw_user_account_overview
        WHERE role = 'user'
        ORDER BY user_id DESC;
    END IF;
END$$

CREATE PROCEDURE sp_get_admin_dashboard_totals(IN p_viewer_role VARCHAR(20))
BEGIN
    SELECT
        CASE
            WHEN LOWER(COALESCE(p_viewer_role, '')) = 'admin'
                THEN (SELECT COUNT(*) FROM users WHERE is_delete = 0)
            ELSE (SELECT COUNT(*) FROM users WHERE is_delete = 0 AND role = 'user')
        END AS total_accounts,
        (SELECT COUNT(*) FROM users WHERE is_delete = 0 AND role = 'user') AS total_users,
        (SELECT COUNT(*) FROM users WHERE is_delete = 0 AND role = 'staff') AS total_staff,
        (SELECT COUNT(*) FROM users WHERE is_delete = 0 AND role = 'admin') AS total_admins,
        (SELECT COUNT(*) FROM vw_book_catalog_metrics) AS total_books,
        (SELECT COALESCE(SUM(stock_quantity), 0) FROM vw_book_catalog_metrics) AS total_stock,
        (SELECT COUNT(*) FROM vw_book_catalog_metrics WHERE available_for_purchase = 1) AS purchase_ready,
        (SELECT COUNT(*) FROM vw_book_catalog_metrics WHERE available_for_borrow = 1) AS borrow_ready,
        (SELECT COUNT(DISTINCT o.order_id) FROM orders o WHERE o.is_delete = 0) AS total_orders,
        (SELECT COALESCE(SUM(oi.quantity), 0)
         FROM orders o
         LEFT JOIN orderitems oi ON oi.order_id = o.order_id
         WHERE o.is_delete = 0) AS total_books_sold,
        (SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
         FROM orders o
         LEFT JOIN orderitems oi ON oi.order_id = o.order_id
         WHERE o.is_delete = 0) AS total_sales,
        (SELECT COUNT(*) FROM borrowings WHERE is_delete = 0) AS total_borrow_records,
        (SELECT COALESCE(SUM(CASE WHEN status IN ('borrowed', 'overdue') THEN quantity ELSE 0 END), 0)
         FROM borrowings
         WHERE is_delete = 0) AS active_loans;
END$$

CREATE PROCEDURE sp_get_book_inventory()
BEGIN
    SELECT *
    FROM vw_book_catalog_metrics
    ORDER BY genre_label ASC, title ASC;
END$$

CREATE PROCEDURE sp_get_catalog_summary()
BEGIN
    SELECT
        COUNT(*) AS total_books,
        COALESCE(SUM(stock_quantity), 0) AS total_stock,
        COALESCE(SUM(CASE WHEN available_for_purchase = 1 THEN 1 ELSE 0 END), 0) AS purchase_ready,
        COALESCE(SUM(CASE WHEN available_for_borrow = 1 THEN 1 ELSE 0 END), 0) AS borrow_ready
    FROM vw_book_catalog_metrics;
END$$

CREATE PROCEDURE sp_get_profile_dashboard(IN p_user_id BIGINT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.username,
        u.email,
        u.role,
        COALESCE(u.phone_number, '') AS phone_number,
        COALESCE(u.profile_image, '') AS profile_image,
        COALESCE(u.bio, '') AS bio,
        u.created_at,
        COALESCE(addr.city, '') AS city,
        COALESCE(addr.address_line1, '') AS address_line1,
        COALESCE(addr.address_line2, '') AS address_line2,
        COALESCE(addr.post_code, '') AS post_code,
        COALESCE(addr.country, '') AS country,
        COALESCE((SELECT COUNT(DISTINCT o.order_id)
                  FROM orders o
                  WHERE o.user_id = u.user_id AND o.is_delete = 0), 0) AS total_orders,
        COALESCE((SELECT SUM(oi.quantity)
                  FROM orders o
                  LEFT JOIN orderitems oi ON oi.order_id = o.order_id
                  WHERE o.user_id = u.user_id AND o.is_delete = 0), 0) AS books_bought,
        fn_user_total_spent(u.user_id) AS total_spent,
        COALESCE((SELECT COUNT(*)
                  FROM borrowings br
                  WHERE br.user_id = u.user_id AND br.is_delete = 0), 0) AS borrow_records,
        fn_user_total_books_borrowed(u.user_id) AS books_borrowed,
        fn_user_active_loans(u.user_id) AS active_loans
    FROM users u
    LEFT JOIN (
        SELECT a.user_id, a.city, a.address_line1, a.address_line2, a.post_code, a.country
        FROM addresses a
        INNER JOIN (
            SELECT user_id, MAX(address_id) AS latest_address_id
            FROM addresses
            WHERE is_delete = 0
            GROUP BY user_id
        ) latest_addr ON latest_addr.latest_address_id = a.address_id
    ) addr ON addr.user_id = u.user_id
    WHERE u.user_id = p_user_id
      AND u.is_delete = 0
    LIMIT 1;
END$$

CREATE PROCEDURE sp_get_profile_recent_purchases(IN p_user_id BIGINT UNSIGNED, IN p_limit INT)
BEGIN
    SELECT
        o.order_id,
        o.created_at AS order_date,
        oi.quantity,
        oi.price,
        COALESCE(b.title, 'Unknown book') AS title
    FROM orders o
    INNER JOIN orderitems oi ON oi.order_id = o.order_id
    LEFT JOIN books b ON b.book_id = oi.book_id
    WHERE o.user_id = p_user_id
      AND o.is_delete = 0
    ORDER BY o.created_at DESC, oi.order_item_id DESC
    LIMIT p_limit;
END$$

CREATE PROCEDURE sp_get_profile_recent_borrowings(IN p_user_id BIGINT UNSIGNED, IN p_limit INT)
BEGIN
    SELECT
        br.borrowing_id,
        br.quantity,
        br.borrowed_at,
        br.due_date,
        br.status,
        COALESCE(b.title, 'Unknown book') AS title
    FROM borrowings br
    LEFT JOIN books b ON b.book_id = br.book_id
    WHERE br.user_id = p_user_id
      AND br.is_delete = 0
    ORDER BY br.borrowed_at DESC, br.borrowing_id DESC
    LIMIT p_limit;
END$$

CREATE PROCEDURE sp_process_checkout(
    IN p_user_id BIGINT UNSIGNED,
    IN p_buy_items JSON,
    IN p_borrow_items JSON
)
BEGIN
    DECLARE v_buy_total DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_order_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_buy_count INT DEFAULT 0;
    DECLARE v_borrow_count INT DEFAULT 0;
    DECLARE v_all_count INT DEFAULT 0;
    DECLARE v_existing_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        DROP TEMPORARY TABLE IF EXISTS tmp_checkout_buy;
        DROP TEMPORARY TABLE IF EXISTS tmp_checkout_borrow;
        RESIGNAL;
    END;

    DROP TEMPORARY TABLE IF EXISTS tmp_checkout_buy;
    DROP TEMPORARY TABLE IF EXISTS tmp_checkout_borrow;

    CREATE TEMPORARY TABLE tmp_checkout_buy (
        book_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        quantity INT NOT NULL
    );

    CREATE TEMPORARY TABLE tmp_checkout_borrow (
        book_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        quantity INT NOT NULL
    );

    IF p_buy_items IS NOT NULL AND JSON_VALID(p_buy_items) AND JSON_LENGTH(p_buy_items) > 0 THEN
        INSERT INTO tmp_checkout_buy (book_id, quantity)
        SELECT CAST(j.book_id AS UNSIGNED), CAST(j.quantity AS UNSIGNED)
        FROM JSON_TABLE(
            p_buy_items,
            '$[*]' COLUMNS (
                book_id BIGINT PATH '$.book_id',
                quantity INT PATH '$.quantity'
            )
        ) AS j
        WHERE j.book_id IS NOT NULL AND j.quantity IS NOT NULL AND j.quantity > 0;
    END IF;

    IF p_borrow_items IS NOT NULL AND JSON_VALID(p_borrow_items) AND JSON_LENGTH(p_borrow_items) > 0 THEN
        INSERT INTO tmp_checkout_borrow (book_id, quantity)
        SELECT CAST(j.book_id AS UNSIGNED), CAST(j.quantity AS UNSIGNED)
        FROM JSON_TABLE(
            p_borrow_items,
            '$[*]' COLUMNS (
                book_id BIGINT PATH '$.book_id',
                quantity INT PATH '$.quantity'
            )
        ) AS j
        WHERE j.book_id IS NOT NULL AND j.quantity IS NOT NULL AND j.quantity > 0;
    END IF;

    SELECT COUNT(*) INTO v_buy_count FROM tmp_checkout_buy;
    SELECT COUNT(*) INTO v_borrow_count FROM tmp_checkout_borrow;

    IF v_buy_count = 0 AND v_borrow_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Your cart is empty.';
    END IF;

    SELECT COUNT(*)
    INTO v_all_count
    FROM (
        SELECT book_id
        FROM tmp_checkout_buy
        UNION
        SELECT book_id
        FROM tmp_checkout_borrow
    ) checkout_books;

    SELECT COUNT(*)
    INTO v_existing_count
    FROM books b
    INNER JOIN (
        SELECT
            book_id,
            SUM(quantity) AS quantity
        FROM (
            SELECT book_id, quantity FROM tmp_checkout_buy
            UNION ALL
            SELECT book_id, quantity FROM tmp_checkout_borrow
        ) checkout_items
        GROUP BY book_id
    ) t ON t.book_id = b.book_id
    WHERE b.is_delete = 0;

    IF v_all_count <> v_existing_count THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'One or more books in the cart are no longer available.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM tmp_checkout_buy t
        INNER JOIN books b ON b.book_id = t.book_id
        WHERE b.is_delete = 0
          AND b.available_for_purchase <> 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'One or more books in the buy cart are not available for purchase.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM tmp_checkout_borrow t
        INNER JOIN books b ON b.book_id = t.book_id
        WHERE b.is_delete = 0
          AND b.available_for_borrow <> 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'One or more books in the borrow cart are not available for borrowing.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM (
            SELECT
                book_id,
                SUM(quantity) AS quantity
            FROM (
                SELECT book_id, quantity FROM tmp_checkout_buy
                UNION ALL
                SELECT book_id, quantity FROM tmp_checkout_borrow
            ) checkout_items
            GROUP BY book_id
        ) t
        INNER JOIN books b ON b.book_id = t.book_id
        WHERE b.is_delete = 0
          AND b.stock_quantity < t.quantity
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'One or more books no longer have enough stock for this checkout.';
    END IF;

    START TRANSACTION;

    UPDATE books b
    INNER JOIN (
        SELECT
            book_id,
            SUM(quantity) AS quantity
        FROM (
            SELECT book_id, quantity FROM tmp_checkout_buy
            UNION ALL
            SELECT book_id, quantity FROM tmp_checkout_borrow
        ) checkout_items
        GROUP BY book_id
    ) t ON t.book_id = b.book_id
    SET b.stock_quantity = b.stock_quantity - t.quantity
    WHERE b.is_delete = 0;

    IF v_buy_count > 0 THEN
        SELECT COALESCE(SUM(t.quantity * b.price), 0)
        INTO v_buy_total
        FROM tmp_checkout_buy t
        INNER JOIN books b ON b.book_id = t.book_id;

        INSERT INTO orders (user_id, total_price, order_status)
        VALUES (p_user_id, v_buy_total, 'Completed');

        SET v_order_id = LAST_INSERT_ID();

        INSERT INTO orderitems (order_id, book_id, quantity, price)
        SELECT
            v_order_id,
            t.book_id,
            t.quantity,
            b.price
        FROM tmp_checkout_buy t
        INNER JOIN books b ON b.book_id = t.book_id;
    END IF;

    IF v_borrow_count > 0 THEN
        INSERT INTO borrowings (user_id, book_id, quantity, due_date, notes, status)
        SELECT
            p_user_id,
            t.book_id,
            t.quantity,
            DATE_ADD(CURDATE(), INTERVAL 14 DAY),
            'Borrowed through the storefront cart.',
            'borrowed'
        FROM tmp_checkout_borrow t;
    END IF;

    COMMIT;

    SELECT
        COALESCE(v_order_id, 0) AS order_id,
        v_buy_total AS total_price,
        COALESCE((SELECT SUM(quantity) FROM tmp_checkout_buy), 0) AS buy_quantity,
        COALESCE((SELECT SUM(quantity) FROM tmp_checkout_borrow), 0) AS borrow_quantity;

    DROP TEMPORARY TABLE IF EXISTS tmp_checkout_buy;
    DROP TEMPORARY TABLE IF EXISTS tmp_checkout_borrow;
END$$

DELIMITER ;
