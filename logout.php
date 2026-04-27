<?php
declare(strict_types=1);

require_once __DIR__ . '/user.php';

logout_user();

header('Location: Lgin.php?logged_out=1');
exit;
