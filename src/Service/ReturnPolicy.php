<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * The two pieces of art. 14(1) information the acceptance message has to carry:
 * where the goods go back, and who pays for sending them.
 *
 * Statics with no state, like DigitalConsentService::statutoryConsentText(), so
 * both the email and the settings screen read the same sentences from the same
 * place and cannot drift apart. The screen shows the shop the exact wording the
 * customer will get.
 */
final class ReturnPolicy
{
    /** Accepted values of the `return_cost` setting. Enforced in Settings::sanitize(). */
    public const COSTS = ['not_stated', 'customer', 'shop'];

    /**
     * Art. 13(1): the trader reimburses without undue delay and in any case
     * within 14 days of being informed of the withdrawal.
     *
     * Counted from the declaration, not from the day the shop accepted it. The
     * clock the trader is on starts when the consumer tells them, and a shop
     * that sits on a request for a week has spent a week of it.
     */
    public const REFUND_DAYS = 14;

    /** Statuses that still owe the customer money. */
    private const OWES_REFUND = ['pending', 'accepted'];

    /**
     * When the refund is due for a stored request, or 0 when it is not owed or
     * the row carries no usable date.
     */
    public static function refundDeadline(object $request): int
    {
        if (! in_array((string) ($request->status ?? ''), self::OWES_REFUND, true)) {
            return 0;
        }

        $created = (string) ($request->created_at ?? '');
        if ($created === '' || str_starts_with($created, '0000')) {
            return 0;
        }

        // The column is site time, like everything else this plugin stores, so
        // it is compared against current_time() and never against time().
        $start = strtotime($created);

        return $start === false ? 0 : $start + (self::REFUND_DAYS * DAY_IN_SECONDS);
    }

    /** Whether that deadline has already gone by. */
    public static function refundOverdue(object $request): bool
    {
        $deadline = self::refundDeadline($request);

        return $deadline > 0 && $deadline < (int) current_time('timestamp');
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * The return address, as it should be printed.
     *
     * The shop's own setting wins. Empty falls back to the WooCommerce store
     * address, which the shop already publishes, so the fallback invents
     * nothing. Returns an empty string when neither resolves, and the caller
     * then suppresses the whole block rather than printing an empty box.
     */
    public static function address(): string
    {
        $configured = trim((string) (self::settings()['return_address'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return self::storeAddress();
    }

    /** The WooCommerce store address, or '' when the shop never filled it in. */
    public static function storeAddress(): string
    {
        $line1 = trim((string) get_option('woocommerce_store_address', ''));
        if ($line1 === '') {
            // Without a street there is no address worth printing, whatever
            // else is filled in.
            return '';
        }

        $parts = [
            $line1,
            trim((string) get_option('woocommerce_store_address_2', '')),
            trim(trim((string) get_option('woocommerce_store_postcode', '')) . ' ' . trim((string) get_option('woocommerce_store_city', ''))),
            self::baseCountryName(),
        ];

        return implode("\n", array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function baseCountryName(): string
    {
        $base = (string) get_option('woocommerce_default_country', '');
        if ($base === '') {
            return '';
        }

        // The option stores COUNTRY:STATE; only the country belongs in an address block.
        $code = strtok($base, ':');
        if (! is_string($code)) {
            return '';
        }

        if (function_exists('WC') && WC()->countries instanceof \WC_Countries) {
            $countries = WC()->countries->get_countries();
            if (isset($countries[$code])) {
                return (string) $countries[$code];
            }
        }

        return $code;
    }

    /**
     * The cost paragraph, or '' when the shop has not said who pays.
     *
     * Silence is the shipped default on purpose: asserting that the consumer
     * pays only holds if the shop informed them before the contract, and if it
     * did not, art. 14(1) leaves the cost with the trader.
     */
    public static function costSentence(): string
    {
        $s    = self::settings();
        $rule = (string) ($s['return_cost'] ?? 'not_stated');
        $note = trim((string) ($s['return_cost_note'] ?? ''));

        if (! in_array($rule, self::COSTS, true) || $rule === 'not_stated') {
            return '';
        }

        $sentence = $rule === 'shop'
            ? __('We bear the direct cost of returning the goods.', 'plogins-withdraw')
            : __('You bear the direct cost of returning the goods.', 'plogins-withdraw');

        return $note !== '' ? $sentence . ' ' . $note : $sentence;
    }
}
