<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Lightweight server-side User-Agent classifier.
 *
 * Buckets the request User-Agent header into a coarse browser / OS / device
 * label for aggregate reporting. The raw User-Agent string is never stored —
 * only these low-cardinality buckets — so no device fingerprint is retained.
 */
final class UserAgent {

    /**
     * Classify a User-Agent string.
     *
     * @return array{browser:string,os:string,device:string}
     */
    public static function parse(string $ua): array {
        return [
            'browser' => self::browser($ua),
            'os'      => self::os($ua),
            'device'  => self::device($ua),
        ];
    }

    private static function browser(string $ua): string {
        // Order matters: more specific tokens first.
        $map = [
            'Edge'    => '/Edg[ A-Za-z]*\//',
            'Opera'   => '/(OPR|Opera)\//',
            'Samsung' => '/SamsungBrowser\//',
            'Firefox' => '/Firefox\//',
            'Chrome'  => '/(Chrome|CriOS)\//',
            'Safari'  => '/Safari\//',
            'IE'      => '/(MSIE |Trident\/)/',
        ];
        foreach ($map as $label => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $label;
            }
        }
        return 'Other';
    }

    private static function os(string $ua): string {
        $map = [
            'iOS'     => '/(iPhone|iPad|iPod)/',
            'Android' => '/Android/',
            'Windows' => '/Windows NT/',
            'macOS'   => '/(Macintosh|Mac OS X)/',
            'Linux'   => '/Linux/',
        ];
        foreach ($map as $label => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $label;
            }
        }
        return 'Other';
    }

    private static function device(string $ua): string {
        if (preg_match('/(iPad|Tablet)/', $ua) || (preg_match('/Android/', $ua) && !preg_match('/Mobile/', $ua))) {
            return 'tablet';
        }
        if (preg_match('/(Mobi|iPhone|iPod|Android.*Mobile|Windows Phone)/', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}
