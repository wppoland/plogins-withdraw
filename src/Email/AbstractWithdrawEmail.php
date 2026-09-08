<?php

declare(strict_types=1);

namespace Withdraw\Email;

use Withdraw\Plugin;
use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

/**
 * Shared plumbing for every Plogins Withdraw email: load the request row, render
 * the template pair, send.
 *
 * It exists to stop seven classes carrying seven copies of the same
 * get_content_html()/get_content_plain() pair, not to add a layer: each subclass
 * still owns its id, its trigger, its wording and its own template variables.
 */
abstract class AbstractWithdrawEmail extends \WC_Email
{
    /** The withdrawal request row this message is about. */
    public ?object $request = null;

    /**
     * Template variables this message adds on top of the shared ones.
     *
     * @return array<string, mixed>
     */
    abstract protected function templateVars(bool $plainText): array;

    public function get_content_html(): string
    {
        return $this->render((string) $this->template_html, false);
    }

    public function get_content_plain(): string
    {
        return $this->render((string) $this->template_plain, true);
    }

    private function render(string $template, bool $plainText): string
    {
        $shared = [
            'order'              => $this->object instanceof \WC_Order ? $this->object : null,
            'request'            => $this->request,
            'order_number'       => $this->orderNumber(),
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'sent_to_admin'      => ! $this->is_customer_email(),
            'plain_text'         => $plainText,
            'email'              => $this,
        ];

        return wc_get_template_html(
            $template,
            array_merge($shared, $this->templateVars($plainText)),
            '',
            (string) $this->template_base,
        );
    }

    protected function repository(): RequestRepository
    {
        /** @var RequestRepository $repository */
        $repository = Plugin::instance()->container()->get(RequestRepository::class);

        return $repository;
    }

    /**
     * Load the row and point the email at it.
     *
     * Everything the message prints comes from the row, never from the action
     * arguments, so a request can be re-sent later and still say what it said.
     */
    protected function prepare(int $requestId): bool
    {
        $request = $this->repository()->find($requestId);
        if (! is_object($request)) {
            return false;
        }

        $this->request = $request;

        $order        = wc_get_order((int) ($request->order_id ?? 0));
        $this->object = $order instanceof \WC_Order ? $order : null;

        $this->recipient = (string) ($request->customer_email ?? '');

        $this->placeholders['{order_number}'] = $this->orderNumber();
        $this->placeholders['{request_id}']   = (string) ($request->id ?? '');

        return true;
    }

    /** What the shop shows the customer, falling back to the stored order id. */
    protected function orderNumber(): string
    {
        if ($this->object instanceof \WC_Order) {
            return (string) $this->object->get_order_number();
        }

        return (string) ($this->request->order_id ?? '');
    }

    /**
     * Items as they were declared, decoded from the row.
     *
     * @return list<array{name:string, qty:int}>
     */
    protected function items(): array
    {
        $decoded = json_decode((string) ($this->request->items ?? ''), true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'name' => (string) ($item['name'] ?? ''),
                'qty'  => (int) ($item['qty'] ?? 0),
            ];
        }

        return $items;
    }

    /** Site-time timestamp of the declaration, taken from the row that stored it. */
    protected function submittedAt(): int
    {
        $created = (string) ($this->request->created_at ?? '');

        return $created !== '' ? (int) strtotime($created) : 0;
    }

    /** Send, if the shop has left this message on and there is somewhere to send it. */
    protected function dispatch(): bool
    {
        if (! $this->is_enabled() || ! $this->get_recipient()) {
            return false;
        }

        return (bool) $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments(),
        );
    }
}
