<?php
declare(strict_types=1);

/**
 * 匿名フィードバック・利用ログ用のSQLite接続。
 * 個人特定情報(写真そのもの、IPアドレス、位置情報等)は一切保存しない。
 */
final class FeedbackDb
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dbPath = __DIR__ . '/../database/feedback.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS feedback_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                verdict TEXT NOT NULL,
                delta_ab REAL,
                delta_e REAL,
                low_reliability_warning INTEGER NOT NULL DEFAULT 0,
                subjective_feedback TEXT,
                free_text TEXT,
                updated_at TEXT
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_logs_created_at ON feedback_logs(created_at)');

        self::$pdo = $pdo;
        return $pdo;
    }
}
