<?php declare(strict_types=1);

namespace Gratis\Analytics\CLI;

use Gratis\Analytics\Store;
use Gratis\Analytics\Settings;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands for Gratis Analytics.
 */
final class AnalyticsCommand {

    /**
     * Show headline analytics stats.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to report (default 7).
     *
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused; required by WP-CLI).
     * @param array<string,string> $assoc_args Associative args.
     */
    public function stats($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
        $days = max(1, (int) ($assoc_args['days'] ?? 7));
        $from = gmdate('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $to   = gmdate('Y-m-d 23:59:59');

        $stats = Store::stats($from, $to);
        \WP_CLI::log(sprintf('Last %d days: %d pageviews, %d unique visitors', $days, $stats['pageviews'], $stats['visitors']));
    }

    /**
     * Show the top pages.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to report (default 7).
     *
     * [--limit=<limit>]
     * : How many rows to show (default 10).
     *
     * @subcommand top-pages
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused; required by WP-CLI).
     * @param array<string,string> $assoc_args Associative args.
     */
    public function top_pages($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
        $this->report('path', $assoc_args);
    }

    /**
     * Show the top referrers.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to report (default 7).
     *
     * [--limit=<limit>]
     * : How many rows to show (default 10).
     *
     * @subcommand top-referrers
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused; required by WP-CLI).
     * @param array<string,string> $assoc_args Associative args.
     */
    public function top_referrers($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
        $this->report('referrer', $assoc_args);
    }

    /**
     * Delete page views older than the retention window (or a given age).
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Override the retention window; delete views older than this many days.
     *
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused; required by WP-CLI).
     * @param array<string,string> $assoc_args Associative args.
     */
    public function prune($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
        $days    = isset($assoc_args['days']) ? max(0, (int) $assoc_args['days']) : Settings::retentionDays();
        $deleted = Store::prune($days);
        \WP_CLI::success(sprintf('Pruned %d page view(s) older than %d days.', $deleted, $days));
    }

    /**
     * Shared "top dimension" report used by top-pages / top-referrers.
     *
     * @param array<string,string> $assoc_args
     */
    private function report(string $column, array $assoc_args): void {
        $days  = max(1, (int) ($assoc_args['days'] ?? 7));
        $limit = max(1, (int) ($assoc_args['limit'] ?? 10));
        $from  = gmdate('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $to    = gmdate('Y-m-d 23:59:59');

        $rows = Store::topBy($column, $from, $to, $limit);
        if (empty($rows)) {
            \WP_CLI::log('No data yet.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $rows, ['label', 'views']);
    }
}
