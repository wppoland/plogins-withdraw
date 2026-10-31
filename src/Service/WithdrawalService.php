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
    /** Set when the last authorizeOrder() call found no order for a typed number and email. */
    private bool $unmatched = false;

    public function __construct(
        private readonly RequestRepository $repository,
        private readonly AccessLink $links,
        private readonly DigitalConsentService $consent,
        private readonly ProductExclusion $exclusions,
    ) {
    }

    public function registerHooks(): void
    {
        add_shortcode('withdraw_form', [$this, 'renderShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('wp_robots', [$this, 'filterRobots']);
    }

    /**
     * Keep keyed URLs out of search indexes. A crawler that stores one has
     * stored a working credential for as long as it lives.
     *
     * @param array<string, mixed> $robots
     * @return array<string, mixed>
     */
    public function filterRobots(array $robots): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only, no state read or changed.
        if (! isset($_GET['wd_key'])) {
            return $robots;
        }

        unset($robots['index'], $robots['follow']);
        $robots['noindex']  = true;
        $robots['nofollow'] = true;

        return $robots;
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
        if ('link' === $step) {
            return $this->handleLinkRequest();
        }
        if (in_array($step, ['unmatched', 'unmatched_review', 'unmatched_confirm'], true)) {
            return $this->handleUnmatched($step);
        }

        if ($step === '') {
            // A click on an emailed link is a plain GET. The token is the
            // credential, the request changes nothing, and anyone who can make a
            // victim load the URL already holds the URL, so no nonce applies.
            if ($this->keyFromRequest() !== '') {
                return $this->renderItemsStep();
            }
            // WooCommerce has already authenticated a signed-in owner, so mailing
            // them a link would be friction with no security gain. Only while the
            // emailed-link flow is on: with it off this path is unreachable and a
            // shop sees exactly what it sees today.
            if (! empty($this->settings()['magic_link']) && $this->ownerOrder() instanceof \WC_Order) {
                return $this->renderItemsStep();
            }
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
            // Only after a lookup that found nothing, never after a throttle or
            // a bad nonce, and only while the shop has switched it on.
            'offer_unmatched' => $this->unmatched && ! empty($s['accept_unmatched']),
            'period'   => (int) $s['period_days'],
            'intro'    => $this->text((string) $s['intro_text'], [StatutoryText::class, 'intro']),
            'magic'    => ! empty($s['magic_link']),
            'nonce'    => wp_create_nonce('withdraw_lookup'),
        ];
        $this->template('form-lookup.php', $vars);
        return (string) ob_get_clean();
    }

    private function renderItemsStep(): string
    {
        // The posted lookup step still verifies its nonce exactly as before. A
        // keyed link arrives as a GET and carries no form, so there is no nonce
        // to check and nothing a nonce would protect.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified on the next line.
        if (isset($_POST['withdraw_step'])) {
            if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_lookup')) {
                return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
            }
        }

        $error = '';
        $auth  = $this->authorizeOrder($error);
        if ($auth === null) {
            return $this->renderLookupStep($error);
        }

        $order = $auth['order'];

        $eligibility = $this->eligibility($order);
        if (! $eligibility['eligible']) {
            return $this->renderLookupStep($eligibility['reason']);
        }

        $s = $this->settings();
        ob_start();
        $this->template('form-items.php', [
            'order'    => $order,
            'email'    => $auth['email'],
            'key'      => $auth['key'],
            'legacy'   => $auth['legacy'],
            'name'     => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'deadline' => $eligibility['deadline'],
            'late'     => $eligibility['late'],
            'excluded' => $eligibility['excluded'],
            'reasons'  => $eligibility['reasons'],
            'remaining' => $eligibility['remaining'],
            'sealed'   => $this->exclusions->sealedItems($order),
            'model'    => $this->text((string) $s['model_form_text'], [StatutoryText::class, 'modelForm']),
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

        $error = '';
        $auth  = $this->authorizeOrder($error);
        if ($auth === null) {
            return $this->renderLookupStep($error);
        }

        $order = $auth['order'];
        $email = $auth['email'];

        $eligibility = $this->eligibility($order);
        if (! $eligibility['eligible']) {
            return $this->renderLookupStep($eligibility['reason']);
        }

        $name = isset($_POST['withdraw_name']) ? sanitize_text_field(wp_unslash($_POST['withdraw_name'])) : '';
        if ($name === '') {
            return $this->renderLookupStep(__('Please give the name on the contract so the declaration identifies who is withdrawing.', 'plogins-withdraw'));
        }

        $items = $this->selectedItems($order, $eligibility['remaining']);
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
            'key'    => $auth['key'],
            'legacy' => $auth['legacy'],
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
     * Quantities the customer picked, clamped to what is still open on each
     * line: the ordered quantity less earlier declarations, with Art. 16
     * exclusions absent altogether.
     *
     * Clamping HERE is the enforcement point. Leaving a row out of the table is
     * presentation, and a posted quantity does not have to come from a table we
     * rendered. If nothing survives, the caller's existing "select at least one
     * item" error fires.
     *
     * @param array<int,int> $remaining order item id => quantity still withdrawable
     * @return list<array{item_id:int,product_id:int,name:string,qty:int}>
     */
    private function selectedItems(\WC_Order $order, array $remaining): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller.
        $rawQty = isset($_POST['withdraw_qty']) && is_array($_POST['withdraw_qty']) ? map_deep(wp_unslash($_POST['withdraw_qty']), 'absint') : [];
        $items  = [];

        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product || ! isset($remaining[(int) $itemId])) {
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
                'qty'        => min($qty, $remaining[(int) $itemId]),
            ];
        }

        return $items;
    }

    private function handleSubmit(): string
    {
        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_confirm')) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $error = '';
        $auth  = $this->authorizeOrder($error);
        if ($auth === null) {
            return $this->renderLookupStep($error);
        }

        $order = $auth['order'];
        $email = $auth['email'];

        $eligibility = $this->eligibility($order);
        if (! $eligibility['eligible']) {
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

        $items = $this->selectedItems($order, $eligibility['remaining']);

        if ($items === []) {
            return $this->renderLookupStep(__('Please select at least one item to withdraw from.', 'plogins-withdraw'));
        }

        $name = isset($_POST['withdraw_name']) ? sanitize_text_field(wp_unslash($_POST['withdraw_name'])) : '';
        if ($name === '') {
            return $this->renderLookupStep(__('Please give the name on the contract so the declaration identifies who is withdrawing.', 'plogins-withdraw'));
        }

        $reason = isset($_POST['withdraw_reason']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_reason'])) : '';

        // Art. 11a(4) makes the acknowledgement state when the declaration was
        // submitted, so the moment is taken once here and used for both the
        // stored row and the email, rather than being re-read later.
        $submittedAt = current_time('timestamp');

        // The declaration is frozen as the consumer saw it on the review step.
        // Art. 11a(4) makes the acknowledgement their proof, so it has to repeat
        // back those words, not words a later locale or a renamed product would
        // produce.
        $declaration = $this->statement($order, $name, $email, $items);

        // Verifiable SHA-256 receipt hash over the declaration record (Art. 11a(4)).
        $receiptHash = hash('sha256', (string) $order->get_id() . '|' . $email . '|' . $declaration . '|' . (string) $submittedAt);

        $duplicate = $this->repository->pendingDuplicate($order->get_id(), $email, $declaration);
        if ($duplicate > 0) {
            ob_start();
            $this->template('confirmation.php', ['order' => $order, 'items' => $items, 'id' => $duplicate, 'hash' => $receiptHash]);
            return (string) ob_get_clean();
        }

        $id = $this->repository->create($order->get_id(), $email, $items, $reason, $receiptHash, $name, $declaration, $eligibility['late']);

        // Single use means one completed withdrawal per link, not one page view.
        // Consuming on first view would kill the link on step two, break the back
        // button, and be tripped by Outlook Safe Links, Gmail image proxies and
        // browser prefetchers long before the customer clicks.
        if ($auth['key'] !== '') {
            $this->links->consume($auth['key']);
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: request id, 2: item list */
                __("Withdrawal declaration #%1\$d received.\n%2\$s", 'plogins-withdraw'),
                $id,
                implode("\n", array_map(
                    static fn (array $i): string => sprintf('- %s x%d', $i['name'], $i['qty']),
                    $items,
                )),
            ) . ($eligibility['late'] ? "\n" . __('Submitted after the withdrawal deadline. Check the delivery date before you decide.', 'plogins-withdraw') : ''),
        );

        /**
         * Fires once a withdrawal declaration has been recorded.
         *
         * Both messages the declaration produces, the consumer's art. 11a(4)
         * acknowledgement and the shop notification, are WooCommerce emails
         * listening on this action.
         *
         * @param int       $id          Request id.
         * @param \WC_Order $order       The order withdrawn from.
         * @param int       $submittedAt Submission timestamp, site time.
         */
        do_action('withdraw/declared', $id, $order, $submittedAt);

        ob_start();
        $this->template('confirmation.php', [
            'order' => $order,
            'items' => $items,
            'id'    => $id,
            'hash'  => $receiptHash,
        ]);
        return (string) ob_get_clean();
    }

    /* --------------------------------------------------------------------- */

    /**
     * A declaration that matches no order: a mistyped order number, or an
     * address other than the billing one.
     *
     * Art. 11a(2) asks the statement for a name, details identifying the
     * contract and an electronic contact, not for a match the shop can verify,
     * so with `accept_unmatched` on the declaration is recorded for the shop to
     * match by hand instead of being lost. Same three steps as the matched
     * flow, so the confirmation is still its own control (Art. 11a(3)).
     *
     * Only the shop is emailed. The address is unverified, and mailing it would
     * let anyone send the shop's mail, with their own text in it, to any inbox.
     * The consumer gets the statement and its time on screen. A status the shop
     * sets on the row later (accepted, rejected, processed) is emailed to the
     * address given, because by then a person has looked at it.
     */
    private function handleUnmatched(string $step): string
    {
        if (empty($this->settings()['accept_unmatched'])) {
            return $this->renderLookupStep();
        }

        $nonceAction = ['unmatched' => 'withdraw_lookup', 'unmatched_review' => 'withdraw_unmatched', 'unmatched_confirm' => 'withdraw_unmatched_confirm'][$step];
        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), $nonceAction)) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $fields = [
            'number' => $this->orderNumberFromRequest(),
            'email'  => isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '',
            'name'   => isset($_POST['withdraw_name']) ? sanitize_text_field(wp_unslash($_POST['withdraw_name'])) : '',
            'goods'  => isset($_POST['withdraw_goods']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_goods'])) : '',
            'reason' => isset($_POST['withdraw_reason']) ? sanitize_textarea_field(wp_unslash($_POST['withdraw_reason'])) : '',
        ];

        $error = '';
        if ($step !== 'unmatched') {
            if ($fields['name'] === '' || $fields['goods'] === '' || $fields['number'] === '') {
                $error = __('Please fill in your name, the order number and the goods you are withdrawing from.', 'plogins-withdraw');
            } elseif (! is_email($fields['email'])) {
                $error = __('Enter a valid email address.', 'plogins-withdraw');
            }
        }

        if ($step === 'unmatched' || $error !== '') {
            return $this->renderUnmatched('edit', $fields, $error);
        }

        $statement = sprintf(
            /* translators: 1: goods as the consumer described them, 2: order number as typed, 3: consumer name, 4: consumer email */
            __("I hereby give notice that I withdraw from my contract of sale of the following goods:\n%1\$s\n\nOrder number as I have it: %2\$s\nName of consumer: %3\$s\nContact: %4\$s", 'plogins-withdraw'),
            $fields['goods'],
            $fields['number'],
            $fields['name'],
            $fields['email'],
        );

        if ($step === 'unmatched_review') {
            return $this->renderUnmatched('review', $fields, '', $statement);
        }

        if (empty($_POST['withdraw_declare'])) {
            return $this->renderUnmatched('review', $fields, __('Please tick the declaration to confirm you are withdrawing from the contract.', 'plogins-withdraw'), $statement);
        }

        // An unauthenticated write, so it is capped like the lookup it replaces.
        if ($this->links->lookupThrottled($fields['email'])) {
            return $this->renderLookupStep(__('Too many attempts. Please try again in an hour.', 'plogins-withdraw'));
        }

        $submittedAt = current_time('timestamp');
        $receiptHash = hash('sha256', '0|' . $fields['email'] . '|' . $statement . '|' . (string) $submittedAt);
        // A replayed confirm shows the same receipt instead of a second row.
        if ($this->repository->pendingDuplicate(0, $fields['email'], $statement) > 0) {
            return $this->renderUnmatched('done', $fields, '', $statement, $submittedAt, $receiptHash);
        }
        $id          = $this->repository->create(0, $fields['email'], [], $fields['reason'], $receiptHash, $fields['name'], $statement, false, $fields['number']);

        /**
         * Fires once a declaration that matched no order has been recorded.
         *
         * Separate from `withdraw/declared`, which always carries a WC_Order.
         *
         * @param int $id          Request id.
         * @param int $submittedAt Submission timestamp, site time.
         */
        do_action('withdraw/declared_unverified', $id, $submittedAt);

        return $this->renderUnmatched('done', $fields, '', $statement, $submittedAt, $receiptHash);
    }

    /** @param array{number:string, email:string, name:string, goods:string, reason:string} $fields */
    private function renderUnmatched(string $mode, array $fields, string $error, string $statement = '', int $submittedAt = 0, string $receiptHash = ''): string
    {
        ob_start();
        $this->template('form-unmatched.php', [
            'mode'        => $mode,
            'fields'      => $fields,
            'error'       => $error,
            'statement'   => $statement,
            'submittedAt' => $submittedAt,
            'receiptHash' => $receiptHash,
        ]);

        return (string) ob_get_clean();
    }

    /**
     * Step 1 when the emailed-link flow is on: take an order number and an
     * address, and answer the same way whatever the truth is.
     */
    private function handleLinkRequest(): string
    {
        if (empty($this->settings()['magic_link'])) {
            // The step only exists while the setting is on. Enforced here and not
            // only in the template, because a template is markup and a POST does
            // not have to come from one.
            return $this->renderLookupStep();
        }

        if (! isset($_POST['withdraw_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['withdraw_nonce'])), 'withdraw_lookup')) {
            return $this->renderLookupStep(__('Security check failed. Please try again.', 'plogins-withdraw'));
        }

        $number = $this->orderNumberFromRequest();
        $email  = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';

        if (! is_email($email)) {
            // Deliberately not neutralised: a malformed address cannot be the
            // billing address of any order, so saying so leaks nothing and saves
            // the customer an hour of waiting for a mail that was never sent.
            return $this->renderLookupStep(__('Enter a valid email address.', 'plogins-withdraw'));
        }

        // Counted before anything is decided. A counter that only advanced on a
        // hit would itself be an oracle for whether the order exists.
        //
        // What the page SAYS is identical either way. What it cannot hide is how
        // long it took: sending mail blocks, and on a shop sending through SMTP a
        // hit answers measurably slower than a miss. Deferring the send does not
        // fix it under PHP-FPM without fastcgi_finish_request, and padding the
        // response with a delay is worse. The throttle is what makes the channel
        // useless in practice: an attacker gets a handful of measurements an hour
        // per address and per address they still need the billing address to be
        // right for the timing to differ at all.
        if (! $this->links->throttled($email)) {
            $order = $this->lookupOrder($number, $email);
            if ($order instanceof \WC_Order) {
                $this->mailLink($order);
            }
        }

        // Eligibility is deliberately not checked before sending. The customer
        // sees the real reason ("the withdrawal period has ended") after
        // clicking; refusing here would either make this panel a lie or add a
        // second observable outcome.
        ob_start();
        $this->template('link-sent.php', [
            'minutes' => max(1, (int) round($this->links->ttl() / MINUTE_IN_SECONDS)),
            // Offered whatever the lookup found, so it says nothing about
            // whether the order exists.
            'offer_unmatched' => ! empty($this->settings()['accept_unmatched']),
            'number'  => $number,
            'email'   => $email,
        ]);
        return (string) ob_get_clean();
    }

    /**
     * The one place that decides whose order this is, server-side.
     *
     * Resolution order: a link token, then a signed-in customer who owns the
     * order, then, only while the emailed-link flow is off, today's order number
     * plus billing email.
     *
     * When authority came from a token or from ownership, `withdraw_email` is
     * never read. Taking the address from a posted field on an authorised flow
     * would let whoever holds a link redirect the art. 11a(4) acknowledgement to
     * an address of their choosing.
     *
     * @param string $error Filled with the message to show when this returns null.
     * @return array{order:\WC_Order, email:string, key:string, legacy:bool}|null
     */
    private function authorizeOrder(string &$error = ''): ?array
    {
        $expired = __('This link has expired or has already been used. Request a new one.', 'plogins-withdraw');
        $magic   = ! empty($this->settings()['magic_link']);

        // Tried whatever the setting says, so a link issued moments before the
        // shop switched the flow off still opens the form instead of dying.
        $key = $this->keyFromRequest();
        if ($key !== '') {
            $payload = $this->links->resolve($key);
            if ($payload === null) {
                $error = $expired;
                return null;
            }

            $order = wc_get_order($payload['order_id']);
            if (! $order instanceof \WC_Order) {
                $error = $expired;
                return null;
            }

            // The shop may have edited the billing address after the link went
            // out, in which case the link no longer points at the person it was
            // mailed to. Treat it as expired rather than let it keep working.
            if (strtolower(trim($order->get_billing_email())) !== strtolower(trim($payload['email']))) {
                $error = $expired;
                return null;
            }

            return [
                'order'  => $order,
                'email'  => (string) $order->get_billing_email(),
                'key'    => $key,
                'legacy' => false,
            ];
        }

        // Only while the emailed-link flow is on. With it off, a shop keeps the
        // order number plus billing email it has today, for signed-in customers
        // as well, so an update changes nothing anybody can observe.
        if ($magic) {
            $owned = $this->ownerOrder();
            if ($owned instanceof \WC_Order) {
                return [
                    'order'  => $owned,
                    'email'  => (string) $owned->get_billing_email(),
                    'key'    => '',
                    'legacy' => false,
                ];
            }
        }

        if (! $magic) {
            $number = $this->orderNumberFromRequest();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller verifies its own step nonce first.
            $email  = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';

            // Every step authorises here, and the review nonce is not bound to an
            // order, so the cap sits on the lookup itself rather than on one step.
            if ($this->links->lookupThrottled($email)) {
                $error = __('Too many attempts. Please try again in an hour.', 'plogins-withdraw');
                return null;
            }

            $order = $this->lookupOrder($number, $email);

            if ($order instanceof \WC_Order) {
                return [
                    'order'  => $order,
                    // The address ON THE ORDER, not the posted string that was
                    // just matched against it. lookupOrder() compares the two
                    // case-insensitively and after trimming, so the two are not
                    // guaranteed to be the same bytes, and the art. 11a(4)
                    // acknowledgement has to go to the address of record. All
                    // three branches now answer with the same thing.
                    'email'  => (string) $order->get_billing_email(),
                    'key'    => '',
                    'legacy' => true,
                ];
            }
        }

        $error           = __('We could not find an order with that number and email.', 'plogins-withdraw');
        $this->unmatched = ! $magic;

        return null;
    }

    /**
     * The order in the request, but only if the signed-in customer owns it.
     *
     * The My Account button is a UI affordance, not authority: a non-owner who
     * builds the same URL fails here and falls back to the lookup form. Guest
     * orders carry customer_id 0, so a registered user with the same address does
     * not match either, which is correct because the order was never theirs.
     */
    private function ownerOrder(): ?\WC_Order
    {
        $userId = get_current_user_id();
        if ($userId < 1) {
            return null;
        }

        $orderId = $this->orderIdFromRequest();
        if ($orderId < 1) {
            return null;
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order || (int) $order->get_customer_id() !== $userId) {
            return null;
        }

        return $order;
    }

    private function keyFromRequest(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the token is the credential; every POST step verifies its own nonce separately.
        if (isset($_POST['withdraw_key'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
            return $this->links->sanitizeToken(sanitize_text_field(wp_unslash($_POST['withdraw_key'])));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
        if (isset($_GET['wd_key'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
            return $this->links->sanitizeToken(sanitize_text_field(wp_unslash($_GET['wd_key'])));
        }

        return '';
    }

    /**
     * The order number as the customer typed it.
     *
     * Read as text, never through absint(): a shop numbering its orders
     * "INV-1001" would otherwise have every lookup collapse to 0 before
     * resolveOrderNumber() or its filter ever saw the number.
     */
    private function orderNumberFromRequest(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller verifies its own step nonce first.
        $raw = isset($_POST['withdraw_order']) ? sanitize_text_field(wp_unslash($_POST['withdraw_order'])) : '';

        return ltrim(trim($raw), '#');
    }

    private function orderIdFromRequest(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- an id alone grants nothing; ownership is checked by the caller.
        if (isset($_POST['withdraw_order'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
            return absint(wp_unslash($_POST['withdraw_order']));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
        if (isset($_GET['wd_order'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
            return absint(wp_unslash($_GET['wd_order']));
        }

        return 0;
    }

    /**
     * Where a link should point.
     *
     * Taken from the page the shortcode is actually rendering on, so a shop that
     * never filled in the form page setting still gets working links. The setting
     * and the home page are fallbacks for the non-singular case.
     */
    private function formUrl(): string
    {
        $pageId = is_singular() ? (int) get_queried_object_id() : 0;
        if ($pageId < 1) {
            $pageId = (int) $this->settings()['form_page_id'];
        }

        $permalink = $pageId > 0 ? get_permalink($pageId) : false;

        return is_string($permalink) && $permalink !== '' ? $permalink : home_url('/');
    }

    /**
     * Issue a one-time link and let the WooCommerce email carry it.
     *
     * Nothing is reported back to the visitor, not even a failed send: telling
     * them would say the order exists. If an SMTP plugin is broken, a guest
     * cannot withdraw at all, which is why the setting is off by default and the
     * admin help says so.
     */
    private function mailLink(\WC_Order $order): void
    {
        $email = (string) $order->get_billing_email();
        if (! is_email($email)) {
            return; // Manually created orders can have no address to mail.
        }

        $url     = add_query_arg('wd_key', $this->links->issue($order->get_id(), $email), $this->formUrl());
        $minutes = max(1, (int) round($this->links->ttl() / MINUTE_IN_SECONDS));

        /**
         * Fires once a one-time withdrawal link has been issued.
         *
         * The message itself is a WooCommerce email listening on this action, so
         * the shop can restyle or switch it off like any other. Its wording is
         * still filterable through `withdraw/magic_link_email`.
         *
         * @param \WC_Order $order   The order the link was issued for.
         * @param string    $url     The link itself.
         * @param int       $minutes How long the link stays valid.
         */
        do_action('withdraw/link_issued', $order, $url, $minutes);
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

    private function lookupOrder(string $number, string $email): ?\WC_Order
    {
        if ($number === '' || ! is_email($email)) {
            return null;
        }
        $order = ctype_digit($number) ? wc_get_order((int) $number) : false;

        // The form asks for the ORDER NUMBER, and every email and heading this
        // plugin prints uses get_order_number(). On stock WooCommerce that is
        // the id, so the two coincide and nobody notices. Any plugin that
        // renumbers orders breaks the pair: the shop shows the customer a
        // number its own form then rejects. Resolve the number properly before
        // giving up.
        if (! $order instanceof \WC_Order || (string) $order->get_order_number() !== $number) {
            $resolved = $this->resolveOrderNumber($number);

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
     * The last moment the order can be withdrawn from.
     *
     * The period runs from delivery (Directive 2011/83/EU Art. 9(2)(b)). The
     * order record has no delivery date, so completion stands in for it, or
     * creation if the order was never completed; `withdraw/delivery_date`
     * supplies the real one. Counted under Regulation 1182/71 Art. 3: the
     * delivery day itself does not count (3(1)), the period ends with the last
     * hour of its last day (3(2)(b)), and a last day on a Saturday or Sunday
     * moves to the next working day (3(4)). Grace days are the shop's own
     * extension, added on top.
     *
     * Shared with the My Account controls and the order emails, so a link is
     * never offered for an order the form would then refuse as out of time.
     */
    public static function deadline(\WC_Order $order, int $periodDays, int $graceDays = 0): int
    {
        /**
         * Filters the moment the consumer acquired the goods.
         *
         * Return a timestamp or a DateTimeInterface from a shipping or tracking
         * plugin; anything else falls back to completion, then creation.
         *
         * @param int|\DateTimeInterface|null $delivered
         * @param \WC_Order                    $order
         */
        $delivered = apply_filters('withdraw/delivery_date', null, $order);

        if ($delivered instanceof \DateTimeInterface) {
            $start = $delivered->getTimestamp();
        } elseif (is_numeric($delivered) && (int) $delivered > 0) {
            $start = (int) $delivered;
        } else {
            $completed = $order->get_date_completed();
            $start     = $completed ? $completed->getTimestamp() : ($order->get_date_created()?->getTimestamp() ?? time());
        }

        $end = (new \DateTimeImmutable('@' . $start))
            ->setTimezone(wp_timezone())
            ->setTime(23, 59, 59)
            ->modify('+' . max(0, $periodDays) . ' days');

        // ponytail: Saturday and Sunday only. Public holidays differ per member
        // state and per region, so a shop that needs them extends through the
        // grace days or the delivery-date filter.
        while ((int) $end->format('N') >= 6) {
            $end = $end->modify('+1 day');
        }

        return $end->modify('+' . max(0, $graceDays) . ' days')->getTimestamp();
    }

    /**
     * Status allowed and the period still running.
     *
     * Deliberately not the full eligibility(): that also queries open requests
     * per order, which on the orders list is one query per row. The form still
     * runs the full check when the customer arrives.
     *
     * @param array<string, mixed> $settings
     */
    public static function withinPeriod(\WC_Order $order, array $settings): bool
    {
        if (! in_array($order->get_status(), (array) ($settings['eligible_statuses'] ?? []), true)) {
            return false;
        }

        return time() <= self::deadline($order, (int) ($settings['period_days'] ?? 14), (int) ($settings['grace_days'] ?? 0));
    }

    /**
     * Whether the form may take a declaration for this order, and for what.
     *
     * `remaining` is the quantity per line item still open to withdrawal: the
     * ordered quantity, less what earlier pending, accepted or processed
     * declarations already cover, less anything an Art. 16 exclusion removes.
     * A partial withdrawal therefore leaves the rest of the order withdrawable.
     *
     * @return array{eligible:bool, reason:string, deadline:int, late:bool, excluded:list<int>, reasons:array<int,string>, remaining:array<int,int>}
     */
    private function eligibility(\WC_Order $order): array
    {
        $s    = $this->settings();
        $deny = static fn (string $reason, int $deadline = 0): array => [
            'eligible' => false, 'reason' => $reason, 'deadline' => $deadline, 'late' => false,
            'excluded' => [], 'reasons' => [], 'remaining' => [],
        ];

        $allowed = (array) $s['eligible_statuses'];
        if (! in_array($order->get_status(), $allowed, true)) {
            return $deny(__('This order is not in a status that can be withdrawn.', 'plogins-withdraw'));
        }

        $deadline = self::deadline($order, (int) $s['period_days'], (int) ($s['grace_days'] ?? 0));
        $late     = time() > $deadline;

        // Advisory mode takes a late declaration and flags it for the shop to
        // judge, instead of turning the consumer away on the form.
        if ($late && ($s['deadline_mode'] ?? 'strict') !== 'advisory') {
            return $deny(__('The withdrawal period for this order has ended.', 'plogins-withdraw'), $deadline);
        }

        $committed = $this->repository->committedQuantities($order->get_id());
        if ($committed === null) {
            return $deny(__('A withdrawal request for this order is already being processed.', 'plogins-withdraw'), $deadline);
        }

        // Art. 16(m) is empty unless the shop turned the feature on, the
        // customer consented AND supply has begun. Art. 16(c) and (d) come
        // from the product or its category.
        $reasons = array_fill_keys($this->consent->excludedItemIds($order), 'digital') + $this->exclusions->excludedItems($order);

        $remaining = [];
        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product || isset($reasons[(int) $itemId])) {
                continue;
            }
            $left = (int) $item->get_quantity() - ($committed[(int) $itemId] ?? 0);
            if ($left > 0) {
                $remaining[(int) $itemId] = $left;
            }
        }

        $result = [
            'eligible'  => true,
            'reason'    => '',
            'deadline'  => $deadline,
            'late'      => $late,
            'excluded'  => array_keys($reasons),
            'reasons'   => $reasons,
            'remaining' => $remaining,
        ];

        if ($remaining !== []) {
            return $result;
        }

        $result['eligible'] = false;
        if ($reasons === [] || $committed !== []) {
            $result['reason'] = $committed !== []
                ? __('Every item in this order is already covered by a withdrawal request.', 'plogins-withdraw')
                : __('This order has no items that can be withdrawn from.', 'plogins-withdraw');
        } elseif (array_values(array_unique($reasons)) === ['digital']) {
            $result['reason'] = __('This order is for digital content you asked us to supply immediately, and supply has begun, so the right of withdrawal no longer applies to it.', 'plogins-withdraw');
        } else {
            $result['reason'] = __('The right of withdrawal does not apply to the items in this order.', 'plogins-withdraw');
        }

        return $result;
    }

    /**
     * The shop's own wording, or the generated statutory text when it has none.
     *
     * @param callable(): string $fallback
     */
    private function text(string $configured, callable $fallback): string
    {
        $configured = trim($configured);

        return $configured !== '' ? $configured : $fallback();
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
