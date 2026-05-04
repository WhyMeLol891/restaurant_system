<?php

declare(strict_types=1);

function getDbConnection(?string &$error = null): ?PDO
{
    $host = '127.0.0.1';
    $port = 3306;
    $dbName = 'restaurant_system';
    $username = 'root';
    $password = '';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    try {
        return new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $exception) {
        $error = $exception->getMessage();
        return null;
    }
}
