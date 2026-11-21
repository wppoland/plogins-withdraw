<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\RequestRepository;
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
 *
 * It also adds a "Withdrawals" tab listing the customer's own requests and
 * their status, so a declaration made once can be followed up without the
 * acknowledgement email.
 */
final class MyAccount implements HasHooks
{
    public const ENDPOINT = 'withdrawals';

    private const PER_PAGE = 20;

    public function __construct(
        private readonly RequestRepository $repository,
        private readonly WithdrawalService $withdrawals,
        private readonly RequestStatus $status,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderButton']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addOrderAction'], 10, 2);

        // Registered on every request, wp-admin included: rewrite rules are
        // also rebuilt there (Permalinks, WooCommerce's own flush), and a
        // rebuild without the endpoint makes the tab a 404. WooCommerce adds
        // its endpoints on an earlier init pass, so this one adds itself.
        add_rewrite_endpoint(self::ENDPOINT, WC()->query->get_endpoints_mask());
        add_filter('woocommerce_get_query_vars', [$this, 'addQueryVar']);
        add_filter('woocommerce_account_menu_items', [$this, 'addMenuItem']);
        add_filter('woocommerce_endpoint_' . self::ENDPOINT . '_title', [$this, 'title']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderList']);
    }

    /**
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    public function addQueryVar($vars): array
    {
        $vars                 = is_array($vars) ? $vars : [];
        $vars[self::ENDPOINT] = self::ENDPOINT;

        return $vars;
    }

    /**
     * Right after "Orders", where a customer looks for anything about a purchase.
     *
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function addMenuItem($items): array
    {
        $items = is_array($items) ? $items : [];
        $out   = [];
        foreach ($items as $key => $label) {
            $out[$key] = $label;
            if ($key === 'orders') {
                $out[self::ENDPOINT] = $this->title();
            }
        }
        if (! isset($out[self::ENDPOINT])) {
            $out[self::ENDPOINT] = $this->title();
        }

        return $out;
    }

    public function title(): string
    {
        return __('Withdrawals', 'plogins-withdraw');
    }

    /**
     * The tab itself. The endpoint value is the page number, the way the
     * WooCommerce orders tab pages (/withdrawals/2/).
     *
     * @param mixed $value
     */
    public function renderList($value = ''): void
    {
        $page = max(1, absint($value));
        // One row more than shown tells whether there is a next page without a COUNT query.
        $rows  = $this->repository->forCustomer(get_current_user_id(), self::PER_PAGE + 1, ($page - 1) * self::PER_PAGE);
        $more  = count($rows) > self::PER_PAGE;
        $rows  = array_slice($rows, 0, self::PER_PAGE);
        $links = [];

        foreach ($rows as $row) {
            $oid = (int) $row->order_id;
            if (isset($links[$oid])) {
                continue;
            }
            $order       = wc_get_order($oid);
            $links[$oid] = ['order' => $order instanceof \WC_Order ? $order : null, 'new' => ''];
            if ($order instanceof \WC_Order && $this->withdrawals->eligibility($order)['eligible']) {
                $links[$oid]['new'] = $this->url($order);
            }
        }

        wc_get_template('myaccount/withdraw-requests.php', [
            'withdraw_rows'     => $rows,
            'withdraw_orders'   => $links,
            'withdraw_form_url' => WithdrawLink::formUrl(WithdrawLink::settings()),
            'withdraw_label'    => WithdrawLink::label(WithdrawLink::settings()),
            'withdraw_view'     => array_map(fn (object $row): string => $this->status->statusUrl($row, false), $rows),
            'withdraw_page'     => $page,
            'withdraw_more'     => $more,
        ], '', WITHDRAW_DIR . 'templates/');
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
