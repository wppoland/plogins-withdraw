<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * The two model texts from Annex I to Directive 2011/83/EU, generated from what
 * the shop already knows about itself.
 *
 * They shipped as English placeholders in a config file, printed to the customer
 * word for word. A config default cannot be translated, so a Polish or German
 * shop showed its customers English until somebody noticed and rewrote it by
 * hand. Generating them makes them translatable, keeps the trader's own details
 * in them, and keeps the stated period in step with the setting.
 *
 * A shop that types its own wording still wins: these are only used when the
 * corresponding setting is empty.
 */
final class StatutoryText
{
    /** @return array<string, mixed> */
    private static function settings(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    private static function periodDays(): int
    {
        return max(1, (int) (self::settings()['period_days'] ?? 14));
    }

    /**
     * The trader, as Annex I asks for: name, geographical address and, where
     * available, telephone and email.
     *
     * Falls back to the site title, the WooCommerce store address and the admin
     * email, so a shop that fills in nothing still produces a usable block
     * rather than a form addressed to nobody.
     */
    public static function seller(): string
    {
        $s = self::settings();

        $name = trim((string) ($s['seller_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) get_option('blogname', ''));
        }

        $email = trim((string) ($s['seller_email'] ?? ''));
        if ($email === '') {
            $email = trim((string) get_option('admin_email', ''));
        }

        $phone = trim((string) ($s['seller_phone'] ?? ''));

        $lines = array_filter([
            $name,
            ReturnPolicy::storeAddress(),
            $email,
            $phone,
        ], static fn (string $line): bool => $line !== '');

        return implode("\n", $lines);
    }

    /** Whether the shop has enough for the texts to name a real trader. */
    public static function sellerIsComplete(): bool
    {
        return ReturnPolicy::storeAddress() !== '';
    }

    /**
     * Annex I(B), the model withdrawal form.
     *
     * Printed on the items step so the consumer can see the statutory wording of
     * what they are about to declare.
     */
    public static function modelForm(): string
    {
        $seller = self::seller();

        return sprintf(
            // phpcs:ignore Generic.Files.LineLength.TooLong -- a translatable string has to be one literal, and this one is a statutory form.
            /* translators: %s: the trader's name, address and contact details, each on its own line */
            __("Model withdrawal form\n(complete and return this form only if you wish to withdraw from the contract)\n\nTo:\n%s\n\nI hereby give notice that I withdraw from my contract of sale of the following goods:\n\nOrdered on / received on:\n\nName of consumer:\n\nAddress of consumer:\n\nDate:", 'plogins-withdraw'),
            $seller !== '' ? $seller : __('(the seller\'s name and address)', 'plogins-withdraw'),
        );
    }

    /**
     * Annex I(A), the model instructions on withdrawal.
     *
     * Rendered by the `[withdraw_instructions]` shortcode, for the legal page
     * where a shop has to state this before the contract is concluded. Built in
     * parts because two of them depend on settings: the length of the period and
     * who bears the return cost.
     */
    public static function instructions(): string
    {
        $days   = self::periodDays();
        $seller = self::seller();
        $parts  = [];

        $parts[] = sprintf(
            /* translators: 1: number of days, 2: number of days */
            __('You have the right to withdraw from this contract within %1$d days without giving any reason. The withdrawal period will expire after %2$d days from the day on which you acquire, or a third party other than the carrier and indicated by you acquires, physical possession of the goods.', 'plogins-withdraw'),
            $days,
            $days,
        );

        $parts[] = sprintf(
            /* translators: %s: the trader's name, address and contact details */
            __("To exercise the right of withdrawal, you must inform us of your decision to withdraw from this contract by an unequivocal statement. Our details are:\n\n%s", 'plogins-withdraw'),
            $seller !== '' ? $seller : __('(the seller\'s name and address)', 'plogins-withdraw'),
        );

        $parts[] = __('You may use the model withdrawal form below, or the withdrawal function on this site, but it is not obligatory. To meet the withdrawal deadline, it is enough for you to send your communication concerning your exercise of the right of withdrawal before the withdrawal period has expired.', 'plogins-withdraw');

        $parts[] = __('Effects of withdrawal', 'plogins-withdraw');

        $parts[] = __('If you withdraw from this contract, we shall reimburse to you all payments received from you, including the costs of delivery, with the exception of the supplementary costs resulting from your choice of a type of delivery other than the least expensive type of standard delivery offered by us, without undue delay and in any event not later than 14 days from the day on which we are informed about your decision to withdraw from this contract. We will carry out such reimbursement using the same means of payment as you used for the initial transaction, unless you have expressly agreed otherwise; in any event, you will not incur any fees as a result of such reimbursement.', 'plogins-withdraw');

        $parts[] = __('We may withhold reimbursement until we have received the goods back or you have supplied evidence of having sent back the goods, whichever is the earliest.', 'plogins-withdraw');

        $parts[] = __('You shall send back the goods or hand them over to us without undue delay and in any event not later than 14 days from the day on which you communicate your withdrawal from this contract to us. The deadline is met if you send back the goods before the period of 14 days has expired.', 'plogins-withdraw');

        // Only stated when the shop has said so. Art. 14(1) with art. 6(1)(i)
        // makes this sentence the very thing that has to be given BEFORE the
        // contract, so printing it by default would be the plugin quietly
        // creating the notice on which the shop then relies.
        $cost = ReturnPolicy::costSentence();
        if ($cost !== '') {
            $parts[] = $cost;
        }

        $parts[] = __('You are only liable for any diminished value of the goods resulting from handling other than what is necessary to establish the nature, characteristics and functioning of the goods.', 'plogins-withdraw');

        /**
         * Filters the paragraphs of the model withdrawal instructions.
         *
         * Every shop's obligations differ; this is where a shop adds the
         * service-contract or digital-content paragraphs its own catalogue needs.
         *
         * @param list<string> $parts The paragraphs, in order.
         */
        $parts = (array) apply_filters('withdraw/model_instructions', $parts);

        return implode("\n\n", array_filter(array_map('strval', $parts)));
    }

    /**
     * The form intro, when the shop has not written one.
     */
    public static function intro(): string
    {
        return __('Use this form to withdraw from your purchase. Select the items you are withdrawing from, then confirm the declaration on the next step.', 'plogins-withdraw');
    }
}
