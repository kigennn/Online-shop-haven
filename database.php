<?php
declare(strict_types=1);

$databaseHost = '127.0.0.1';
$databaseUser = 'root';
$databasePassword = '';
$databaseName = 'bookstore';

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli($databaseHost, $databaseUser, $databasePassword, $databaseName);

    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
}
