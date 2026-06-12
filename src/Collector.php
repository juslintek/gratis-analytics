<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * REST endpoint that receives the cookie-free tracking beacon and records an
 * anonymous page view.
 *
 * Privacy guarantees:
 *  - No cookies are set or read.
 *  - The raw IP address and User-Agent are never stored. The visitor is
 *    represented only by a salted SHA-256 hash that rotates every day, so the
 *    same person cannot be correlated across days and the hash cannot be
 *    reversed to an IP without the site secret salt.
 *  - The Do Not Track signal and known bots are honoured/skipped.
 */
final class Collector {

    public static function register(): void {
        register_rest_route(
            'gratis-analytics/v1',
            '/collect',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'collect'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public static function collect(\WP_REST_Request $request): \WP_REST_Response {
        $ok = new \WP_REST_Response(['ok' => true], 200);

        if (!Settings::getBool('enabled', true)) {
            return $ok;
        }

        // Respect the logged-in tracking preference (REST runs with cookies).
        if (is_user_logged_in() && !Settings::getBool('track_logged_in', false)) {
            return $ok;
        }

        // Honour Do Not Track.
        if (Settings::getBool('respect_dnt', true) && self::dntEnabled()) {
            return $ok;
        }

        $ua = self::userAgent();
        if (self::isBot($ua)) {
            return $ok;
        }

        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }

        $agent = UserAgent::parse($ua);

        Store::insert(
            [
                'path'         => self::sanitizePath((string) ($data['p'] ?? '/')),
                'referrer'     => self::referrerHost((string) ($data['r'] ?? '')),
                'visitor_hash' => self::visitorHash($ua),
                'screen_width' => min(10000, max(0, (int) ($data['w'] ?? 0))),
                'language'     => self::sanitizeLanguage((string) ($data['l'] ?? '')),
                'browser'      => $agent['browser'],
                'os'           => $agent['os'],
                'device'       => $agent['device'],
            ]
        );

        return $ok;
    }

    /**
     * Daily-rotating, salted visitor hash. Derived from the request IP +
     * User-Agent + site, combined with a site secret and the current UTC date
     * so it changes every day and is not reversible. Neither input is stored.
     */
    private static function visitorHash(string $ua): string {
        $ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }

        $salt = wp_salt('nonce') . '|' . gmdate('Y-m-d');

        return hash('sha256', $salt . '|' . $ip . '|' . $ua . '|' . home_url());
    }

    /**
     * Read and sanitise the request User-Agent header.
     */
    private static function userAgent(): string {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        return sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
    }

    private static function dntEnabled(): bool {
        if (!isset($_SERVER['HTTP_DNT'])) {
            return false;
        }
        return '1' === sanitize_text_field(wp_unslash($_SERVER['HTTP_DNT']));
    }

    /**
     * Detect common bots/crawlers so they do not inflate stats.
     */
    private static function isBot(string $ua): bool {
        if ('' === $ua) {
            return true;
        }
        return (bool) preg_match('/(bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|preview|wget|curl|python-requests|headless|lighthouse|pingdom|uptimerobot|monitor|scan)/i', $ua);
    }

    /**
     * Reduce the reported location to a clean, length-capped path.
     */
    private static function sanitizePath(string $path): string {
        $only_path = wp_parse_url($path, PHP_URL_PATH);
        if (is_string($only_path) && '' !== $only_path) {
            $path = $only_path;
        }
        $path = '/' . ltrim($path, '/');
        $path = sanitize_text_field($path);
        return substr($path, 0, 255);
    }

    /**
     * Store only the referrer host (never the full URL / query), and drop
     * self-referrals.
     */
    private static function referrerHost(string $referrer): string {
        if ('' === $referrer) {
            return '';
        }
        $host = wp_parse_url($referrer, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return '';
        }
        if (strtolower($host) === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
            return '';
        }
        return substr(sanitize_text_field($host), 0, 255);
    }

    private static function sanitizeLanguage(string $language): string {
        $language = strtolower(substr($language, 0, 10));
        $language = preg_replace('/[^a-z\-]/', '', $language);
        return (string) $language;
    }
}
