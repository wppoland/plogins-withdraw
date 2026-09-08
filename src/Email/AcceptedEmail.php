<?php

declare(strict_types=1);

namespace Withdraw\Email;

use Withdraw\Service\ReturnPolicy;

defined('ABSPATH') || exit;

/**
 * Sent when the shop accepts a withdrawal.
 *
 * The only status message with a template of its own, because it is the only one
 * that has to carry art. 14(1) information: by when the goods go back, where
 * they go, and who pays for sending them.
 */
final class AcceptedEmail extends AbstractStatusEmail
{
    /**
     * Art. 14(1): 14 days from the day the consumer communicated the
     * withdrawal.
     *
     * Deliberately not the `period_days` setting. That measures the withdrawal
     * window, a different clock that starts at delivery, and reusing it here
     * would state a return deadline the law does not give.
     */
    private const RETURN_DAYS = 14;

    public function __construct()
    {
        $this->id             = 'withdraw_accepted';
        $this->title          = __('Withdrawal accepted', 'plogins-withdraw');
        $this->description    = __('Sent to the customer when you accept a withdrawal. It states the return deadline, the return address and who bears the cost of sending the goods back, which is what Article 14(1) asks for. Set the address and the cost rule under WooCommerce, Withdrawal.', 'plogins-withdraw');
        $this->template_html  = 'emails/withdraw-accepted.php';
        $this->template_plain = 'emails/plain/withdraw-accepted.php';

        parent::__construct();
    }

    protected function status(): string
    {
        return 'accepted';
    }

    protected function get_default_body(): string
    {
        return __('Your withdrawal has been accepted.', 'plogins-withdraw');
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal for order #{order_number} has been accepted', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal accepted', 'plogins-withdraw');
    }

    /**
     * The return deadline, counted from the declaration and never from the send.
     *
     * The 14 days run from the day the consumer said they were withdrawing, not
     * from the day the shop got around to accepting it, so a message sent a week
     * later still names the real date. 0 when the row carries no timestamp,
     * which suppresses the sentence rather than inventing a date.
     */
    public function returnDeadline(): int
    {
        $submitted = $this->submittedAt();

        return $submitted > 0 ? $submitted + (self::RETURN_DAYS * DAY_IN_SECONDS) : 0;
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        return array_merge(parent::templateVars($plainText), [
            'declaration'      => (string) ($this->request->declaration ?? ''),
            'submitted_at'     => $this->submittedAt(),
            'items'            => $this->items(),
            'return_deadline'  => $this->returnDeadline(),
            'return_days'      => self::RETURN_DAYS,
            // Resolved here so a theme override of the template receives exactly
            // what the shop was shown on the settings screen.
            'return_address'   => ReturnPolicy::address(),
            'return_cost_text' => ReturnPolicy::costSentence(),
        ]);
    }
}
