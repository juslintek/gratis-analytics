<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Admin dashboard rendering the headline stats, a daily trend chart and the
 * top pages / referrers / browsers / devices for a selectable date range.
 */
final class Dashboard {

    /** Selectable ranges (days) for the dashboard. */
    private const RANGES = [7, 30, 90, 365];

    public static function register(): void {
        add_menu_page(
            __('Analytics', 'gratis-analytics'),
            __('Analytics', 'gratis-analytics'),
            'manage_options',
            'gratis-analytics',
            [__CLASS__, 'render'],
            'dashicons-chart-area',
            4
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-analytics'));
        }

        $days = self::selectedRange();
        $from = gmdate('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $to   = gmdate('Y-m-d 23:59:59');

        $stats   = Store::stats($from, $to);
        $series  = Store::timeseries($from, $to);
        $pages   = Store::topBy('path', $from, $to);
        $refs    = Store::topBy('referrer', $from, $to);
        $browser = Store::topBy('browser', $from, $to, 5);
        $devices = Store::topBy('device', $from, $to, 5);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Analytics', 'gratis-analytics') . '</h1>';

        self::rangeNav($days);

        // Headline stat cards.
        echo '<div style="display:flex;gap:20px;margin:20px 0;flex-wrap:wrap">';
        self::statCard(__('Pageviews', 'gratis-analytics'), $stats['pageviews'], '#f0fdf4');
        self::statCard(__('Unique visitors', 'gratis-analytics'), $stats['visitors'], '#eff6ff');
        echo '</div>';

        self::trendChart($series);

        echo '<div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:24px">';
        self::table(__('Top pages', 'gratis-analytics'), __('Page', 'gratis-analytics'), $pages);
        self::table(__('Top referrers', 'gratis-analytics'), __('Source', 'gratis-analytics'), $refs);
        echo '</div>';

        echo '<div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:24px">';
        self::table(__('Browsers', 'gratis-analytics'), __('Browser', 'gratis-analytics'), $browser);
        self::table(__('Devices', 'gratis-analytics'), __('Device', 'gratis-analytics'), $devices);
        echo '</div>';

        echo '<p style="margin-top:24px"><a href="' . esc_url(admin_url('admin.php?page=' . Settings::PAGE)) . '">' . esc_html__('Settings', 'gratis-analytics') . '</a></p>';
        echo '</div>';
    }

    /**
     * Resolve the requested range from the query string (read-only filter).
     */
    private static function selectedRange(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display range filter, no state change.
        $requested = isset($_GET['range']) ? absint(wp_unslash($_GET['range'])) : 7;
        return in_array($requested, self::RANGES, true) ? $requested : 7;
    }

    private static function rangeNav(int $active): void {
        $labels = [
            7   => __('7 days', 'gratis-analytics'),
            30  => __('30 days', 'gratis-analytics'),
            90  => __('90 days', 'gratis-analytics'),
            365 => __('1 year', 'gratis-analytics'),
        ];

        echo '<div style="margin:8px 0">';
        foreach (self::RANGES as $days) {
            $url   = add_query_arg(['page' => 'gratis-analytics', 'range' => $days], admin_url('admin.php'));
            $style = $days === $active ? 'font-weight:700;text-decoration:none;' : '';
            echo '<a class="button' . ($days === $active ? ' button-primary' : '') . '" style="margin-right:6px;' . esc_attr($style) . '" href="' . esc_url($url) . '">' . esc_html($labels[$days]) . '</a>';
        }
        echo '</div>';
    }

    private static function statCard(string $label, int $value, string $bg): void {
        echo '<div style="background:' . esc_attr($bg) . ';padding:20px;border-radius:8px;flex:1;min-width:180px;text-align:center">';
        echo '<div style="font-size:2rem;font-weight:700">' . esc_html(number_format_i18n($value)) . '</div>';
        echo '<div>' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * Render a simple CSS bar chart for the daily trend.
     *
     * @param array<int,array{day:string,views:int,visitors:int}> $series
     */
    private static function trendChart(array $series): void {
        echo '<h3>' . esc_html__('Pageviews per day', 'gratis-analytics') . '</h3>';

        if (empty($series)) {
            echo '<p>' . esc_html__('No data yet.', 'gratis-analytics') . '</p>';
            return;
        }

        $max = 1;
        foreach ($series as $point) {
            $max = max($max, $point['views']);
        }

        echo '<div style="display:flex;align-items:flex-end;gap:2px;height:140px;border-bottom:1px solid #ccd0d4;padding-top:8px;overflow-x:auto">';
        foreach ($series as $point) {
            $height = (int) round(($point['views'] / $max) * 120);
            $title  = sprintf(
                /* translators: 1: date, 2: pageviews, 3: visitors */
                __('%1$s — %2$s views, %3$s visitors', 'gratis-analytics'),
                $point['day'],
                number_format_i18n($point['views']),
                number_format_i18n($point['visitors'])
            );
            echo '<div title="' . esc_attr($title) . '" style="flex:1;min-width:6px;background:#2271b1;height:' . absint($height) . 'px;border-radius:2px 2px 0 0"></div>';
        }
        echo '</div>';
    }

    /**
     * Render a two-column "label / views" table.
     *
     * @param array<int,array{label:string,views:int}> $rows
     */
    private static function table(string $heading, string $col, array $rows): void {
        echo '<div style="flex:1;min-width:280px">';
        echo '<h3>' . esc_html($heading) . '</h3>';

        if (empty($rows)) {
            echo '<p>' . esc_html__('No data yet.', 'gratis-analytics') . '</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>' . esc_html($col) . '</th><th style="width:80px;text-align:right">' . esc_html__('Views', 'gratis-analytics') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $label = '' === $row['label'] ? __('(direct / none)', 'gratis-analytics') : $row['label'];
            echo '<tr><td>' . esc_html($label) . '</td><td style="text-align:right">' . esc_html(number_format_i18n($row['views'])) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
