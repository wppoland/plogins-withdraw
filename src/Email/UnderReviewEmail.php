<?php

declare(strict_types=1);

namespace Withdraw\Email;

defined('ABSPATH') || exit;

/**
 * Sent when a request is moved back to pending.
 *
 * Kept as its own message because that move already sends mail today, and
 * dropping it would replace an existing message with silence.
 */
final class UnderReviewEmail extends AbstractStatusEmail
{
    public function __construct()
    {
        $this->id          = 'withdraw_under_review';
        $this->title       = __('Withdrawal under review', 'plogins-withdraw');
        $this->description = __('Sent to the customer when you move a withdrawal request back to pending.', 'plogins-withdraw');

        parent::__construct();
    }

    protected function status(): string
    {
        return 'pending';
    }

    protected function get_default_body(): string
    {
        return __('Your withdrawal request is being reviewed. We will write again as soon as there is an outcome.', 'plogins-withdraw');
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal request for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal request under review', 'plogins-withdraw');
    }
}
