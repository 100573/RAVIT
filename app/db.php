<?php
require_once __DIR__ . '/../config/config.php';

function getPDO() {
    try {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        error_log('[DB] 接続失敗: ' . $e->getMessage());
        throw new RuntimeException('DB接続失敗');
    }
}
