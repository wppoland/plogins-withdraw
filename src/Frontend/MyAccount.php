<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\WithdrawalService;

defined('ABSPATH') || exit;

/**
 * Adds the required "easy withdrawal" entry point to the customer's account:
 * a control under the order details and an action on the orders list, both
 * linking to the withdrawal form page with the order pre-filled.
 *
 * The label is not ours to choose: art. 11a(1) requires "withdraw from contract
 * here" or an unambiguous equivalent. It also requires the function to be
 * available throughout the whole withdrawal period, which an order-view button
 * alone does not achieve, so WithdrawLink carries the rest. Both controls show
 * only while the period runs, so a lapsed right is never offered.
 */
final class MyAccount implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderButton']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addOrderAction'], 10, 2);
    }

    /**
     * The order-view button. Not on the order-received page: that renders the
     * same template straight after checkout, and the function lives in My
     * Account, the footer link and the order emails.
     */
    public function renderButton(\WC_Order $order): void
    {
        if (is_order_received_page()) {
            return;
        }

        $url = $this->url($order);
        if ($url === '') {
            return;
        }
        ?>
        <p class="withdraw-order-action">
            <a class="button withdraw-order-action__btn" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html(WithdrawLink::label(WithdrawLink::settings())); ?>
            </a>
        </p>
        <?php
    }

    /**
     * The orders-list action.
     *
     * Since WooCommerce 10.9 this filter also feeds the actions row of the
     * order-details template, which renders on the order-received page and on
     * the single order view. The first is suppressed as above; the second
     * already has renderButton(), which works on every WooCommerce version, so
     * adding the action there too would print the control twice.
     *
     * @param array<string, array{url:string, name:string}> $actions
     * @param mixed $order
     * @return array<string, array{url:string, name:string}>
     */
    public function addOrderAction($actions, $order): array
    {
        $actions = is_array($actions) ? $actions : [];

        if (! $order instanceof \WC_Order || is_order_received_page() || is_wc_endpoint_url('view-order')) {
            return $actions;
        }

        $url = $this->url($order);
        if ($url !== '') {
            $actions['withdraw'] = [
                'url'  => $url,
                'name' => WithdrawLink::label(WithdrawLink::settings()),
            ];
        }

        return $actions;
    }

    /** The pre-filled form URL, or '' when no control should show. */
    private function url(\WC_Order $order): string
    {
        $s    = WithdrawLink::settings();
        $base = WithdrawLink::formUrl($s);

        if ($base === '' || ! WithdrawalService::withinPeriod($order, $s)) {
            return '';
        }

        return add_query_arg(['wd_order' => $order->get_id()], $base);
    }
}
