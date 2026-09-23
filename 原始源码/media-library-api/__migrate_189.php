<?php
require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/DB.php';
use Core\DB;
echo "=== v1.8.9 数据库迁移 ===
";
try {
    $db = DB::instance();
    $db->exec("CREATE TABLE IF NOT EXISTS media_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(191) NOT NULL UNIQUE, email VARCHAR(255) DEFAULT NULL, pass_hash VARCHAR(255) DEFAULT NULL, is_admin TINYINT(1) NOT NULL DEFAULT 0, status TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_login DATETIME DEFAULT NULL)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON media_users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON media_users(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_status ON media_users(status)");
    echo "OK 数据库迁移完成
";
} catch (Exception $e) {
    echo "ERROR 迁移失败: " . $e->getMessage() . "
";
    exit(1);
}
