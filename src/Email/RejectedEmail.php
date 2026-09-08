<?php

declare(strict_types=1);

namespace Withdraw\Email;

defined('ABSPATH') || exit;

/** Sent when the shop marks a withdrawal request as rejected. */
final class RejectedEmail extends AbstractStatusEmail
{
    public function __construct()
    {
        $this->id          = 'withdraw_rejected';
        $this->title       = __('Withdrawal rejected', 'plogins-withdraw');
        $this->description = __('Sent to the customer when you mark a withdrawal request as rejected.', 'plogins-withdraw');

        parent::__construct();
    }

    protected function status(): string
    {
        return 'rejected';
    }

    protected function get_default_body(): string
    {
        return __('Your withdrawal request could not be accepted. If you believe this is a mistake, reply to this email and we will look at it again.', 'plogins-withdraw');
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal request for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal request rejected', 'plogins-withdraw');
    }
}
