<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'tor_skp';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('TOR_DB_HOST') ?: DB_HOST;
    $name = getenv('TOR_DB_NAME') ?: DB_NAME;
    $user = getenv('TOR_DB_USER') ?: DB_USER;
    $pass = getenv('TOR_DB_PASS');
    $pass = $pass === false ? DB_PASS : $pass;
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);

    return $pdo;
}

function requireDatabaseTables(array $tables): void
{
    static $verified = [];
    foreach (array_unique(array_map('strval', $tables)) as $table) {
        if (isset($verified[$table])) continue;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new RuntimeException('Invalid database table name');
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException("Database schema is incomplete: missing {$table}");
        }
        $verified[$table] = true;
    }
}
