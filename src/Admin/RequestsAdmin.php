<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\RequestRepository;

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

    public function handleStatusChange(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'plogins-withdraw'));
        }
        check_admin_referer('withdraw_set_status');

        $id     = isset($_POST['request_id']) ? absint(wp_unslash($_POST['request_id'])) : 0;
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        if ($id > 0) {
            $this->repository->updateStatus($id, $status);
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
                        <th><?php echo esc_html__('Status', 'plogins-withdraw'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) : ?>
                    <tr><td colspan="6"><?php echo esc_html__('No withdrawal requests yet.', 'plogins-withdraw'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $r) :
                        $items = json_decode((string) $r->items, true);
                        $items = is_array($items) ? $items : []; ?>
                        <tr>
                            <td>#<?php echo (int) $r->id; ?></td>
                            <td><a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $r->order_id . '&action=edit')); ?>">#<?php echo (int) $r->order_id; ?></a></td>
                            <td><?php echo esc_html((string) $r->customer_email); ?></td>
                            <td>
                                <?php foreach ($items as $it) : ?>
                                    <div><?php echo esc_html((string) ($it['name'] ?? '')); ?> &times;<?php echo (int) ($it['qty'] ?? 0); ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format') . ' H:i', (string) $r->created_at)); ?></td>
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
