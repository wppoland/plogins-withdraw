<?php

declare(strict_types=1);

namespace Withdraw\Email;

use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * The shop's own notification that a withdrawal was declared.
 *
 * An admin email, shaped like WC_Email_New_Order: no customer copy, and its own
 * recipient field. That field defaults to the address already saved in
 * `withdraw_settings['notify_email']`, so a shop that never opens the
 * WooCommerce emails screen keeps receiving this exactly where it does today.
 */
final class NewRequestEmail extends AbstractWithdrawEmail
{
    public function __construct()
    {
        $this->id             = 'withdraw_new_request';
        $this->customer_email = false;
        $this->title          = __('New withdrawal request', 'plogins-withdraw');
        $this->description    = __('Sent to the shop when a customer declares a withdrawal. The admin log under WooCommerce, Withdrawal Requests lists every request whether or not this message is switched on.', 'plogins-withdraw');
        $this->template_base  = WITHDRAW_DIR . 'templates/';
        $this->template_html  = 'emails/withdraw-new-request.php';
        $this->template_plain = 'emails/plain/withdraw-new-request.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{request_id}'   => '',
        ];

        add_action('withdraw/declared', [$this, 'triggerFromDeclaration'], 10, 3);

        parent::__construct();

        $this->recipient = $this->get_option('recipient', self::defaultRecipient());
    }

    /**
     * Where this went before it was a WooCommerce email: the plugin's own
     * notification address, then the site admin.
     */
    public static function defaultRecipient(): string
    {
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);
        $settings = is_array($stored) ? $stored : [];
        $notify   = trim((string) ($settings['notify_email'] ?? ''));

        return $notify !== '' ? $notify : (string) get_option('admin_email');
    }

    public function triggerFromDeclaration(int $id, ?\WC_Order $order = null, ?int $submittedAt = null): void
    {
        unset($order, $submittedAt);

        if (! $this->prepare($id)) {
            return;
        }

        // prepare() points the message at the customer, which is right for every
        // other email here and wrong for this one.
        $this->recipient = $this->get_option('recipient', self::defaultRecipient());

        $this->dispatch();
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        $fields    = is_array($this->form_fields) ? $this->form_fields : [];
        $recipient = [
            'title'       => __('Recipient(s)', 'plogins-withdraw'),
            'type'        => 'text',
            /* translators: %s: the address this message goes to when the field is left empty */
            'description' => sprintf(__('Comma-separated. Leave blank to use %s, which is the notification address set under WooCommerce, Withdrawal.', 'plogins-withdraw'), '<code>' . esc_attr(self::defaultRecipient()) . '</code>'),
            'placeholder' => self::defaultRecipient(),
            'default'     => '',
            'desc_tip'    => true,
        ];

        $ordered = [];
        foreach ($fields as $key => $field) {
            $ordered[$key] = $field;
            if ($key === 'enabled') {
                $ordered['recipient'] = $recipient;
            }
        }
        if (! isset($ordered['recipient'])) {
            $ordered['recipient'] = $recipient;
        }

        $this->form_fields = $ordered;
    }

    public function get_default_subject(): string
    {
        return __('[{site_title}] New withdrawal request #{request_id} for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('New withdrawal request', 'plogins-withdraw');
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        unset($plainText);

        return [
            'items'          => $this->items(),
            'reason'         => (string) ($this->request->reason ?? ''),
            'customer_email' => (string) ($this->request->customer_email ?? ''),
            'customer_name'  => (string) ($this->request->customer_name ?? ''),
            'submitted_at'   => $this->submittedAt(),
            'request_id'     => (int) ($this->request->id ?? 0),
        ];
    }
}
