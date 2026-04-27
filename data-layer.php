<?php
declare(strict_types=1);

// Centralize parameter binding so page-level code can stay focused on workflow logic.
function db_bind_and_execute(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error !== '' ? $stmt->error : 'Database statement execution failed.');
    }
}

function db_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException($conn->error !== '' ? $conn->error : 'Database statement preparation failed.');
    }

    db_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    $stmt->close();

    return $rows;
}

function db_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_fetch_all($conn, $sql, $types, $params);

    return $rows[0] ?? null;
}

// Stored procedures can leave extra result sets open, so we always drain them before the next call.
function db_flush_results(mysqli $conn): void
{
    while ($conn->more_results()) {
        $conn->next_result();
        $result = $conn->store_result();

        if ($result instanceof mysqli_result) {
            $result->free();
        }
    }
}

function db_call_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException($conn->error !== '' ? $conn->error : 'Database procedure preparation failed.');
    }

    db_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    $stmt->close();
    db_flush_results($conn);

    return $rows;
}

function db_call_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_call_all($conn, $sql, $types, $params);

    return $rows[0] ?? null;
}

function db_call_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): mixed
{
    $row = db_call_one($conn, $sql, $types, $params);

    if ($row === null) {
        return null;
    }

    return array_shift($row);
}
