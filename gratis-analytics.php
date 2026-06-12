<?php
/**
 * Plugin Name: Gratis Analytics
 * Plugin URI: https://github.com/juslintek/gratis-analytics
 * Description: Privacy-first, cookie-free self-hosted analytics. No cookies, no cross-site tracking, GDPR-friendly anonymous page views with automatic data retention. Replaces Google Analytics + MonsterInsights ($99/yr).
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-analytics
 */

defined('ABSPATH') || exit;

define('GRATIS_ANALYTICS_VERSION', '1.0.0');
define('GRATIS_ANALYTICS_FILE', __FILE__);
define('GRATIS_ANALYTICS_DIR', plugin_dir_path(__FILE__));
define('GRATIS_ANALYTICS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

/** Daily cron hook that prunes page views older than the retention window. */
const GRATIS_ANALYTICS_PRUNE_HOOK = 'gratis_analytics_prune';

// Front-end cookie-free tracking beacon.
add_action('wp_footer', [\Gratis\Analytics\Tracker::class, 'injectScript']);

// REST collection endpoint.
add_action('rest_api_init', [\Gratis\Analytics\Collector::class, 'register']);

// Admin dashboard + settings.
add_action('admin_menu', [\Gratis\Analytics\Dashboard::class, 'register']);
add_action('admin_menu', [\Gratis\Analytics\Settings::class, 'registerMenu']);
add_action('admin_init', [\Gratis\Analytics\Settings::class, 'registerSettings']);

// Privacy policy content + version-guarded table upgrade.
add_action('admin_init', [\Gratis\Analytics\Privacy::class, 'register']);
add_action('admin_init', [\Gratis\Analytics\Store::class, 'install']);

// Data-retention cron.
add_action(GRATIS_ANALYTICS_PRUNE_HOOK, [\Gratis\Analytics\Store::class, 'pruneToRetention']);

// Activation: create the table and schedule pruning.
register_activation_hook(__FILE__, function (): void {
    \Gratis\Analytics\Store::install();
    if (!wp_next_scheduled(GRATIS_ANALYTICS_PRUNE_HOOK)) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', GRATIS_ANALYTICS_PRUNE_HOOK);
    }
});

// Deactivation: clear the scheduled prune.
register_deactivation_hook(__FILE__, function (): void {
    $timestamp = wp_next_scheduled(GRATIS_ANALYTICS_PRUNE_HOOK);
    if ($timestamp) {
        wp_unschedule_event($timestamp, GRATIS_ANALYTICS_PRUNE_HOOK);
    }
});

// WP-CLI.
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-analytics', \Gratis\Analytics\CLI\AnalyticsCommand::class);
    }
});
