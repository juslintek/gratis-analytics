<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Adds a suggested privacy-policy section describing the (anonymous, cookie-
 * free) data Gratis Analytics collects. No personal-data exporter/eraser is
 * registered because no personally identifiable information is ever stored.
 */
final class Privacy {

    public static function register(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content  = '<p>' . esc_html__('This site uses Gratis Analytics, a self-hosted, privacy-first analytics tool. It records anonymous page views to understand which content is popular.', 'gratis-analytics') . '</p>';
        $content .= '<p>' . esc_html__('No cookies are used and no personally identifiable information is stored. Your IP address and browser User-Agent are never saved — they are combined with a secret site key and the current date to produce a one-way hash that rotates every day, used only to estimate the number of distinct visitors. This hash cannot be reversed and cannot be used to track you across days or across other websites.', 'gratis-analytics') . '</p>';
        $content .= '<p>' . esc_html__('The data collected for each page view is limited to: the page path, the referring website (domain only), your screen width, browser language, and coarse browser/operating-system/device type. This data is automatically deleted after the configured retention period.', 'gratis-analytics') . '</p>';

        wp_add_privacy_policy_content('Gratis Analytics', wp_kses_post(wpautop($content)));
    }
}
