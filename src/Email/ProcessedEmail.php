<?php

declare(strict_types=1);

namespace Withdraw\Email;

defined('ABSPATH') || exit;

/** Sent when the shop marks a withdrawal request as processed and refunded. */
final class ProcessedEmail extends AbstractStatusEmail
{
    public function __construct()
    {
        $this->id          = 'withdraw_processed';
        $this->title       = __('Withdrawal processed', 'plogins-withdraw');
        $this->description = __('Sent to the customer when you mark a withdrawal request as processed. The refund itself is made in the normal order screen; this only tells the customer it happened.', 'plogins-withdraw');

        parent::__construct();
    }

    protected function status(): string
    {
        return 'processed';
    }

    protected function get_default_body(): string
    {
        return __('Your withdrawal has been processed and the refund has been issued. Depending on your bank it can take a few working days to appear.', 'plogins-withdraw');
    }

    public function get_default_subject(): string
    {
        return __('Your withdrawal request for order #{order_number}', 'plogins-withdraw');
    }

    public function get_default_heading(): string
    {
        return __('Withdrawal processed', 'plogins-withdraw');
    }
}
