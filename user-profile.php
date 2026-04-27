<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

$pageTitle = 'My Profile';
$activeNav = 'profile';
$extraStyles = ['css/profile.css?v=20260427-1'];
$bodyClass = 'portal-shell profile-page';
$currentUser = require_login();

function profile_format_date(?string $value): string
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

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper($part[0]);
        }

        if (strlen($letters) >= 2) {
            break;
        }
    }

    if ($letters === '') {
        $letters = strtoupper(substr($name, 0, 2));
    }

    return $letters !== '' ? $letters : 'OB';
}

function profile_upload_relative_dir(): string
{
    return 'img/profile-photos';
}

function profile_upload_absolute_dir(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profile-photos';
}

function profile_is_managed_image(string $path): bool
{
    $normalizedPath = str_replace('\\', '/', $path);

    return str_starts_with($normalizedPath, profile_upload_relative_dir() . '/');
}

function profile_delete_managed_image(?string $path): void
{
    if ($path === null || $path === '' || !profile_is_managed_image($path)) {
        return;
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

// Image validation uses several detectors because browser uploads can expose different MIME signatures on Windows.
function profile_normalize_image_extension(string $extension): ?string
{
    return match (strtolower(trim($extension))) {
        'jpg', 'jpeg', 'jfif' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
        'gif' => 'gif',
        default => null,
    };
}

function profile_extension_from_mime(?string $mimeType): ?string
{
    return match (strtolower(trim((string) $mimeType))) {
        'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/jfif', 'image/pipeg' => 'jpg',
        'image/png', 'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => null,
    };
}

function profile_extension_from_image_type(int $imageType): ?string
{
    return match ($imageType) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
}

function profile_detect_upload_extension(array $uploadedFile): ?string
{
    $temporaryPath = (string) ($uploadedFile['tmp_name'] ?? '');

    if ($temporaryPath === '' || !is_file($temporaryPath)) {
        return null;
    }

    $imageWasConfirmed = false;

    if (function_exists('exif_imagetype')) {
        $imageType = @exif_imagetype($temporaryPath);

        if (is_int($imageType)) {
            $imageWasConfirmed = true;
            $extension = profile_extension_from_image_type($imageType);

            if ($extension !== null) {
                return $extension;
            }
        }
    }

    $imageInfo = @getimagesize($temporaryPath);

    if (is_array($imageInfo)) {
        $imageWasConfirmed = true;
        $extension = profile_extension_from_mime(isset($imageInfo['mime']) ? (string) $imageInfo['mime'] : null);

        if ($extension !== null) {
            return $extension;
        }

        if (isset($imageInfo[2]) && is_int($imageInfo[2])) {
            $extension = profile_extension_from_image_type($imageInfo[2]);

            if ($extension !== null) {
                return $extension;
            }
        }
    }

    if (class_exists('finfo')) {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = profile_extension_from_mime(is_string($mimeType) ? $mimeType : null);

        if ($extension !== null) {
            return $extension;
        }
    }

    if (function_exists('mime_content_type')) {
        $mimeType = @mime_content_type($temporaryPath);
        $extension = profile_extension_from_mime(is_string($mimeType) ? $mimeType : null);

        if ($extension !== null) {
            return $extension;
        }
    }

    if ($imageWasConfirmed) {
        return profile_normalize_image_extension((string) pathinfo((string) ($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
    }

    return null;
}

$message = null;
$hasError = false;

$profileStmt = $conn->prepare(
    'SELECT user_id, username, email, role, phone_number, profile_image, bio, pwd, created_at
     FROM users
     WHERE user_id = ? AND is_delete = 0
     LIMIT 1'
);
$profileStmt->bind_param('i', $currentUser['uid']);
$profileStmt->execute();
$profileUser = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();

if ($profileUser === null) {
    logout_user();
    header('Location: Lgin.php');
    exit;
}

$username = (string) $profileUser['username'];
$email = (string) $profileUser['email'];
$phoneNumber = (string) ($profileUser['phone_number'] ?? '');
$profileImage = (string) ($profileUser['profile_image'] ?? '');
$bio = (string) ($profileUser['bio'] ?? '');

try {
    $profileDashboard = db_call_one($conn, 'CALL sp_get_profile_dashboard(?)', 'i', [(int) $currentUser['uid']]) ?? [];
} catch (Throwable $exception) {
    $profileDashboard = [];
}

$city = (string) ($profileDashboard['city'] ?? '');
$addressLine1 = (string) ($profileDashboard['address_line1'] ?? '');
$addressLine2 = (string) ($profileDashboard['address_line2'] ?? '');
$postCode = (string) ($profileDashboard['post_code'] ?? '');
$country = (string) ($profileDashboard['country'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_user_details'])) {
        // Profile details and photo changes are saved together so the hero card refreshes in one pass.
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $removeProfileImage = isset($_POST['remove_profile_image']);
        $uploadedProfileImage = null;
        $nextProfileImage = $profileImage;
        $uploadedFile = $_FILES['profile_photo'] ?? null;

        if ($username === '' || $email === '') {
            $message = 'Username and email are required.';
            $hasError = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $hasError = true;
        } elseif ($phoneNumber !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phoneNumber)) {
            $message = 'Please enter a valid phone number.';
            $hasError = true;
        } elseif (mb_strlen($bio) > 500) {
            $message = 'Your bio should stay under 500 characters.';
            $hasError = true;
        } else {
            if ($uploadedFile !== null && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                    $message = 'We could not upload that picture right now.';
                    $hasError = true;
                } elseif ((int) ($uploadedFile['size'] ?? 0) > 4 * 1024 * 1024) {
                    $message = 'Profile pictures should be 4 MB or smaller.';
                    $hasError = true;
                } else {
                    $fileExtension = profile_detect_upload_extension($uploadedFile);

                    if ($fileExtension === null) {
                        $message = 'Please upload a JPG, PNG, WEBP, or GIF profile picture.';
                        $hasError = true;
                    } else {
                        $uploadDirectory = profile_upload_absolute_dir();

                        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0777, true) && !is_dir($uploadDirectory)) {
                            $message = 'We could not prepare profile photo storage right now.';
                            $hasError = true;
                        } else {
                            // Managed filenames let us replace and delete profile photos safely later on.
                            $fileName = 'user-' . $currentUser['uid'] . '-' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                            $targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;

                            if (!move_uploaded_file((string) $uploadedFile['tmp_name'], $targetPath)) {
                                $message = 'We could not save that picture right now.';
                                $hasError = true;
                            } else {
                                $uploadedProfileImage = profile_upload_relative_dir() . '/' . $fileName;
                                $nextProfileImage = $uploadedProfileImage;
                            }
                        }
                    }
                }
            }

            if ($removeProfileImage) {
                $nextProfileImage = '';
            }

            if (!$hasError) {
                $duplicateStmt = $conn->prepare(
                    'SELECT user_id
                     FROM users
                     WHERE (username = ? OR email = ?) AND user_id <> ? AND is_delete = 0
                     LIMIT 1'
                );
                $duplicateStmt->bind_param('ssi', $username, $email, $currentUser['uid']);
                $duplicateStmt->execute();
                $existingUser = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();

                if ($existingUser !== null) {
                    if ($uploadedProfileImage !== null) {
                        profile_delete_managed_image($uploadedProfileImage);
                    }

                    $message = 'Another account already uses that username or email.';
                    $hasError = true;
                } else {
                    $updateStmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, email = ?, phone_number = NULLIF(?, \'\'),
                             profile_image = NULLIF(?, \'\'), bio = NULLIF(?, \'\')
                         WHERE user_id = ?'
                    );
                    $updateStmt->bind_param('sssssi', $username, $email, $phoneNumber, $nextProfileImage, $bio, $currentUser['uid']);
                    $wasUpdated = $updateStmt->execute();
                    $updateStmt->close();

                    if ($wasUpdated) {
                        if ($uploadedProfileImage !== null && $profileImage !== '' && $profileImage !== $uploadedProfileImage) {
                            profile_delete_managed_image($profileImage);
                        } elseif ($removeProfileImage && $profileImage !== '' && $uploadedProfileImage === null) {
                            profile_delete_managed_image($profileImage);
                        }

                        $currentUser = refresh_user_session($conn, $currentUser['uid']) ?? $currentUser;

                        $profileStmt = $conn->prepare(
                            'SELECT user_id, username, email, role, phone_number, profile_image, bio, pwd, created_at
                             FROM users
                             WHERE user_id = ? AND is_delete = 0
                             LIMIT 1'
                        );
                        $profileStmt->bind_param('i', $currentUser['uid']);
                        $profileStmt->execute();
                        $profileUser = $profileStmt->get_result()->fetch_assoc() ?: $profileUser;
                        $profileStmt->close();

                        $username = (string) $profileUser['username'];
                        $email = (string) $profileUser['email'];
                        $phoneNumber = (string) ($profileUser['phone_number'] ?? '');
                        $profileImage = (string) ($profileUser['profile_image'] ?? '');
                        $bio = (string) ($profileUser['bio'] ?? '');
                        $message = $uploadedProfileImage !== null
                            ? 'Your personal profile and photo were updated successfully.'
                            : ($removeProfileImage ? 'Your personal profile was updated and the picture was removed.' : 'Your personal profile was updated successfully.');
                    } else {
                        if ($uploadedProfileImage !== null) {
                            profile_delete_managed_image($uploadedProfileImage);
                        }

                        $message = 'We could not save your profile details right now.';
                        $hasError = true;
                    }
                }
            }
        }
    } elseif (isset($_POST['submit_password_change'])) {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $message = 'Please fill in all password fields.';
            $hasError = true;
        } elseif (!passwords_match($currentPassword, (string) $profileUser['pwd'])) {
            $message = 'Your current password is incorrect.';
            $hasError = true;
        } elseif (strlen($newPassword) < 8) {
            $message = 'Your new password must be at least 8 characters long.';
            $hasError = true;
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'The new passwords do not match.';
            $hasError = true;
        } elseif (passwords_match($newPassword, (string) $profileUser['pwd'])) {
            $message = 'Please choose a password that is different from the current one.';
            $hasError = true;
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare('UPDATE users SET pwd = ? WHERE user_id = ?');
            $passwordStmt->bind_param('si', $hashedPassword, $currentUser['uid']);
            $wasUpdated = $passwordStmt->execute();
            $passwordStmt->close();

            if ($wasUpdated) {
                $profileUser['pwd'] = $hashedPassword;
                $message = 'Your password was updated successfully.';
            } else {
                $message = 'We could not update your password right now.';
                $hasError = true;
            }
        }
    } elseif (isset($_POST['submit_address_details'])) {
        $city = trim((string) ($_POST['city'] ?? ''));
        $addressLine1 = trim((string) ($_POST['address_line1'] ?? ''));
        $addressLine2 = trim((string) ($_POST['address_line2'] ?? ''));
        $postCode = trim((string) ($_POST['post_code'] ?? ''));
        $country = trim((string) ($_POST['country'] ?? ''));

        if ($city === '' || $addressLine1 === '' || $postCode === '' || $country === '') {
            $message = 'City, address line 1, postcode, and country are required.';
            $hasError = true;
        } else {
            $existingAddressStmt = $conn->prepare(
                'SELECT address_id
                 FROM addresses
                 WHERE user_id = ? AND is_delete = 0
                 ORDER BY address_id DESC
                 LIMIT 1'
            );
            $existingAddressStmt->bind_param('i', $currentUser['uid']);
            $existingAddressStmt->execute();
            $existingAddress = $existingAddressStmt->get_result()->fetch_assoc();
            $existingAddressStmt->close();

            if ($existingAddress !== null) {
                $addressId = (int) $existingAddress['address_id'];
                $saveAddressStmt = $conn->prepare(
                    'UPDATE addresses
                     SET city = ?, address_line1 = ?, address_line2 = NULLIF(?, \'\'),
                         post_code = ?, country = ?
                     WHERE address_id = ?'
                );
                $saveAddressStmt->bind_param('sssssi', $city, $addressLine1, $addressLine2, $postCode, $country, $addressId);
            } else {
                $saveAddressStmt = $conn->prepare(
                    'INSERT INTO addresses (user_id, city, address_line1, address_line2, post_code, country)
                     VALUES (?, ?, ?, NULLIF(?, \'\'), ?, ?)'
                );
                $saveAddressStmt->bind_param('isssss', $currentUser['uid'], $city, $addressLine1, $addressLine2, $postCode, $country);
            }

            $wasSaved = $saveAddressStmt->execute();
            $saveAddressStmt->close();

            if ($wasSaved) {
                $message = 'Your address details were updated successfully.';
            } else {
                $message = 'We could not save your address right now.';
                $hasError = true;
            }
        }
    }
}

$purchaseSummary = [
    'total_orders' => (int) ($profileDashboard['total_orders'] ?? 0),
    'books_bought' => (int) ($profileDashboard['books_bought'] ?? 0),
    'total_spent' => (float) ($profileDashboard['total_spent'] ?? 0),
];

$borrowSummary = [
    'borrow_records' => (int) ($profileDashboard['borrow_records'] ?? 0),
    'books_borrowed' => (int) ($profileDashboard['books_borrowed'] ?? 0),
    'active_loans' => (int) ($profileDashboard['active_loans'] ?? 0),
];

try {
    $recentPurchases = db_call_all($conn, 'CALL sp_get_profile_recent_purchases(?, ?)', 'ii', [(int) $currentUser['uid'], 4]);
    $recentBorrowings = db_call_all($conn, 'CALL sp_get_profile_recent_borrowings(?, ?)', 'ii', [(int) $currentUser['uid'], 4]);
} catch (Throwable $exception) {
    $recentPurchases = [];
    $recentBorrowings = [];
}

$profileRole = ucfirst((string) ($profileUser['role'] ?? 'user'));
$profileInitials = profile_initials($username);
$profileImageAlt = $username . ' profile photo';
$memberSince = profile_format_date((string) ($profileUser['created_at'] ?? ''));

require_once __DIR__ . '/header.php';
?>
<main class="profile-shell">
    <div class="container-fluid px-4 py-4">
        <section class="profile-hero-card mb-4">
            <div class="profile-hero-main">
                <div class="profile-avatar-wrap">
                    <?php if ($profileImage !== ''): ?>
                        <img class="profile-avatar" src="<?= htmlspecialchars($profileImage) ?>" alt="<?= htmlspecialchars($profileImageAlt) ?>">
                    <?php else: ?>
                        <div class="profile-avatar profile-avatar-fallback"><?= htmlspecialchars($profileInitials) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="profile-kicker">Account settings</span>
                    <h1><?= htmlspecialchars($username) ?></h1>
                    <p class="profile-hero-copy mb-0">Update your personal details, security settings, and reading address from one place.</p>
                    <div class="profile-badges mt-3">
                        <span class="profile-pill"><?= htmlspecialchars($profileRole) ?></span>
                        <span class="profile-pill is-muted">Member since <?= htmlspecialchars($memberSince) ?></span>
                        <?php if ($phoneNumber !== ''): ?>
                            <span class="profile-pill is-muted"><?= htmlspecialchars($phoneNumber) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="profile-stat-grid">
                <div class="profile-stat-card">
                    <span>Orders placed</span>
                    <strong><?= number_format((int) ($purchaseSummary['total_orders'] ?? 0)) ?></strong>
                </div>
                <div class="profile-stat-card">
                    <span>Books bought</span>
                    <strong><?= number_format((int) ($purchaseSummary['books_bought'] ?? 0)) ?></strong>
                </div>
                <div class="profile-stat-card">
                    <span>Books borrowed</span>
                    <strong><?= number_format((int) ($borrowSummary['books_borrowed'] ?? 0)) ?></strong>
                </div>
                <div class="profile-stat-card">
                    <span>Active loans</span>
                    <strong><?= number_format((int) ($borrowSummary['active_loans'] ?? 0)) ?></strong>
                </div>
            </div>
        </section>

        <?php if ($message !== null): ?>
            <div class="alert alert-<?= $hasError ? 'danger' : 'success' ?>" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="profile-panel card border-0 mb-4">
                    <div class="card-header border-0">
                        <span class="profile-panel-kicker">Personal profile</span>
                        <h2 class="profile-section-title">How you appear in the system</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="username">Username</label>
                                    <input class="form-control" id="username" type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email Address</label>
                                    <input class="form-control" id="email" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="phone_number">Phone Number</label>
                                    <input class="form-control" id="phone_number" type="text" name="phone_number" value="<?= htmlspecialchars($phoneNumber) ?>" placeholder="+254 700 000 000">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="profile_photo">Profile Picture</label>
                                    <input class="form-control" id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.jfif,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                                    <div class="form-text">Upload a JPG, JPEG, JFIF, PNG, WEBP, or GIF up to 4 MB. This works for users, staff, and admin accounts.</div>
                                </div>
                                <?php if ($profileImage !== ''): ?>
                                    <div class="col-12">
                                        <div class="profile-photo-tools">
                                            <div class="profile-photo-preview">
                                                <span class="profile-photo-preview-label">Current picture</span>
                                                <div class="profile-photo-preview-row">
                                                    <img class="profile-photo-preview-image" src="<?= htmlspecialchars($profileImage) ?>" alt="<?= htmlspecialchars($profileImageAlt) ?>">
                                                    <label class="form-check profile-photo-remove">
                                                        <input class="form-check-input" type="checkbox" name="remove_profile_image" value="1">
                                                        <span class="form-check-label">Remove this picture when I save</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <label class="form-label" for="bio">Short Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="500" placeholder="Tell readers and staff a little about yourself."><?= htmlspecialchars($bio) ?></textarea>
                                    <div class="form-text">Up to 500 characters.</div>
                                </div>
                            </div>
                            <div class="text-end mt-4">
                                <button class="btn btn-primary" type="submit" name="submit_user_details">Save Profile</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="profile-panel card border-0">
                    <div class="card-header border-0">
                        <span class="profile-panel-kicker">Address book</span>
                        <h2 class="profile-section-title">Where your orders and borrowing details point</h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="city">City</label>
                                    <input class="form-control" id="city" type="text" name="city" value="<?= htmlspecialchars($city) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="country">Country</label>
                                    <input class="form-control" id="country" type="text" name="country" value="<?= htmlspecialchars($country) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="address_line1">Address Line 1</label>
                                    <input class="form-control" id="address_line1" type="text" name="address_line1" value="<?= htmlspecialchars($addressLine1) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="address_line2">Address Line 2</label>
                                    <input class="form-control" id="address_line2" type="text" name="address_line2" value="<?= htmlspecialchars($addressLine2) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="post_code">Postcode / Zip</label>
                                    <input class="form-control" id="post_code" type="text" name="post_code" value="<?= htmlspecialchars($postCode) ?>" required>
                                </div>
                            </div>
                            <div class="text-end mt-4">
                                <button class="btn btn-primary" type="submit" name="submit_address_details">Save Address</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="profile-panel card border-0 mb-4">
                    <div class="card-header border-0">
                        <span class="profile-panel-kicker">Security</span>
                        <h2 class="profile-section-title">Change your password</h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input class="form-control" id="current_password" type="password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="new_password">New Password</label>
                                <input class="form-control" id="new_password" type="password" name="new_password" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="confirm_new_password">Confirm New Password</label>
                                <input class="form-control" id="confirm_new_password" type="password" name="confirm_new_password" minlength="8" required>
                            </div>
                            <div class="profile-security-note mb-3">
                                Use at least 8 characters and avoid reusing your current password.
                            </div>
                            <button class="btn btn-primary w-100" type="submit" name="submit_password_change">Update Password</button>
                        </form>
                    </div>
                </div>

                <div class="profile-panel card border-0 mb-4">
                    <div class="card-header border-0">
                        <span class="profile-panel-kicker">Recent activity</span>
                        <h2 class="profile-section-title">Latest purchases</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($recentPurchases !== []): ?>
                            <div class="profile-activity-list">
                                <?php foreach ($recentPurchases as $purchase): ?>
                                    <div class="profile-activity-item">
                                        <strong><?= htmlspecialchars((string) $purchase['title']) ?></strong>
                                        <div class="text-muted small">Order #<?= (int) $purchase['order_id'] ?> · Qty <?= (int) $purchase['quantity'] ?></div>
                                        <div class="text-muted small">$<?= number_format((float) $purchase['price'], 2) ?> each · <?= htmlspecialchars(profile_format_date((string) $purchase['order_date'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">You have not bought any books yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-panel card border-0">
                    <div class="card-header border-0">
                        <span class="profile-panel-kicker">Borrowing log</span>
                        <h2 class="profile-section-title">Latest borrowed books</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($recentBorrowings !== []): ?>
                            <div class="profile-activity-list">
                                <?php foreach ($recentBorrowings as $borrowing): ?>
                                    <div class="profile-activity-item">
                                        <div class="d-flex justify-content-between gap-2 align-items-start">
                                            <strong><?= htmlspecialchars((string) $borrowing['title']) ?></strong>
                                            <span class="profile-pill is-status"><?= htmlspecialchars(ucfirst((string) $borrowing['status'])) ?></span>
                                        </div>
                                        <div class="text-muted small">Qty <?= (int) $borrowing['quantity'] ?> · Borrowed <?= htmlspecialchars(profile_format_date((string) $borrowing['borrowed_at'])) ?></div>
                                        <div class="text-muted small">Due <?= $borrowing['due_date'] !== null ? htmlspecialchars(profile_format_date((string) $borrowing['due_date'])) : 'Not set' ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">You have not borrowed any books yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
