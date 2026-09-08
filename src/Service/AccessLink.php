<?php

declare(strict_types=1);

namespace Withdraw\Service;

defined('ABSPATH') || exit;

/**
 * One-time access tokens for the guest withdrawal flow, and the throttle that
 * stops the form being used to mail a stranger's inbox.
 *
 * Tokens live in transients, not in the `token` column of the requests table:
 * an access token has to exist BEFORE a declaration is stored, so reusing that
 * column would mean inserting a placeholder row. That row would show up in the
 * admin log as a request nobody made, and openCountForOrder() would count it,
 * so issuing a link would immediately make the order ineligible.
 *
 * Only the SHA-256 of a token is ever written, as the transient name. A dump of
 * the options table therefore yields no working link, and a lookup is an exact
 * key match rather than a PHP string comparison, so there is nothing to time.
 *
 * No hooks of its own: it is injected into WithdrawalService.
 */
final class AccessLink
{
    private const TOKEN_PREFIX = 'wd_link_';
    private const EMAIL_PREFIX = 'wd_rl_e_';
    private const IP_PREFIX    = 'wd_rl_i_';

    /**
     * Mint a link token for an order and remember who it was mailed to.
     *
     * random_bytes() is the explicit CSPRNG and throws rather than degrading, so
     * a failure here surfaces as a failed send instead of a guessable token.
     */
    public function issue(int $orderId, string $email): string
    {
        $token = bin2hex(random_bytes(16));

        set_transient(self::TOKEN_PREFIX . hash('sha256', $token), [
            'order_id' => $orderId,
            'email'    => $email,
            'issued'   => time(),
        ], $this->ttl());

        return $token;
    }

    /**
     * @return array{order_id:int, email:string, issued:int}|null
     */
    public function resolve(string $token): ?array
    {
        $token = $this->sanitizeToken($token);
        if ($token === '') {
            return null;
        }

        $payload = get_transient(self::TOKEN_PREFIX . hash('sha256', $token));
        if (! is_array($payload) || empty($payload['order_id']) || empty($payload['email'])) {
            return null;
        }

        return [
            'order_id' => (int) $payload['order_id'],
            'email'    => (string) $payload['email'],
            'issued'   => (int) ($payload['issued'] ?? 0),
        ];
    }

    public function consume(string $token): void
    {
        $token = $this->sanitizeToken($token);
        if ($token === '') {
            return;
        }

        delete_transient(self::TOKEN_PREFIX . hash('sha256', $token));
    }

    /**
     * Normalise a token off a request. Anything that is not exactly 32 lowercase
     * hex characters is rejected before it is hashed, so no attacker-shaped
     * string reaches the transient API.
     */
    public function sanitizeToken(string $raw): string
    {
        $clean = (string) preg_replace('/[^a-f0-9]/', '', strtolower($raw));

        return strlen($clean) === 32 ? $clean : '';
    }

    /** How long an issued link stays valid, in seconds. */
    public function ttl(): int
    {
        /**
         * Filters the lifetime of a withdrawal access link.
         *
         * One hour survives normal mail-queue lag; longer widens the window in
         * which a forwarded link still works.
         *
         * @param int $ttl Lifetime in seconds.
         */
        $ttl = (int) apply_filters('withdraw/magic_link_ttl', 60 * MINUTE_IN_SECONDS);

        return $ttl > 0 ? $ttl : 60 * MINUTE_IN_SECONDS;
    }

    /**
     * Count this attempt, then say whether it is over a limit.
     *
     * Counting and deciding are one method on purpose. A counter that only
     * advanced when a link was actually sent would itself tell an attacker
     * whether the order number and the address belong together.
     */
    public function throttled(string $email): bool
    {
        $limits = $this->limits();

        $perEmail = $this->bump(self::EMAIL_PREFIX . hash('sha256', strtolower(trim($email))), $limits['window']);
        $perIp    = $this->bump(self::IP_PREFIX . hash('sha256', $this->clientIp()), $limits['window']);

        if ($perEmail['n'] > $limits['per_email'] || $perIp['n'] > $limits['per_ip']) {
            return true;
        }

        // Measured against the attempt before this one, because bump() has
        // already stamped the current attempt and would otherwise block itself.
        return $perEmail['prev'] > 0 && (time() - $perEmail['prev']) < $limits['cooldown'];
    }

    /**
     * @return array{per_email:int, per_ip:int, cooldown:int, window:int}
     */
    private function limits(): array
    {
        $defaults = [
            'per_email' => 3,
            'per_ip'    => 10,
            'cooldown'  => 60,
            'window'    => HOUR_IN_SECONDS,
        ];

        /**
         * Filters the send limits for withdrawal access links.
         *
         * @param array{per_email:int, per_ip:int, cooldown:int, window:int} $defaults
         */
        $filtered = apply_filters('withdraw/magic_link_limits', $defaults);
        if (! is_array($filtered)) {
            return $defaults;
        }

        $merged = array_merge($defaults, $filtered);

        return [
            'per_email' => max(1, absint($merged['per_email'])),
            'per_ip'    => max(1, absint($merged['per_ip'])),
            'cooldown'  => absint($merged['cooldown']),
            'window'    => max(60, absint($merged['window'])),
        ];
    }

    /**
     * Count one attempt inside a FIXED window.
     *
     * The window start is stored and the expiry is set to what is left of it,
     * never to the full length again. A window that restarted on every attempt
     * would never drain while attempts kept coming, so anyone who knew a
     * customer's billing address could hold that address over the cap
     * indefinitely by posting the form on a timer, and the flow answers
     * identically whether it sent a link or not, so neither the customer nor the
     * shop would see why the link stopped arriving. Locking a statutory function
     * from outside has to stay bounded, and one window is that bound.
     *
     * @return array{n:int, prev:int} n includes this attempt; prev is the
     *                                timestamp of the previous one in this
     *                                window, 0 if none.
     */
    private function bump(string $key, int $window): array
    {
        $now    = time();
        $stored = get_transient($key);
        $stored = is_array($stored) ? $stored : [];

        $start = isset($stored['start']) ? (int) $stored['start'] : 0;
        if ($start < 1 || $start > $now || ($now - $start) >= $window) {
            $start  = $now;
            $stored = [];
        }

        $n    = isset($stored['n']) ? (int) $stored['n'] : 0;
        $prev = isset($stored['last']) ? (int) $stored['last'] : 0;

        set_transient(
            $key,
            ['n' => $n + 1, 'last' => $now, 'start' => $start],
            max(1, $window - ($now - $start)),
        );

        return ['n' => $n + 1, 'prev' => $prev];
    }

    /**
     * REMOTE_ADDR only. X-Forwarded-For is client-controlled unless the site
     * declares trusted proxies, so honouring it would hand an attacker an
     * unlimited supply of fresh buckets. Behind a proxy many customers share one
     * bucket, which is why this cap is loose and the per-address cap is the real
     * defence.
     */
    private function clientIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
    }
}
