<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Custom database table storage for anonymous page views.
 *
 * Page views are stored in a dedicated `{prefix}gratis_analytics_events`
 * table (one row per view) rather than as posts, so high-volume traffic does
 * not bloat wp_posts/wp_postmeta. No personally identifiable information is
 * stored: visitors are represented only by a daily-rotating, salted hash that
 * cannot be reversed to an IP address or correlated across days.
 */
final class Store {

    /** Schema version, bumped when the table structure changes. */
    public const DB_VERSION = '1';

    /** Option key holding the installed schema version. */
    private const VERSION_OPTION = 'gratis_analytics_db_version';

    /** Columns that may be used as a GROUP BY dimension in {@see topBy()}. */
    private const GROUPABLE = ['path', 'referrer', 'browser', 'os', 'device', 'language'];

    /**
     * Fully-qualified table name including the site table prefix.
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'gratis_analytics_events';
    }

    /**
     * Create or upgrade the events table via dbDelta.
     *
     * Safe to call on activation and on admin_init (guarded by the stored
     * schema version) — dbDelta only applies the diff.
     */
    public static function install(): void {
        if (get_option(self::VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            path VARCHAR(255) NOT NULL DEFAULT '/',
            referrer VARCHAR(255) NOT NULL DEFAULT '',
            visitor_hash CHAR(64) NOT NULL DEFAULT '',
            screen_width SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
            language VARCHAR(10) NOT NULL DEFAULT '',
            browser VARCHAR(40) NOT NULL DEFAULT '',
            os VARCHAR(40) NOT NULL DEFAULT '',
            device VARCHAR(16) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY path (path),
            KEY visitor_hash (visitor_hash)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Insert a single page view.
     *
     * @param array<string,mixed> $row Already-sanitised view payload.
     * @return int Inserted row ID, or 0 on failure.
     */
    public static function insert(array $row): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ok = $wpdb->insert(
            self::table(),
            [
                'path'         => substr((string) ($row['path'] ?? '/'), 0, 255),
                'referrer'     => substr((string) ($row['referrer'] ?? ''), 0, 255),
                'visitor_hash' => substr((string) ($row['visitor_hash'] ?? ''), 0, 64),
                'screen_width' => max(0, (int) ($row['screen_width'] ?? 0)),
                'language'     => substr((string) ($row['language'] ?? ''), 0, 10),
                'browser'      => substr((string) ($row['browser'] ?? ''), 0, 40),
                'os'           => substr((string) ($row['os'] ?? ''), 0, 40),
                'device'       => substr((string) ($row['device'] ?? ''), 0, 16),
                'created_at'   => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Total page views and distinct visitors in a datetime range (GMT).
     *
     * @return array{pageviews:int,visitors:int}
     */
    public static function stats(string $from, string $to): array {
        global $wpdb;
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pageviews = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at BETWEEN %s AND %s', $table, $from, $to));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $visitors = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT visitor_hash) FROM %i WHERE created_at BETWEEN %s AND %s', $table, $from, $to));

        return ['pageviews' => $pageviews, 'visitors' => $visitors];
    }

    /**
     * Top values for a single dimension within a range.
     *
     * @param string $column One of {@see GROUPABLE}.
     * @return array<int,array{label:string,views:int}>
     */
    public static function topBy(string $column, string $from, string $to, int $limit = 10): array {
        if (!in_array($column, self::GROUPABLE, true)) {
            return [];
        }

        global $wpdb;
        $table = self::table();
        $limit = max(1, $limit);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT %i AS label, COUNT(*) AS views FROM %i WHERE created_at BETWEEN %s AND %s AND %i <> '' GROUP BY %i ORDER BY views DESC LIMIT %d",
                $column,
                $table,
                $from,
                $to,
                $column,
                $column,
                $limit
            ),
            ARRAY_A
        );

        return array_map(
            static function (array $r): array {
                return ['label' => (string) $r['label'], 'views' => (int) $r['views']];
            },
            (array) $rows
        );
    }

    /**
     * Per-day page view / visitor counts for charting (ascending by day).
     *
     * @return array<int,array{day:string,views:int,visitors:int}>
     */
    public static function timeseries(string $from, string $to): array {
        global $wpdb;
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DATE(created_at) AS day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM %i WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY day ASC',
                $table,
                $from,
                $to
            ),
            ARRAY_A
        );

        return array_map(
            static function (array $r): array {
                return [
                    'day'      => (string) $r['day'],
                    'views'    => (int) $r['views'],
                    'visitors' => (int) $r['visitors'],
                ];
            },
            (array) $rows
        );
    }

    /**
     * Delete page views older than $days. Returns the number of rows removed.
     */
    public static function prune(int $days): int {
        if ($days <= 0) {
            return 0;
        }

        global $wpdb;
        $table  = self::table();
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at < %s', $table, $cutoff));
    }

    /**
     * Cron callback: prune using the configured retention window.
     */
    public static function pruneToRetention(): int {
        return self::prune(Settings::retentionDays());
    }
}
