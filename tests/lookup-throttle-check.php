<?php
/**
 * The order number plus email lookup is capped per address and per IP.
 *
 * Run inside wp-env:  wp eval-file tests/lookup-throttle-check.php
 *
 * @package Withdraw/Tests
 */

// No declare(strict_types=1): wp-cli eval-file wraps this in eval().

use Withdraw\Plugin;
use Withdraw\Service\AccessLink;

$fails = 0;
$ok    = static function (bool $cond, string $label) use (&$fails): void {
    echo ($cond ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (! $cond) {
        ++$fails;
    }
};

$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand(1, 254);
$links = Plugin::instance()->container()->get(AccessLink::class);
$email = 'throttle-' . wp_generate_password(6, false) . '@example.com';

$blocked = false;
for ($i = 1; $i <= 15; $i++) {
    $blocked = $blocked || $links->lookupThrottled($email);
}
$ok(! $blocked, 'fifteen lookups for one address pass');
$ok($links->lookupThrottled($email), 'the sixteenth lookup for that address is refused');

$blocked = false;
for ($i = 1; $i <= 14; $i++) {
    $blocked = $blocked || $links->lookupThrottled('other-' . $i . '-' . $email);
}
$ok(! $blocked, 'other addresses pass until the IP reaches thirty');
$ok($links->lookupThrottled('last-' . $email), 'the thirty-first lookup from that IP is refused');

echo $fails === 0 ? "PASS\n" : "FAIL: {$fails}\n";
