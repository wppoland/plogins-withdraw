<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Contract\HasHooks;
use Withdraw\Email\AcceptedEmail;
use Withdraw\Email\AccessLinkEmail;
use Withdraw\Email\AcknowledgementEmail;
use Withdraw\Email\NewRequestEmail;
use Withdraw\Email\ProcessedEmail;
use Withdraw\Email\RejectedEmail;
use Withdraw\Email\UnderReviewEmail;

defined('ABSPATH') || exit;

/**
 * Registers the plugin's WooCommerce emails and makes sure they exist by the
 * time their own triggers fire.
 */
final class EmailService implements HasHooks
{
    /**
     * Actions whose listeners live inside WC_Email constructors, so the mailer
     * has to exist before they fire.
     *
     * @var list<string>
     */
    private const MAILER_BOOT_ACTIONS = [
        'withdraw/declared',
        'withdraw/status_changed',
        'withdraw/link_issued',
    ];

    /** The id that tells us our own classes made it into the mailer. */
    private const CANARY = 'withdraw_acknowledgement';

    public function registerHooks(): void
    {
        add_filter('woocommerce_email_classes', [$this, 'registerEmails']);

        // WooCommerce builds its WC_Email objects lazily, only when something
        // calls WC()->mailer(). Every email here registers its own trigger from
        // its constructor, so on a storefront POST or on admin-post.php those
        // constructors never run and the actions below fire with no listener
        // attached at all. Loading the mailer at priority 1 puts the classes in
        // place before their own priority-10 handlers run on the same action.
        //
        // Hung on our own actions rather than on a request type, so a future
        // REST, WP-CLI or cron caller is covered by the same three lines.
        foreach (self::MAILER_BOOT_ACTIONS as $action) {
            add_action($action, [$this, 'loadMailer'], 1);
        }
    }

    /**
     * Build the mailer, and make sure our classes are actually in it.
     *
     * The second half is the trap in its other form: if another plugin called
     * WC()->mailer() before this service registered its filter, WC_Emails was
     * built without our classes and nothing would ever send, with no error
     * anywhere. Topping the array up costs one array lookup per trigger.
     */
    public function loadMailer(): void
    {
        if (! function_exists('WC')) {
            return;
        }

        $mailer = WC()->mailer();
        if (! $mailer instanceof \WC_Emails) {
            return;
        }

        if (! isset($mailer->emails[self::CANARY])) {
            $mailer->emails = $this->registerEmails($mailer->emails);
        }
    }

    /**
     * @param array<string, \WC_Email> $emails
     * @return array<string, \WC_Email>
     */
    public function registerEmails(array $emails): array
    {
        // The array key is what WooCommerce lowercases into the settings screen
        // section, so it has to equal each class's own id.
        $emails['withdraw_acknowledgement'] = new AcknowledgementEmail();
        $emails['withdraw_new_request']     = new NewRequestEmail();
        $emails['withdraw_accepted']        = new AcceptedEmail();
        $emails['withdraw_rejected']        = new RejectedEmail();
        $emails['withdraw_processed']       = new ProcessedEmail();
        $emails['withdraw_under_review']    = new UnderReviewEmail();
        $emails['withdraw_access_link']     = new AccessLinkEmail();

        return $emails;
    }

    /**
     * Every email this plugin registers, id => title, without building the
     * mailer. The admin screen lists them from here.
     *
     * @return array<string, string>
     */
    public static function catalogue(): array
    {
        return [
            'withdraw_acknowledgement' => __('Withdrawal declaration received', 'plogins-withdraw'),
            'withdraw_new_request'     => __('New withdrawal request (to you)', 'plogins-withdraw'),
            'withdraw_accepted'        => __('Withdrawal accepted', 'plogins-withdraw'),
            'withdraw_rejected'        => __('Withdrawal rejected', 'plogins-withdraw'),
            'withdraw_processed'       => __('Withdrawal processed', 'plogins-withdraw'),
            'withdraw_under_review'    => __('Withdrawal under review', 'plogins-withdraw'),
            'withdraw_access_link'     => __('Withdrawal access link', 'plogins-withdraw'),
        ];
    }

    /**
     * Whether a shop has switched one of them off, read from the WooCommerce
     * option rather than from a constructed WC_Email.
     *
     * WC_Email defaults `enabled` to yes, and no option row exists until the
     * shop saves that screen, so an absent option means enabled.
     */
    public static function isEnabled(string $id): bool
    {
        $stored = get_option('woocommerce_' . $id . '_settings', []);
        if (! is_array($stored) || ! isset($stored['enabled'])) {
            return true;
        }

        return $stored['enabled'] === 'yes';
    }

    /** The address the shop notification really goes to, WooCommerce field first. */
    public static function effectiveAdminRecipient(): string
    {
        $stored = get_option('woocommerce_withdraw_new_request_settings', []);
        $saved  = is_array($stored) ? trim((string) ($stored['recipient'] ?? '')) : '';

        return $saved !== '' ? $saved : NewRequestEmail::defaultRecipient();
    }

    /** Deep link to one email's own section of the WooCommerce settings screen. */
    public static function settingsUrl(string $id): string
    {
        return admin_url('admin.php?page=wc-settings&tab=email&section=' . strtolower($id));
    }
}
