<?php declare(strict_types=1);
namespace Gratis\Analytics;

final class Collector
{
    public static function register(): void
    {
        register_rest_route('gratis-analytics/v1', '/collect', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'collect'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function collect(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $db = Store::db();
        $stmt = $db->prepare('INSERT INTO pageviews (path, referrer, screen_width, language, ip_hash, created_at) VALUES (:p, :r, :w, :l, :h, :t)');
        $stmt->bindValue(':p', substr($data['p'] ?? '/', 0, 500));
        $stmt->bindValue(':r', substr($data['r'] ?? '', 0, 500));
        $stmt->bindValue(':w', (int) ($data['w'] ?? 0));
        $stmt->bindValue(':l', substr($data['l'] ?? '', 0, 10));
        $stmt->bindValue(':h', substr(md5($_SERVER['REMOTE_ADDR'] ?? '' . date('Y-m-d')), 0, 8)); // Daily rotating hash, not trackable
        $stmt->bindValue(':t', gmdate('Y-m-d H:i:s'));
        $stmt->execute();
        return new \WP_REST_Response(['ok' => true], 200);
    }
}
