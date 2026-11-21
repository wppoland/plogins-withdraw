<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\AccessLink;
use Withdraw\Service\AcknowledgementPdf;
use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

/**
 * One request as its customer sees it: what was declared, where it stands, a
 * PDF copy, and a way to cancel while the shop has not decided yet.
 *
 * existing-checked: src/Service/WithdrawalService.php - that class runs the declaration form (lookup, items, review, confirm) and is already 1,100 lines; the form shortcode hands this view its requests rather than growing a second flow inside it. src/Frontend/MyAccount.php only lists requests and links here.
 *
 * Two ways in, checked on every request, GET or POST: the signed-in owner of
 * the order (customer_id, never the email), or the request's status key from
 * the acknowledgement (AccessLink::statusKey()), which is how a guest returns.
 */
final class RequestStatus implements HasHooks
{
    public function __construct(
        private readonly RequestRepository $repository,
        private readonly AccessLink $links,
        private readonly AcknowledgementPdf $pdf,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('template_redirect', [$this, 'maybeDownload']);
    }

    /** Whether the current form page request is for this view rather than the form. */
    public function wanted(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- routing only; every action verifies its own nonce.
        return isset($_GET['wd_request']) || (isset($_POST['withdraw_step']) && in_array(sanitize_key(wp_unslash($_POST['withdraw_step'])), ['cancel', 'cancel_confirm'], true));
    }

    /** The signed-in owner of the order, or a valid status key. */
    public function authorized(object $request, string $key): bool
    {
        $userId = get_current_user_id();
        if ($userId > 0 && (int) ($request->order_id ?? 0) > 0) {
            $order = wc_get_order((int) $request->order_id);
            if ($order instanceof \WC_Order && (int) $order->get_customer_id() === $userId) {
                return true;
            }
        }

        return $key !== '' && $this->links->statusKeyValid($request, $key);
    }

    /**
     * Cancel a request on its customer's behalf.
     *
     * Only an authorised caller, and only from pending: the status test is in
     * the UPDATE (RequestRepository::cancel()), so a decision the shop saved
     * first always wins.
     */
    public function cancel(int $id, string $key): bool
    {
        $request = $this->repository->find($id);
        if (! is_object($request) || ! $this->authorized($request, $key) || ! $this->repository->cancel($id)) {
            return false;
        }

        $order = wc_get_order((int) $request->order_id);
        $order = $order instanceof \WC_Order ? $order : null;
        if ($order !== null) {
            /* translators: %d: withdrawal request id */
            $order->add_order_note(sprintf(__('The customer cancelled withdrawal declaration #%d.', 'plogins-withdraw'), $id));
        }

        /** This action is documented in src/Admin/RequestsAdmin.php */
        do_action('withdraw/status_changed', $id, 'cancelled', 'pending');

        /**
         * Fires once a customer has cancelled a pending withdrawal request.
         *
         * The shop notification is a WooCommerce email listening here. A
         * returns add-on can close whatever it opened for the request.
         *
         * @param int            $id    Request id.
         * @param \WC_Order|null $order The order, or null for a declaration that matched none.
         */
        do_action('withdraw/cancelled', $id, $order);

        return true;
    }

    /**
     * Link to the status view. With the key for a guest (the acknowledgement,
     * the confirmation screen); without it for the signed-in owner.
     */
    public function statusUrl(object $request, bool $withKey): string
    {
        $base = WithdrawLink::formUrl(WithdrawLink::settings());
        if ($base === '') {
            return '';
        }

        $args = ['wd_request' => (int) $request->id];
        if ($withKey) {
            $args['wd_rkey'] = $this->links->statusKey($request);
        }

        return add_query_arg($args, $base);
    }

    public function pdfUrl(object $request, bool $withKey): string
    {
        $base = WithdrawLink::formUrl(WithdrawLink::settings());
        if ($base === '') {
            return '';
        }

        $args = [
            'withdraw_pdf' => (int) $request->id,
            '_wpnonce'     => wp_create_nonce('withdraw_pdf_' . (int) $request->id),
        ];
        if ($withKey) {
            $args['wd_rkey'] = $this->links->statusKey($request);
        }

        return add_query_arg($args, $base);
    }

