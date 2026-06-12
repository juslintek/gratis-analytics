<?php declare(strict_types=1);

namespace Gratis\Analytics;

defined('ABSPATH') || exit;

/**
 * Injects the cookie-free tracking beacon into the site footer.
 *
 * The beacon is a tiny inline script (no external request, no library) that
 * POSTs the current path, referrer, screen width and language to the REST
 * collector. It also honours the browser Do Not Track signal client-side.
 */
final class Tracker {

    public static function injectScript(): void {
        if (is_admin()) {
            return;
        }
        if (!Settings::getBool('enabled', true)) {
            return;
        }
        if (is_user_logged_in() && !Settings::getBool('track_logged_in', false)) {
            return;
        }

        $endpoint = rest_url('gratis-analytics/v1/collect');

        printf(
            '<script>(function(){var R=%1$s;if(R&&(navigator.doNotTrack=="1"||window.doNotTrack=="1"||navigator.msDoNotTrack=="1"))return;var d=document;fetch("%2$s",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({p:location.pathname,r:d.referrer,w:screen.width,l:navigator.language}),keepalive:true,credentials:"same-origin"}).catch(function(){})})();</script>%3$s',
            Settings::getBool('respect_dnt', true) ? 'true' : 'false',
            esc_url($endpoint),
            "\n"
        );
    }
}
