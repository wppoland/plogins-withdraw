<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * The public withdrawal flow: the [withdraw_form] shortcode. Two steps, both
 * nonce-protected:
 *   1. look up an order by number + billing email (works for guests too);
 *   2. pick items/quantities + reason, then submit the withdrawal declaration.
 *
 * On submit it stores a request (RequestRepository) and emails the customer and
 * the shop. It never moves money — the merchant refunds in the order screen —
 * matching the legal model: the customer declares withdrawal, the shop acts.
 */
final class WithdrawalService implements HasHooks
{
    public function __construct(private readonly RequestRepository $repository)
    {
    }

    public function registerHooks(): void
    {
        add_shortcode('withdraw_form', [$this, 'renderShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function enqueueAssets(): void
    {
        wp_register_style('plogins-withdraw', WITHDRAW_URL . 'assets/css/withdraw.css', [], \Withdraw\VERSION);
        wp_register_script('plogins-withdraw', WITHDRAW_URL . 'assets/js/withdraw.js', [], \Withdraw\VERSION, true);
    }

    public function renderShortcode(): string
    {
        wp_enqueue_style('plogins-withdraw');
        wp_enqueue_script('plogins-withdraw');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in the handlers below.
        $step = isset($_POST['withdraw_step']) ? sanitize_key(wp_unslash($_POST['withdraw_step'])) : '';

        if ('confirm' === $step) {
            return $this->handleSubmit();
        }
        if ('items' === $step) {
            return $this->renderItemsStep();
        }
        return $this->renderLookupStep();
    }

    /* --------------------------------------------------------------------- */

    private function renderLookupStep(string $error = ''): string
    {
        $s = $this->settings();
        ob_start();
        $vars = [
            'error'    => $error,
            'period'   => (int) $s['period_days'],
            'intro'    => (string) $s['intro_text'],
            'nonce'    => wp_create_nonce('withdraw_lookup'),
        ];
        $this->template('form-lookup.php', $vars);
        return (string) ob_get_clean();
    }

    private function renderItemsStep(): string
    {
        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_lookup')) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $orderId = isset($_POST['withdraw_order']) ? absint(wp_unslash($_POST['withdraw_order'])) : 0;
        $email   = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';

        $order = $this->lookupOrder($orderId, $email);
        if (! $order instanceof \WC_Order) {
            return $this->renderLookupStep(__('We could not find an order with that number and email.', 'plogins-withdraw'));
        }

        $eligibility = $this->eligibility($order);
        if (! $eligibility['eligible']) {
            return $this->renderLookupStep($eligibility['reason']);
        }

        $s = $this->settings();
        ob_start();
        $this->template('form-items.php', [
            'order'    => $order,
            'email'    => $email,
            'deadline' => $eligibility['deadline'],
            'model'    => (string) $s['model_form_text'],
            'nonce'    => wp_create_nonce('withdraw_confirm'),
        ]);
        return (string) ob_get_clean();
    }

    private function handleSubmit(): string
    {
        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_confirm')) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $orderId = isset($_POST['withdraw_order']) ? absint(wp_unslash($_POST['withdraw_order'])) : 0;
        $email   = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';
        $order   = $this->lookupOrder($orderId, $email);
        if (! $order instanceof \WC_Order) {
            return $this->renderLookupStep(__('We could not find an order with that number and email.', 'plogins-withdraw'));
        }
        if (! $this->eligibility($order)['eligible']) {
            return $this->renderLookupStep(__('This order is no longer eligible for withdrawal.', 'plogins-withdraw'));
        }

        // Selected quantities: withdraw_qty[<item_id>] => qty. Each value is cast
        // with absint() in the loop below; the nonce is verified above.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $rawQty = isset($_POST['withdraw_qty']) && is_array($_POST['withdraw_qty']) ? wp_unslash($_POST['withdraw_qty']) : [];
        $items  = [];
        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $qty = isset($rawQty[$itemId]) ? absint($rawQty[$itemId]) : 0;
            if ($qty < 1) {
                continue;
            }
            $qty = min($qty, (int) $item->get_quantity());
            $items[] = [
                'product_id' => (int) $item->get_product_id(),
                'name'       => (string) $item->get_name(),
                'qty'        => $qty,
            ];
        }

        if ($items === []) {
            return $this->renderLookupStep(__('Please select at least one item to withdraw from.', 'plogins-withdraw'));
        }

        $reason = isset($_POST['withdraw_reason']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_reason'])) : '';
        $token  = wp_generate_password(24, false);

        $id = $this->repository->create($order->get_id(), $email, $items, $reason, $token);

        $this->notify($order, $email, $items, $reason, $id);

        ob_start();
        $this->template('confirmation.php', [
            'order' => $order,
            'items' => $items,
            'id'    => $id,
        ]);
        return (string) ob_get_clean();
    }

    /* --------------------------------------------------------------------- */

    private function lookupOrder(int $orderId, string $email): ?\WC_Order
    {
        if ($orderId < 1 || ! is_email($email)) {
            return null;
        }
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return null;
        }
        // Constant-time-ish email match against the order billing email (guest-safe).
        if (strtolower(trim($order->get_billing_email())) !== strtolower(trim($email))) {
            return null;
        }
        return $order;
    }

    /**
     * @return array{eligible:bool, reason:string, deadline:int}
     */
    private function eligibility(\WC_Order $order): array
    {
        $s = $this->settings();

        $allowed = (array) $s['eligible_statuses'];
        if (! in_array($order->get_status(), $allowed, true)) {
            return ['eligible' => false, 'reason' => __('This order is not in a status that can be withdrawn.', 'plogins-withdraw'), 'deadline' => 0];
        }

        // The withdrawal window starts at completion (delivery proxy) or, if the
        // order was never marked completed, at creation. Configurable length.
        $completed = $order->get_date_completed();
        $start     = $completed ? $completed->getTimestamp() : ($order->get_date_created()?->getTimestamp() ?? time());
        $deadline  = $start + ((int) $s['period_days'] * DAY_IN_SECONDS);

        if (time() > $deadline) {
            return ['eligible' => false, 'reason' => __('The withdrawal period for this order has ended.', 'plogins-withdraw'), 'deadline' => $deadline];
        }

        if ($this->repository->openCountForOrder($order->get_id()) > 0) {
            return ['eligible' => false, 'reason' => __('A withdrawal request for this order is already being processed.', 'plogins-withdraw'), 'deadline' => $deadline];
        }

        return ['eligible' => true, 'reason' => '', 'deadline' => $deadline];
    }

    /**
     * @param array<int, array{product_id:int, name:string, qty:int}> $items
     */
    private function notify(\WC_Order $order, string $email, array $items, string $reason, int $id): void
    {
        $s        = $this->settings();
        $lines    = array_map(static fn (array $i): string => sprintf('- %s x%d', $i['name'], $i['qty']), $items);
        $itemList = implode("\n", $lines);
        $orderNo  = $order->get_order_number();

        // Customer confirmation.
        $customerSubject = sprintf(
            /* translators: %s: order number */
            __('Your withdrawal request for order #%s', 'plogins-withdraw'),
            $orderNo,
        );
        $customerBody = sprintf(
            /* translators: 1: order number, 2: item list, 3: reason */
            __("We received your withdrawal request for order #%1\$s.\n\nItems:\n%2\$s\n\nReason: %3\$s\n\nWe will confirm the next steps by email.", 'plogins-withdraw'),
            $orderNo,
            $itemList,
            $reason !== '' ? $reason : __('(none given)', 'plogins-withdraw'),
        );
        wp_mail($email, $customerSubject, $customerBody);

        // Shop notification.
        $adminEmail = ! empty($s['notify_email']) ? (string) $s['notify_email'] : get_option('admin_email');
        $adminSubject = sprintf(
            /* translators: 1: request id, 2: order number */
            __('New withdrawal request #%1$d for order #%2$s', 'plogins-withdraw'),
            $id,
            $orderNo,
        );
        $adminBody = sprintf(
            /* translators: 1: order number, 2: customer email, 3: item list, 4: reason */
            __("A customer submitted a withdrawal request.\n\nOrder: #%1\$s\nCustomer: %2\$s\n\nItems:\n%3\$s\n\nReason: %4\$s", 'plogins-withdraw'),
            $orderNo,
            $email,
            $itemList,
            $reason !== '' ? $reason : __('(none given)', 'plogins-withdraw'),
        );
        wp_mail($adminEmail, $adminSubject, $adminBody);
    }

    /** @param array<string, mixed> $vars */
    private function template(string $file, array $vars): void
    {
        $path = WITHDRAW_DIR . 'templates/' . $file;
        if (! is_readable($path)) {
            return;
        }
        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template vars.
        require $path;
    }
}
