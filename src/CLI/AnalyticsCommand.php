<?php declare(strict_types=1);
namespace Gratis\Analytics\CLI;

use Gratis\Analytics\Store;

final class AnalyticsCommand
{
    /**
     * Show analytics stats.
     * [--days=<days>]
     * : Number of days (default 7).
     * @when after_wp_load
     */
    public function stats($args, $assoc_args): void
    {
        $days = (int) ($assoc_args['days'] ?? 7);
        $from = gmdate('Y-m-d 00:00:00', strtotime("-{$days} days"));
        $to = gmdate('Y-m-d 23:59:59');
        $stats = Store::stats($from, $to);
        \WP_CLI::log("Last {$days} days: {$stats['pageviews']} pageviews, {$stats['visitors']} visitors");
    }

    /**
     * Show top pages.
     * @subcommand top-pages
     * @when after_wp_load
     */
    public function top_pages($args, $assoc_args): void
    {
        $from = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
        $to = gmdate('Y-m-d 23:59:59');
        $pages = Store::topPages($from, $to);
        if (empty($pages)) { \WP_CLI::log("No data yet."); return; }
        \WP_CLI\Utils\format_items('table', $pages, ['path', 'views']);
    }
}