    /**
     * The PDF download. Nonce, then ownership, then the file with headers that
     * keep it out of every cache. Nothing is written to disk.
     */
    public function maybeDownload(): void
    {
        if (! isset($_GET['withdraw_pdf'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
            return;
        }

        $id    = absint(wp_unslash($_GET['withdraw_pdf'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $deny  = static function (): void {
            wp_die(
                esc_html__('This download link is not valid any more. Open the withdrawal status page again and download it from there.', 'plogins-withdraw'),
                esc_html__('Withdrawal', 'plogins-withdraw'),
                ['response' => 403],
            );
        };

        $request = wp_verify_nonce($nonce, 'withdraw_pdf_' . $id) ? $this->repository->find($id) : null;
        $pdf     = is_object($request) && $this->authorized($request, $this->keyFromRequest()) ? $this->pdf->render($request) : '';
        if ($pdf === '') {
            $deny(); // Ends the request.
        }

        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="withdrawal-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Robots-Tag: noindex, nofollow');
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF built from escaped PDF strings, sent as an attachment.
        exit;
    }

    /** The status view, including the two steps of a cancellation. */
    public function render(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification -- each POST step verifies its own nonce below; the GET view changes nothing.
        $id   = isset($_POST['withdraw_request']) ? absint(wp_unslash($_POST['withdraw_request'])) : (isset($_GET['wd_request']) ? absint(wp_unslash($_GET['wd_request'])) : 0);
        $step = isset($_POST['withdraw_step']) ? sanitize_key(wp_unslash($_POST['withdraw_step'])) : '';
        $wpn  = isset($_POST['withdraw_nonce']) ? sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification
        $key = $this->keyFromRequest();

        $request = $id > 0 ? $this->repository->find($id) : null;
        if (! is_object($request) || ! $this->authorized($request, $key)) {
            // Same answer for a wrong id and a wrong key, so ids cannot be probed.
            return '<div class="withdraw-form"><p class="withdraw-form__error">'
                . esc_html__('This withdrawal request could not be found, or the link is not valid. Sign in to your account, or use the link in your acknowledgement email.', 'plogins-withdraw')
                . '</p></div>';
        }

        $notice     = '';
        $confirming = false;
        if ($step === 'cancel' && wp_verify_nonce($wpn, 'withdraw_cancel_' . $id)) {
            $confirming = $request->status === 'pending';
        } elseif ($step === 'cancel_confirm' && wp_verify_nonce($wpn, 'withdraw_cancel_confirm_' . $id)) {
            $done    = $this->cancel($id, $key);
            $request = $this->repository->find($id) ?? $request;
            $notice  = match (true) {
                $done                             => __('Your withdrawal request has been cancelled and the shop has been told.', 'plogins-withdraw'),
                $request->status === 'cancelled'  => __('This withdrawal request is already cancelled.', 'plogins-withdraw'),
                default                           => __('This withdrawal request could not be cancelled, because the shop has already dealt with it.', 'plogins-withdraw'),
            };
        } elseif ($step !== '') {
            $notice = __('Security check failed. Please try again.', 'plogins-withdraw');
        }

        $order = wc_get_order((int) $request->order_id);
        $items = json_decode((string) $request->items, true);

        wp_enqueue_style('plogins-withdraw');

        ob_start();
        wc_get_template('withdraw-request-status.php', [
            'withdraw_request'    => $request,
            'withdraw_order'      => $order instanceof \WC_Order ? $order : null,
            'withdraw_items'      => is_array($items) ? $items : [],
            'withdraw_steps'      => $this->steps($request),
            'withdraw_key'        => $key,
            'withdraw_notice'     => $notice,
            'withdraw_confirming' => $confirming,
            'withdraw_can_cancel' => $request->status === 'pending',
            'withdraw_pdf_url'    => $this->pdfUrl($request, $key !== ''),
        ], '', WITHDRAW_DIR . 'templates/');

        return (string) ob_get_clean();
    }

    /**
     * The steps a request goes through, with the dates the row holds: when it
     * was received, and when its current status was set.
     *
     * @return list<array{label:string, state:string, date:string}>
     */
    public function steps(object $request): array
    {
        $status  = (string) $request->status;
        $date    = static fn (string $mysql): string => $mysql !== '' && ! str_starts_with($mysql, '0000') ? (string) mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $mysql) : '';
        $changed = $date((string) ($request->updated_at ?? ''));
        $steps   = [['label' => __('Received', 'plogins-withdraw'), 'state' => 'done', 'date' => $date((string) $request->created_at)]];

        if ($status === 'cancelled') {
            $steps[] = ['label' => __('Cancelled by you', 'plogins-withdraw'), 'state' => 'current', 'date' => $changed];
            return $steps;
        }

        $steps[] = ['label' => __('Under review', 'plogins-withdraw'), 'state' => $status === 'pending' ? 'current' : 'done', 'date' => ''];

        $decided = match ($status) {
            'accepted'  => ['label' => __('Accepted', 'plogins-withdraw'), 'state' => 'current', 'date' => $changed],
            'rejected'  => ['label' => __('Rejected', 'plogins-withdraw'), 'state' => 'current', 'date' => $changed],
            'processed' => ['label' => __('Accepted', 'plogins-withdraw'), 'state' => 'done', 'date' => ''],
            default     => ['label' => __('Accepted or rejected', 'plogins-withdraw'), 'state' => 'todo', 'date' => ''],
        };
        $steps[] = $decided;

        if ($status !== 'rejected') {
            $steps[] = ['label' => __('Processed', 'plogins-withdraw'), 'state' => $status === 'processed' ? 'current' : 'todo', 'date' => $status === 'processed' ? $changed : ''];
        }

        return $steps;
    }

    private function keyFromRequest(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification -- the key is the credential; actions verify their own nonce.
        $raw = isset($_POST['withdraw_rkey']) ? sanitize_text_field(wp_unslash($_POST['withdraw_rkey'])) : (isset($_GET['wd_rkey']) ? sanitize_text_field(wp_unslash($_GET['wd_rkey'])) : '');
        // phpcs:enable WordPress.Security.NonceVerification

        return $this->links->sanitizeToken($raw);
    }
}
