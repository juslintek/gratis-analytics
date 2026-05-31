<?php declare(strict_types=1);
namespace Gratis\Analytics;

final class Store
{
    private static ?\SQLite3 $db = null;

    public static function db(): \SQLite3
    {
        if (self::$db) return self::$db;
        $dir = WP_CONTENT_DIR . '/gratis-analytics';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        self::$db = new \SQLite3($dir . '/analytics.db');
        self::$db->exec('PRAGMA journal_mode=WAL');
        self::$db->exec('CREATE TABLE IF NOT EXISTS pageviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path TEXT, referrer TEXT, screen_width INTEGER,
            language TEXT, ip_hash TEXT, created_at TEXT
        )');
        self::$db->exec('CREATE INDEX IF NOT EXISTS idx_pv_date ON pageviews(created_at)');
        self::$db->exec('CREATE INDEX IF NOT EXISTS idx_pv_path ON pageviews(path)');
        return self::$db;
    }

    public static function stats(string $from, string $to): array
    {
        $db = self::db();
        $total = (int) $db->querySingle("SELECT COUNT(*) FROM pageviews WHERE created_at BETWEEN '{$from}' AND '{$to}'");
        $unique = (int) $db->querySingle("SELECT COUNT(DISTINCT ip_hash) FROM pageviews WHERE created_at BETWEEN '{$from}' AND '{$to}'");
        return ['pageviews' => $total, 'visitors' => $unique];
    }

    public static function topPages(string $from, string $to, int $limit = 10): array
    {
        $db = self::db();
        $result = $db->query("SELECT path, COUNT(*) as views FROM pageviews WHERE created_at BETWEEN '{$from}' AND '{$to}' GROUP BY path ORDER BY views DESC LIMIT {$limit}");
        $pages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $pages[] = $row;
        return $pages;
    }

    public static function topReferrers(string $from, string $to, int $limit = 10): array
    {
        $db = self::db();
        $result = $db->query("SELECT referrer, COUNT(*) as views FROM pageviews WHERE created_at BETWEEN '{$from}' AND '{$to}' AND referrer != '' GROUP BY referrer ORDER BY views DESC LIMIT {$limit}");
        $refs = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $refs[] = $row;
        return $refs;
    }
}
