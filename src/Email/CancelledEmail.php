<?php

declare(strict_types=1);

namespace Withdraw\Email;

use Withdraw\Service\EmailService;

defined('ABSPATH') || exit;

/**
 * Tells the shop a customer cancelled a pending withdrawal request.
 *
 * existing-checked: src/Email/NewRequestEmail.php - that message is final, listens on withdraw/declared and prints the declaration; a cancellation needs its own trigger and switch, so it sends to the same address (EmailService::effectiveAdminRecipient()) and reuses the shared status template.
 *
 * An admin email to the address the new-request notification uses. It uses
 * the shared status template, which already prints one sentence, the shop's
 * note and the request and order numbers.
 */
final class CancelledEmail extends AbstractWithdrawEmail
{
    public function __construct()
    {
        $this->id             = 'withdraw_cancelled';
        $this->customer_email = false;
        $this->title          = __('Withdrawal cancelled by the customer', 'plogins-withdraw');
        $this->description    = __('Sent to the shop when a customer cancels a withdrawal request that was still pending.', 'plogins-withdraw');
        $this->template_base  = WITHDRAW_DIR . 'templates/';
        $this->template_html  = 'emails/withdraw-status.php';
        $this->template_plain = 'emails/plain/withdraw-status.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{request_id}'   => '',
        ];

        add_action('withdraw/cancelled', [$this, 'triggerFromCancel'], 10, 1);

        parent::__construct();

        $this->recipient = EmailService::effectiveAdminRecipient();
    }

    public function triggerFromCancel(int $id): void
    {
        if (! $this->prepare($id)) {
            return;
        }
        // prepare() addresses the customer; this one goes to the shop.
        $this->recipient = EmailService::effectiveAdminRecipient();
        $this->dispatch();
    }

    public function get_default_subject(): string
    {
        return __('[{site_title}] Withdrawal request #{request_id} for order #{order_number} cancelled', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal cancelled', 'plogins-withdraw');
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        unset($plainText);

        return [
            'body_text'  => __('The customer cancelled this withdrawal request before you decided on it. It is marked as cancelled in the withdrawal request log.', 'plogins-withdraw'),
            'request_id' => (int) ($this->request->id ?? 0),
            'admin_note' => '',
        ];
    }
}
