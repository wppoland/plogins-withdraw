<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\ReturnPolicy;

defined('ABSPATH') || exit;

/**
 * Admin log of withdrawal requests (WooCommerce → Withdrawal Requests) with a
 * per-row status control. Read-only on the money side: the merchant refunds in
 * the normal order screen; this tracks the request lifecycle.
 */
final class RequestsAdmin implements HasHooks
{
    public const PAGE = 'plogins-withdraw-requests';

    public function __construct(private readonly RequestRepository $repository)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_post_withdraw_set_status', [$this, 'handleStatusChange']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Withdrawal Requests', 'plogins-withdraw'),
            __('Withdrawal Requests', 'plogins-withdraw'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    /**
     * Announce a status change, and leave a trace on the order.
     *
     * The message itself is four WooCommerce emails listening on this action,
     * one per status, so a shop can reword a rejection without touching a
     * refund confirmation. WC()->mailer() is loaded on the same action at
     * priority 1 by EmailService, which is what makes those listeners exist by
     * the time this fires: admin-post.php never builds the mailer on its own.
     */
    private function announceStatusChange(int $id, string $status, string $previousStatus): void
    {
        $request = $this->repository->find($id);
        if (! is_object($request)) {
            return;
        }

        /**
         * Fires after a withdrawal request changes status.
         *
         * @param int    $id             Request id.
         * @param string $status         The status just saved.
         * @param string $previousStatus The status it had before.
         */
        do_action('withdraw/status_changed', $id, $status, $previousStatus);

        // Same reason the declaration writes a note: whoever opens the order
        // next should see what happened without knowing this plugin exists.
        $order = wc_get_order((int) $request->order_id);
        if ($order instanceof \WC_Order) {
            $order->add_order_note(
                sprintf(
                    /* translators: 1: request id, 2: new status */
                    __('Withdrawal declaration #%1$d marked as %2$s.', 'plogins-withdraw'),
                    $id,
                    $status,
                ),
            );
        }
    }

    /**
     * Link to an order in a way that survives HPOS.
     *
     * post.php?post=<id> only works while orders are posts. This plugin
     * declares HPOS compatibility, and with custom order tables switched on
     * every link in this log led to a 404.
     */
    private static function orderEditUrl(int $orderId): string
    {
        if (class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return admin_url('admin.php?page=wc-orders&action=edit&id=' . $orderId);
        }

        return admin_url('post.php?post=' . $orderId . '&action=edit');
    }

    public function handleStatusChange(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'plogins-withdraw'));
        }
        check_admin_referer('withdraw_set_status');

        $id     = isset($_POST['request_id']) ? absint(wp_unslash($_POST['request_id'])) : 0;
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        if ($id > 0) {
            // Two things were wrong here. updateStatus() returns false for a
            // status outside its whitelist, and this ignored that, so an
            // invalid post still mailed the customer the generic "being
            // reviewed" body for a write that never happened. And saving the
            // same status twice sent a second identical email, because nothing
            // asked whether anything had actually changed.
            $before = $this->repository->find($id);
            $was    = is_object($before) && isset($before->status) ? (string) $before->status : '';

            if ($was !== $status && $this->repository->updateStatus($id, $status)) {
                $this->announceStatusChange($id, $status, $was);
            }
        }

        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param.
        $filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $rows   = $this->repository->all(['status' => $filter, 'limit' => 100]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Withdrawal Requests', 'plogins-withdraw'); ?></h1>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Status updated.', 'plogins-withdraw'); ?></p></div>
            <?php endif; ?>
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE], admin_url('admin.php'))); ?>" <?php echo $filter === '' ? 'class="current"' : ''; ?>><?php echo esc_html__('All', 'plogins-withdraw'); ?></a> |</li>
                <?php foreach (RequestRepository::STATUSES as $st) : ?>
                    <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE, 'status' => $st], admin_url('admin.php'))); ?>" <?php echo $filter === $st ? 'class="current"' : ''; ?>><?php echo esc_html(ucfirst($st)); ?></a> |</li>
                <?php endforeach; ?>
            </ul>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Order', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Customer', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Items', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Date', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Refund due', 'plogins-withdraw'); ?></th>
                        <th><?php echo esc_html__('Status', 'plogins-withdraw'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) : ?>
                    <tr><td colspan="7"><?php echo esc_html__('No withdrawal requests yet.', 'plogins-withdraw'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $r) :
                        $items = json_decode((string) $r->items, true);
                        $items = is_array($items) ? $items : [];
                        $refundDue = ReturnPolicy::refundDeadline($r);
                        $refundLate = ReturnPolicy::refundOverdue($r); ?>
                        <tr>
                            <td>#<?php echo (int) $r->id; ?></td>
                            <td><a href="<?php echo esc_url(self::orderEditUrl((int) $r->order_id)); ?>">#<?php echo (int) $r->order_id; ?></a></td>
                            <td><?php echo esc_html((string) $r->customer_email); ?></td>
                            <td>
                                <?php foreach ($items as $it) : ?>
                                    <div><?php echo esc_html((string) ($it['name'] ?? '')); ?> &times;<?php echo (int) ($it['qty'] ?? 0); ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format') . ' H:i', (string) $r->created_at)); ?></td>
                            <td>
                                <?php if ($refundDue === 0) : ?>
                                    &mdash;
                                <?php elseif ($refundLate) : ?>
                                    <strong style="color:#b32d2e"><?php echo esc_html(date_i18n((string) get_option('date_format'), $refundDue)); ?></strong><br>
                                    <span style="color:#b32d2e"><?php echo esc_html__('Overdue', 'plogins-withdraw'); ?></span>
                                <?php else : ?>
                                    <?php echo esc_html(date_i18n((string) get_option('date_format'), $refundDue)); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px">
                                    <?php wp_nonce_field('withdraw_set_status'); ?>
                                    <input type="hidden" name="action" value="withdraw_set_status">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $r->id; ?>">
                                    <select name="status">
                                        <?php foreach (RequestRepository::STATUSES as $st) : ?>
                                            <option value="<?php echo esc_attr($st); ?>" <?php selected($r->status, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-small"><?php echo esc_html__('Save', 'plogins-withdraw'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
