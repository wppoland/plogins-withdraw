<?php

declare(strict_types=1);

namespace Withdraw\Email;

defined('ABSPATH') || exit;

/**
 * Art. 11a(4): the acknowledgement of a withdrawal declaration, on a durable
 * medium, sent to the consumer as soon as the declaration is recorded.
 *
 * It repeats the declaration back in the words that were on screen (read from
 * the row, not rebuilt) and states the date AND time it was submitted. A bare
 * "we got it" would not do that job.
 */
final class AcknowledgementEmail extends AbstractWithdrawEmail
{
    public function __construct()
    {
        $this->id             = 'withdraw_acknowledgement';
        $this->customer_email = true;
        $this->title          = __('Withdrawal declaration received', 'plogins-withdraw');
        $this->description    = __('Sent to the customer the moment a withdrawal declaration is recorded. It repeats the declaration back and states when it was submitted, which is what Article 11a(4) asks for, so switch it off only if you already send an acknowledgement of your own.', 'plogins-withdraw');
        $this->template_base  = WITHDRAW_DIR . 'templates/';
        $this->template_html  = 'emails/withdraw-acknowledgement.php';
        $this->template_plain = 'emails/plain/withdraw-acknowledgement.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{request_id}'   => '',
        ];

        add_action('withdraw/declared', [$this, 'triggerFromDeclaration'], 10, 3);

        parent::__construct();
    }

    /**
     * The request id is the only argument this message reads. The order and the
     * timestamp come off the row, so a re-send says what the first send said.
     */
    public function triggerFromDeclaration(int $id, ?\WC_Order $order = null, ?int $submittedAt = null): void
    {
        unset($order, $submittedAt);
        $this->trigger($id);
    }

    public function trigger(int $requestId): void
    {
        if (! $this->prepare($requestId)) {
            return;
        }

        // A shop that switched this off has said it sends its own; only a
        // failure of a message we were meant to send is worth a note.
        if (! $this->is_enabled()) {
            return;
        }

        if (! $this->dispatch()) {
            // A statutory record that fails silently is the worst outcome here,
            // so whoever opens the order next sees it happened without knowing
            // this plugin exists.
            if ($this->object instanceof \WC_Order) {
                $this->object->add_order_note(
                    sprintf(
                        /* translators: %d: withdrawal request id */
                        __('The withdrawal acknowledgement for declaration #%d could not be delivered. The declaration itself is recorded.', 'plogins-withdraw'),
                        $requestId,
                    ),
                );
            }
        }
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal request for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal declaration received', 'plogins-withdraw');
    }

    public function get_default_additional_content(): string
    {
        return __('Keep this message: it is your confirmation that the declaration reached us, and the date above is the date it takes effect from.', 'plogins-withdraw');
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        unset($plainText);

        return [
            'declaration'  => (string) ($this->request->declaration ?? ''),
            'submitted_at' => $this->submittedAt(),
            'items'        => $this->items(),
            'reason'       => (string) ($this->request->reason ?? ''),
        ];
    }
}
