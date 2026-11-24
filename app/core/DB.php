<?php
// app/core/DB.php

namespace App\Core;

use PDO;
use PDOException;

class DB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/db.php';
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
                try {
                    self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (\Throwable $t) {
                    self::$instance->exec("SET NAMES utf8mb4");
                }
                try {
                    self::$instance->exec("SET time_zone = '+05:30'");
                } catch (\Throwable $t) {
                    // Ignore if server lacks timezone table or privileges
                }
            } catch (PDOException $e) {
                // If in debug mode, show message; else generic error
                die("Database connection failed: " . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }

    public static function pdo(): PDO {
        return self::connect();
    }

    public static function beginTransaction(): bool {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool {
        return self::connect()->commit();
    }

    public static function rollBack(): bool {
        if (self::connect()->inTransaction()) {
            return self::connect()->rollBack();
        }
        return false;
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): ?array {
        $res = self::query($sql, $params)->fetch();
        return $res ?: null;
    }

    public static function fetchValue(string $sql, array $params = []) {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function lastInsertId(): string {
        return self::connect()->lastInsertId();
    }
}
