<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Global Gratis Analytics settings, stored as a single option array and
 * exposed through the WordPress Settings API on a submenu under Analytics.
 */
final class Settings {

    /** Option name holding all settings. */
    public const OPTION = 'gratis_analytics_settings';

    /** Settings group / page slug. */
    private const GROUP = 'gratis_analytics_settings_group';
    public const PAGE   = 'gratis-analytics-settings';

    /**
     * Default values for every setting.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return [
            'enabled'          => 1,
            'track_logged_in'  => 0,
            'respect_dnt'      => 1,
            'retention_days'   => 365,
        ];
    }

    /**
     * Merged settings (stored over defaults), then filtered.
     *
     * @return array<string,mixed>
     */
    public static function all(): array {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $settings = array_merge(self::defaults(), $stored);

        /**
         * Filter the resolved Gratis Analytics settings.
         *
         * @param array<string,mixed> $settings Merged settings.
         */
        return (array) apply_filters('gratis_analytics_settings', $settings);
    }

    /**
     * Boolean accessor for a single setting.
     */
    public static function getBool(string $key, bool $fallback = false): bool {
        $all = self::all();
        return isset($all[$key]) ? (bool) $all[$key] : $fallback;
    }

    /**
     * Resolved data-retention window in days (0 = keep forever).
     */
    public static function retentionDays(): int {
        $all = self::all();
        return max(0, (int) ($all['retention_days'] ?? 365));
    }

    /**
     * Register the settings submenu page under the Analytics menu.
     */
    public static function registerMenu(): void {
        add_submenu_page(
            'gratis-analytics',
            __('Analytics Settings', 'gratis-analytics'),
            __('Settings', 'gratis-analytics'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'renderPage']
        );
    }

    /**
     * Register the option and its sanitisation callback.
     */
    public static function registerSettings(): void {
        register_setting(
            self::GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    /**
     * Sanitise incoming settings.
     *
     * @param mixed $input Raw option input.
     * @return array<string,mixed>
     */
    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];

        return [
            'enabled'         => empty($input['enabled']) ? 0 : 1,
            'track_logged_in' => empty($input['track_logged_in']) ? 0 : 1,
            'respect_dnt'     => empty($input['respect_dnt']) ? 0 : 1,
            'retention_days'  => min(3650, max(0, (int) ($input['retention_days'] ?? 365))),
        ];
    }

    /**
     * Render the settings screen.
     */
    public static function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-analytics'));
        }

        $settings = self::all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Analytics Settings', 'gratis-analytics') . '</h1>';
        echo '<p>' . esc_html__('Cookie-free, privacy-first page view tracking. No personal data is stored — visitors are counted with a salted hash that rotates daily and cannot be reversed.', 'gratis-analytics') . '</p>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Tracking', 'gratis-analytics') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[enabled]" value="1"' . checked((int) $settings['enabled'], 1, false) . '> ' . esc_html__('Enable page view tracking', 'gratis-analytics') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Logged-in users', 'gratis-analytics') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[track_logged_in]" value="1"' . checked((int) $settings['track_logged_in'], 1, false) . '> ' . esc_html__('Also track views from logged-in users', 'gratis-analytics') . '</label>';
        echo '<p class="description">' . esc_html__('Off by default so your own admin/editor visits are not counted.', 'gratis-analytics') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Do Not Track', 'gratis-analytics') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[respect_dnt]" value="1"' . checked((int) $settings['respect_dnt'], 1, false) . '> ' . esc_html__('Honour the browser Do Not Track (DNT) signal', 'gratis-analytics') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_retention">' . esc_html__('Data retention (days)', 'gratis-analytics') . '</label></th><td>';
        echo '<input type="number" min="0" max="3650" step="1" id="ga_retention" name="' . esc_attr(self::OPTION) . '[retention_days]" value="' . esc_attr((string) $settings['retention_days']) . '" class="small-text">';
        echo '<p class="description">' . esc_html__('Page views older than this are deleted daily. Set to 0 to keep data forever.', 'gratis-analytics') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
