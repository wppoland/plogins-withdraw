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
 * the shop. It never moves money, the merchant refunds in the order screen, 
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
        // Art. 11a(3) wants the confirmation to be its own control, so selecting
        // items and confirming the declaration cannot be the same click.
        if ('review' === $step) {
            return $this->renderReviewStep();
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
            'name'     => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'deadline' => $eligibility['deadline'],
            'model'    => (string) $s['model_form_text'],
            'nonce'    => wp_create_nonce('withdraw_review'),
        ]);
        return (string) ob_get_clean();
    }

    /**
     * The declaration, shown back before it is made.
     *
     * Art. 11a(3) requires a confirmation control that carries no wording other
     * than "confirm withdrawal", which means the consumer has to see what they
     * are confirming on a step of its own. Selecting quantities and declaring
     * withdrawal used to be one submit.
     */
    private function renderReviewStep(): string
    {
        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_review')) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $orderId = isset($_POST['withdraw_order']) ? absint(wp_unslash($_POST['withdraw_order'])) : 0;
        $email   = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';
        $order   = $this->lookupOrder($orderId, $email);

        if (! $order instanceof \WC_Order) {
            return $this->renderLookupStep(__('We could not find an order with that number and email.', 'plogins-withdraw'));
        }

        $eligibility = $this->eligibility($order);
        if (! $eligibility['eligible']) {
            return $this->renderLookupStep($eligibility['reason']);
        }

        $name = isset($_POST['withdraw_name']) ? sanitize_text_field(wp_unslash($_POST['withdraw_name'])) : '';
        if ($name === '') {
            return $this->renderLookupStep(__('Please give the name on the contract so the declaration identifies who is withdrawing.', 'plogins-withdraw'));
        }

        $items = $this->selectedItems($order);
        if ($items === []) {
            return $this->renderLookupStep(__('Please select at least one item to withdraw from.', 'plogins-withdraw'));
        }

        $reason = isset($_POST['withdraw_reason']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_reason'])) : '';

        ob_start();
        $this->template('form-review.php', [
            'order'  => $order,
            'email'  => $email,
            'name'   => $name,
            'items'  => $items,
            'reason' => $reason,
            'statement' => $this->statement($order, $name, $email, $items),
            'nonce'  => wp_create_nonce('withdraw_confirm'),
        ]);
        return (string) ob_get_clean();
    }

    /**
     * The withdrawal statement itself, in the words art. 11a(2) asks for: who is
     * withdrawing, which contract, and how to reach them electronically.
     *
     * @param list<array{item_id:int,product_id:int,name:string,qty:int}> $items
     */
    private function statement(\WC_Order $order, string $name, string $email, array $items): string
    {
        $lines = array_map(static fn (array $i): string => sprintf('- %s x%d', $i['name'], $i['qty']), $items);

        return sprintf(
            /* translators: 1: item list, 2: order number, 3: order date, 4: consumer name, 5: consumer email */
            __("I hereby give notice that I withdraw from my contract of sale of the following goods:\n%1\$s\n\nOrdered under order #%2\$s of %3\$s.\nName of consumer: %4\$s\nContact: %5\$s", 'plogins-withdraw'),
            implode("\n", $lines),
            $order->get_order_number(),
            $order->get_date_created() ? date_i18n(get_option('date_format'), $order->get_date_created()->getTimestamp()) : '',
            $name,
            $email,
        );
    }

    /**
     * Quantities the customer picked, clamped to what the order actually holds.
     *
     * @return list<array{item_id:int,product_id:int,name:string,qty:int}>
     */
    private function selectedItems(\WC_Order $order): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by the caller, values cast with absint below.
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
            $items[] = [
                'item_id'    => (int) $itemId,
                'product_id' => (int) $item->get_product_id(),
                'name'       => (string) $item->get_name(),
                'qty'        => min($qty, (int) $item->get_quantity()),
            ];
        }

        return $items;
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

        // The declaration checkbox carried only the HTML `required` attribute,
        // which any client can skip. This record IS the customer's declaration
        // under art. 11a, so storing one nobody ticked would make the log worth
        // nothing as evidence. Checked on the server, where it counts.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
        if (empty($_POST['withdraw_declare'])) {
            return $this->renderLookupStep(__('Please tick the declaration to confirm you are withdrawing from the contract.', 'plogins-withdraw'));
        }

        $items = $this->selectedItems($order);

        if ($items === []) {
            return $this->renderLookupStep(__('Please select at least one item to withdraw from.', 'plogins-withdraw'));
        }

        $name = isset($_POST['withdraw_name']) ? sanitize_text_field(wp_unslash($_POST['withdraw_name'])) : '';
        if ($name === '') {
            return $this->renderLookupStep(__('Please give the name on the contract so the declaration identifies who is withdrawing.', 'plogins-withdraw'));
        }

        $reason = isset($_POST['withdraw_reason']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_reason'])) : '';
        $token  = wp_generate_password(24, false);

        // Art. 11a(4) makes the acknowledgement state when the declaration was
        // submitted, so the moment is taken once here and used for both the
        // stored row and the email, rather than being re-read later.
        $submittedAt = current_time('timestamp');

        $id = $this->repository->create($order->get_id(), $email, $items, $reason, $token, $name);

        $this->notify($order, $email, $items, $reason, $id, $name, $submittedAt);

        /**
         * Fires once a withdrawal declaration has been recorded.
         *
         * @param int       $id          Request id.
         * @param \WC_Order $order       The order withdrawn from.
         * @param int       $submittedAt Submission timestamp, site time.
         */
        $order->add_order_note(
            sprintf(
                /* translators: 1: request id, 2: item list */
                __("Withdrawal declaration #%1\$d received.\n%2\$s", 'plogins-withdraw'),
                $id,
                implode("\n", array_map(
                    static fn (array $i): string => sprintf('- %s x%d', $i['name'], $i['qty']),
                    $items,
                )),
            ),
        );

        do_action('withdraw/declared', $id, $order, $submittedAt);

        ob_start();
        $this->template('confirmation.php', [
            'order' => $order,
            'items' => $items,
            'id'    => $id,
        ]);
        return (string) ob_get_clean();
    }

    /* --------------------------------------------------------------------- */

    /**
     * Find an order by the number the shop shows, not by its database id.
     *
     * WooCommerce has no core lookup for this, because the number is whatever
     * `woocommerce_order_number` filters it into. The two conventions that
     * cover almost every renumbering plugin are a `_order_number` meta key and
     * a plain numeric id, and the filter is there for anything else.
     */
    private function resolveOrderNumber(string $number): ?\WC_Order
    {
        /**
         * Filters the order resolved from a customer-facing order number.
         *
         * Return a WC_Order to take over the lookup entirely.
         *
         * @param \WC_Order|null $order  Resolved order, null until something resolves it.
         * @param string         $number The number the customer typed.
         */
        $filtered = apply_filters('withdraw/resolve_order_number', null, $number);

        if ($filtered instanceof \WC_Order) {
            return $filtered;
        }

        $found = wc_get_orders([
            'limit'      => 1,
            'return'     => 'ids',
            'meta_key'   => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $number,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);

        if (! empty($found)) {
            $order = wc_get_order((int) $found[0]);

            return $order instanceof \WC_Order ? $order : null;
        }

        return null;
    }

    private function lookupOrder(int $orderId, string $email): ?\WC_Order
    {
        if ($orderId < 1 || ! is_email($email)) {
            return null;
        }
        $order = wc_get_order($orderId);

        // The form asks for the ORDER NUMBER, and every email and heading this
        // plugin prints uses get_order_number(). On stock WooCommerce that is
        // the id, so the two coincide and nobody notices. Any plugin that
        // renumbers orders breaks the pair: the shop shows the customer a
        // number its own form then rejects. Resolve the number properly before
        // giving up.
        if (! $order instanceof \WC_Order || (string) $order->get_order_number() !== (string) $orderId) {
            $resolved = $this->resolveOrderNumber((string) $orderId);

            if ($resolved instanceof \WC_Order) {
                $order = $resolved;
            }
        }

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
    private function notify(\WC_Order $order, string $email, array $items, string $reason, int $id, string $name = '', ?int $submittedAt = null): void
    {
        $submittedAt = $submittedAt ?? current_time('timestamp');
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
        // Art. 11a(4): the acknowledgement is the consumer's proof, on a durable
        // medium, so it repeats the declaration back in full and states the date
        // AND time it was submitted. A bare "we got it" would not do that job.
        $customerBody = sprintf(
            /* translators: 1: order number, 2: submission date and time, 3: the declaration text, 4: reason */
            __("We received your withdrawal declaration for order #%1\$s.\n\nSubmitted on %2\$s.\n\nYour declaration:\n%3\$s\n\nReason given: %4\$s\n\nKeep this message: it is your confirmation that the declaration reached us, and the date above is the date it takes effect from.", 'plogins-withdraw'),
            $orderNo,
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $submittedAt),
            $this->statement($order, $name !== '' ? $name : $email, $email, $items),
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
