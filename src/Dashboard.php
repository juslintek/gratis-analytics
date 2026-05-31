<?php declare(strict_types=1);
namespace Gratis\Analytics;

final class Dashboard
{
    public static function register(): void
    {
        add_menu_page('Analytics', 'Analytics', 'manage_options', 'gratis-analytics', [__CLASS__, 'render'], 'dashicons-chart-area', 4);
    }

    public static function render(): void
    {
        $from = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
        $to = gmdate('Y-m-d 23:59:59');
        $stats = Store::stats($from, $to);
        $pages = Store::topPages($from, $to);
        $refs = Store::topReferrers($from, $to);

        echo '<div class="wrap"><h1>📊 Analytics (Last 7 Days)</h1>';
        echo '<div style="display:flex;gap:20px;margin:20px 0">';
        echo '<div style="background:#f0fdf4;padding:20px;border-radius:8px;flex:1;text-align:center"><div style="font-size:2rem;font-weight:700">' . number_format($stats['pageviews']) . '</div><div>Pageviews</div></div>';
        echo '<div style="background:#eff6ff;padding:20px;border-radius:8px;flex:1;text-align:center"><div style="font-size:2rem;font-weight:700">' . number_format($stats['visitors']) . '</div><div>Visitors</div></div>';
        echo '</div>';

        if ($pages) {
            echo '<h3>Top Pages</h3><table class="wp-list-table widefat fixed striped"><thead><tr><th>Page</th><th>Views</th></tr></thead><tbody>';
            foreach ($pages as $p) echo '<tr><td>' . esc_html($p['path']) . '</td><td>' . $p['views'] . '</td></tr>';
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}
