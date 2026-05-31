<?php
/**
 * Plugin Name: Gratis Analytics
 * Description: Privacy-first self-hosted analytics. No cookies, GDPR compliant, SQLite storage. Replaces Google Analytics + MonsterInsights ($99/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 * Text Domain: gratis-analytics
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';

add_action('wp_footer', [\Gratis\Analytics\Tracker::class, 'injectScript']);
add_action('rest_api_init', [\Gratis\Analytics\Collector::class, 'register']);
add_action('admin_menu', [\Gratis\Analytics\Dashboard::class, 'register']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-analytics', \Gratis\Analytics\CLI\AnalyticsCommand::class);
    }
});
