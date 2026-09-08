<?php

declare(strict_types=1);

namespace Withdraw\Email;

defined('ABSPATH') || exit;

/**
 * Base for the four messages that answer a status change made in the admin log.
 *
 * Four separate emails rather than one parameterised message, because a shop
 * needs to reword or switch off a rejection without touching a refund
 * confirmation. Three of them share one template pair, because they differ only
 * in a sentence; the acceptance has its own, because it is the only one carrying
 * the return address and the cost rule.
 */
abstract class AbstractStatusEmail extends AbstractWithdrawEmail
{
    public function __construct()
    {
        $this->customer_email = true;
        $this->template_base  = WITHDRAW_DIR . 'templates/';
        $this->template_html  = $this->template_html ?: 'emails/withdraw-status.php';
        $this->template_plain = $this->template_plain ?: 'emails/plain/withdraw-status.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{request_id}'   => '',
        ];

        add_action('withdraw/status_changed', [$this, 'triggerFromStatusChange'], 10, 3);

        parent::__construct();
    }

    /** The one status this message answers to. */
    abstract protected function status(): string;

    /** Today's wording for this status, before the shop or a filter touches it. */
    abstract protected function get_default_body(): string;

    public function triggerFromStatusChange(int $id, string $status, string $previousStatus = ''): void
    {
        unset($previousStatus);

        if ($status !== $this->status()) {
            return;
        }

        if (! $this->prepare($id)) {
            return;
        }

        $this->dispatch();
    }

    /**
     * The body sentence the template prints.
     *
     * The filter is the one the plugin has always applied to this message, with
     * the same three arguments, so a shop's existing customisation keeps
     * rewriting the same text.
     */
    public function bodyText(): string
    {
        /**
         * Filters the status-change message sent to the customer.
         *
         * @param string      $body    The message body.
         * @param string      $status  The new status.
         * @param object|null $request The withdrawal request row.
         */
        return (string) apply_filters('withdraw/status_email_body', $this->get_default_body(), $this->status(), $this->request);
    }

    /** @return array<string, mixed> */
    protected function templateVars(bool $plainText): array
    {
        unset($plainText);

        return [
            'body_text'  => $this->bodyText(),
            'request_id' => (int) ($this->request->id ?? 0),
        ];
    }
}
