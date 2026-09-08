<?php

declare(strict_types=1);

namespace Withdraw\Email;

use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * The one-time link that opens the withdrawal form for a guest.
 *
 * Only ever sent while the "Guest access" setting is on. is_enabled() answers to
 * that setting as well as to the WooCommerce switch, so the two cannot
 * contradict each other and the plugin setting stays the real one.
 */
final class AccessLinkEmail extends AbstractWithdrawEmail
{
    private string $body = '';

    private string $subjectOverride = '';

    public function __construct()
    {
        $this->id             = 'withdraw_access_link';
        $this->customer_email = true;
        $this->title          = __('Withdrawal access link', 'plogins-withdraw');
        $this->description    = __('The single-use link that opens the withdrawal form, sent to the address on the order. Only used while "Email a one-time link" is on under WooCommerce, Withdrawal. With that setting on and this message off, a guest cannot withdraw at all.', 'plogins-withdraw');
        $this->template_base  = WITHDRAW_DIR . 'templates/';
        $this->template_html  = 'emails/withdraw-access-link.php';
        $this->template_plain = 'emails/plain/withdraw-access-link.php';
        $this->placeholders   = [
            '{order_number}' => '',
        ];

        add_action('withdraw/link_issued', [$this, 'trigger'], 10, 3);

        parent::__construct();
    }

    /**
     * The plugin setting is the single switch.
     *
     * parent::is_enabled() still applies woocommerce_email_enabled_withdraw_access_link,
     * so both seams survive; this only makes sure the message cannot outlive the
     * feature it belongs to.
     */
    public function is_enabled(): bool
    {
        if (! parent::is_enabled()) {
            return false;
        }

        $stored   = get_option(Migrator::OPTION_SETTINGS, []);
        $settings = is_array($stored) ? $stored : [];

        return ! empty($settings['magic_link']);
    }

    public function trigger(\WC_Order $order, string $url, int $minutes): void
    {
        $this->object    = $order;
        $this->recipient = (string) $order->get_billing_email();

        $this->placeholders['{order_number}'] = (string) $order->get_order_number();

        $mail = [
            // The shop's own subject field feeds the filter, rather than the
            // filter being handed a constant: otherwise the WooCommerce field
            // would accept a subject and silently decide nothing.
            'subject' => $this->get_subject(),
            'body'    => sprintf(
                /* translators: 1: order number, 2: link URL, 3: number of minutes the link stays valid */
                __("Somebody asked to withdraw from order #%1\$s.\n\nOpen this link to fill in the withdrawal form:\n%2\$s\n\nThis link works for %3\$d minutes and can be used once.\n\nIf you did not ask for this, you can ignore this message. Nothing has been submitted.", 'plogins-withdraw'),
                $order->get_order_number(),
                $url,
                $minutes,
            ),
            'headers' => [],
        ];

        /**
         * Filters the one-time withdrawal link email.
         *
         * The `headers` key is no longer honoured: WooCommerce builds the
         * headers for its own emails. Use `woocommerce_email_headers` instead.
         *
         * @param array{subject:string, body:string, headers:array<int, string>} $mail
         * @param \WC_Order $order The order the link was issued for.
         * @param string    $url   The link itself.
         */
        $mail = apply_filters('withdraw/magic_link_email', $mail, $order, $url);

        $this->subjectOverride = (string) ($mail['subject'] ?? '');
        $this->body            = (string) ($mail['body'] ?? '');

        if (! $this->is_enabled() || ! $this->get_recipient()) {
            // Nothing is reported anywhere the visitor can see it: saying the
            // mail failed would say the order exists.
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->subjectOverride !== '' ? $this->subjectOverride : $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments(),
        );
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal link for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Your withdrawal link', 'plogins-withdraw');
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        unset($plainText);

        return [
            'body_text' => $this->body,
        ];
    }
}
