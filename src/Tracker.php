<?php declare(strict_types=1);
namespace Gratis\Analytics;

final class Tracker
{
    public static function injectScript(): void
    {
        if (is_admin() || is_user_logged_in()) return;
        $endpoint = rest_url('gratis-analytics/v1/collect');
        echo '<script>!function(){var d=document,r=d.referrer,p=location.pathname;fetch("' . esc_url($endpoint) . '",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({p:p,r:r,w:screen.width,l:navigator.language}),keepalive:!0})}()</script>' . "\n";
    }
}
