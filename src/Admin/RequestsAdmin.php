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
        $note   = isset($_POST['status_note']) ? sanitize_textarea_field(wp_unslash($_POST['status_note'])) : '';

        // A rejection with no reason is not an answer. The customer is told their
        // declaration was refused and has nothing to act on, and the shop keeps
        // no record of why it refused, which is the half that matters if the
        // decision is ever questioned.
        if ($id > 0 && $status === 'rejected' && trim($note) === '') {
            wp_safe_redirect(add_query_arg(
                ['page' => self::PAGE, 'request' => $id, 'wd_error' => 'reason'],
                admin_url('admin.php'),
            ));
            exit;
        }

        if ($id > 0) {
            // Two things were wrong here. updateStatus() returns false for a
            // status outside its whitelist, and this ignored that, so an
            // invalid post still mailed the customer the generic "being
            // reviewed" body for a write that never happened. And saving the
            // same status twice sent a second identical email, because nothing
            // asked whether anything had actually changed.
            $before = $this->repository->find($id);
            $was    = is_object($before) && isset($before->status) ? (string) $before->status : '';

            if ($was !== $status) {
                if ($this->repository->updateStatus($id, $status, $note)) {
                    $this->announceStatusChange($id, $status, $was);
                }
            } else {
                // Same status, edited note: save it and say nothing to the
                // customer. Re-announcing would send them a second identical
                // message for a correction they never see.
                $this->repository->updateStatus($id, $status, $note);
            }
        }

        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    /** Rows per page in the request log. */
    private const PER_PAGE = 25;

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view param.
        $detail = isset($_GET['request']) ? absint(wp_unslash($_GET['request'])) : 0;
        if ($detail > 0) {
            $this->renderDetail($detail);

            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param.
        $filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param.
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param.
        $paged  = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;

        $query = ['status' => $filter, 'search' => $search];
        $total = $this->repository->total($query);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        // A bookmarked page 9 of a list that has shrunk to 3 pages used to return
        // an empty table with no explanation.
        $paged = min($paged, $pages);

        $rows = $this->repository->all($query + [
            'limit'  => self::PER_PAGE,
            'offset' => ($paged - 1) * self::PER_PAGE,
        ]);

        $base   = ['page' => self::PAGE];
        $keep   = array_filter(['status' => $filter, 's' => $search], static fn ($v): bool => $v !== '');
        $listUrl = static fn (array $extra = []): string => add_query_arg($base + $keep + $extra, admin_url('admin.php'));
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Withdrawal Requests', 'plogins-withdraw'); ?></h1>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Status updated.', 'plogins-withdraw'); ?></p></div>
            <?php endif; ?>

            <ul class="subsubsub">
                <?php $counts = $this->repository->counts(); ?>
                <li><a href="<?php echo esc_url(add_query_arg($base + array_filter(['s' => $search]), admin_url('admin.php'))); ?>" <?php echo $filter === '' ? 'class="current"' : ''; ?>><?php echo esc_html__('All', 'plogins-withdraw'); ?> <span class="count">(<?php echo (int) array_sum($counts); ?>)</span></a> |</li>
                <?php foreach (RequestRepository::STATUSES as $st) : ?>
                    <li><a href="<?php echo esc_url(add_query_arg($base + ['status' => $st] + array_filter(['s' => $search]), admin_url('admin.php'))); ?>" <?php echo $filter === $st ? 'class="current"' : ''; ?>><?php echo esc_html(ucfirst($st)); ?> <span class="count">(<?php echo (int) ($counts[$st] ?? 0); ?>)</span></a> |</li>
                <?php endforeach; ?>
            </ul>

            <form method="get" class="search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
                <?php if ($filter !== '') : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($filter); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="withdraw-search"><?php echo esc_html__('Search requests', 'plogins-withdraw'); ?></label>
                    <input type="search" id="withdraw-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Email or order number', 'plogins-withdraw'); ?>">
                    <?php submit_button(__('Search requests', 'plogins-withdraw'), '', '', false); ?>
                </p>
            </form>

            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: number of withdrawal requests */
                            _n('%s request', '%s requests', $total, 'plogins-withdraw'),
                            number_format_i18n($total),
                        ));
                        ?>
                    </span>
                    <?php
                    echo wp_kses_post((string) paginate_links([
                        'base'      => $listUrl(['paged' => '%#%']),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $pages,
                        'prev_text' => '&lsaquo;',
                        'next_text' => '&rsaquo;',
                    ]));
                    ?>
                </div>
            </div>

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
                    <tr><td colspan="7">
                        <?php
                        echo esc_html($search !== '' || $filter !== ''
                            ? __('No withdrawal requests match this filter.', 'plogins-withdraw')
                            : __('No withdrawal requests yet.', 'plogins-withdraw'));
                        ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $r) :
                        $items      = json_decode((string) $r->items, true);
                        $items      = is_array($items) ? $items : [];
                        $refundDue  = ReturnPolicy::refundDeadline($r);
                        $refundLate = ReturnPolicy::refundOverdue($r); ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url($listUrl(['request' => (int) $r->id])); ?>">#<?php echo (int) $r->id; ?></a></strong>
                            </td>
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
                            <td><?php $this->renderStatusForm($r); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post((string) paginate_links([
                            'base'      => $listUrl(['paged' => '%#%']),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $pages,
                            'prev_text' => '&lsaquo;',
                            'next_text' => '&rsaquo;',
                        ]));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * One request, in full.
     *
     * The list cannot show everything the row holds, and the two fields it left
     * out are the ones that matter in a dispute: the reason the customer gave and
     * the declaration they confirmed. The token stays out of the page on purpose;
     * it is a credential, not a record.
     */
    private function renderDetail(int $id): void
    {
        $request = $this->repository->find($id);
        $backUrl = add_query_arg(['page' => self::PAGE], admin_url('admin.php'));

        if (! is_object($request)) {
            echo '<div class="wrap"><h1>' . esc_html__('Withdrawal request', 'plogins-withdraw') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('That request no longer exists.', 'plogins-withdraw') . '</p></div>';
            echo '<p><a href="' . esc_url($backUrl) . '">' . esc_html__('Back to the request log', 'plogins-withdraw') . '</a></p></div>';

            return;
        }

        $items = json_decode((string) $request->items, true);
        $items = is_array($items) ? $items : [];

        $order      = wc_get_order((int) $request->order_id);
        $orderNo    = $order instanceof \WC_Order ? $order->get_order_number() : (string) $request->order_id;
        $refundDue  = ReturnPolicy::refundDeadline($request);
        $refundLate = ReturnPolicy::refundOverdue($request);
        $dateFormat = (string) get_option('date_format') . ' ' . (string) get_option('time_format');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: withdrawal request id */
                    __('Withdrawal request #%d', 'plogins-withdraw'),
                    (int) $request->id,
                ));
                ?>
            </h1>
            <a href="<?php echo esc_url($backUrl); ?>" class="page-title-action"><?php echo esc_html__('Back to the log', 'plogins-withdraw'); ?></a>

            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag. ?>
            <?php if (isset($_GET['wd_error']) && 'reason' === $_GET['wd_error']) : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Say why before rejecting. The reason is printed in the email the customer receives, and it is what your own records will show if the decision is ever questioned.', 'plogins-withdraw'); ?></p></div>
            <?php endif; ?>

            <table class="widefat striped" style="margin-top:16px;max-width:900px">
                <tbody>
                    <tr>
                        <th style="width:220px"><?php echo esc_html__('Order', 'plogins-withdraw'); ?></th>
                        <td><a href="<?php echo esc_url(self::orderEditUrl((int) $request->order_id)); ?>">#<?php echo esc_html($orderNo); ?></a></td>
                    </tr>
                    <?php if (! empty($request->customer_name)) : ?>
                        <tr>
                            <th><?php echo esc_html__('Name on the contract', 'plogins-withdraw'); ?></th>
                            <td><?php echo esc_html((string) $request->customer_name); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php echo esc_html__('Customer', 'plogins-withdraw'); ?></th>
                        <td><a href="<?php echo esc_url('mailto:' . (string) $request->customer_email); ?>"><?php echo esc_html((string) $request->customer_email); ?></a></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Declared on', 'plogins-withdraw'); ?></th>
                        <td><?php echo esc_html(mysql2date($dateFormat, (string) $request->created_at)); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Last updated', 'plogins-withdraw'); ?></th>
                        <td><?php echo esc_html(mysql2date($dateFormat, (string) $request->updated_at)); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Refund due', 'plogins-withdraw'); ?></th>
                        <td>
                            <?php if ($refundDue === 0) : ?>
                                <?php echo esc_html__('Nothing outstanding.', 'plogins-withdraw'); ?>
                            <?php else : ?>
                                <?php echo esc_html(date_i18n((string) get_option('date_format'), $refundDue)); ?>
                                <?php if ($refundLate) : ?>
                                    <strong style="color:#b32d2e"><?php echo esc_html__('Overdue', 'plogins-withdraw'); ?></strong>
                                <?php endif; ?>
                                <p class="description"><?php echo esc_html__('Article 13(1): 14 days from the day the customer told you, and the refund includes the standard delivery cost they paid.', 'plogins-withdraw'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Items', 'plogins-withdraw'); ?></th>
                        <td>
                            <?php foreach ($items as $it) : ?>
                                <div><?php echo esc_html((string) ($it['name'] ?? '')); ?> &times;<?php echo (int) ($it['qty'] ?? 0); ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Reason given', 'plogins-withdraw'); ?></th>
                        <td>
                            <?php
                            $reason = trim((string) $request->reason);
                            echo $reason !== ''
                                ? esc_html($reason)
                                : '<em>' . esc_html__('None given. A reason is never required.', 'plogins-withdraw') . '</em>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Declaration', 'plogins-withdraw'); ?></th>
                        <td>
                            <?php
                            $declaration = trim((string) ($request->declaration ?? ''));
                            if ($declaration !== '') {
                                echo '<div style="white-space:pre-wrap">' . esc_html($declaration) . '</div>';
                            } else {
                                echo '<em>' . esc_html__('Recorded before 1.4.0, when the declaration was not stored. The acknowledgement email sent at the time carries it.', 'plogins-withdraw') . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Status and your note', 'plogins-withdraw'); ?></th>
                        <td><?php $this->renderStatusForm($request, true); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * The status control, shared by the list and the detail screen.
     *
     * The note travels with the status rather than sitting on a screen of its
     * own, because the moment a shop rejects a request is the only moment it
     * reliably knows why.
     */
    private function renderStatusForm(object $request, bool $wide = false): void
    {
        $note = (string) ($request->admin_note ?? '');
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start">
            <?php wp_nonce_field('withdraw_set_status'); ?>
            <input type="hidden" name="action" value="withdraw_set_status">
            <input type="hidden" name="request_id" value="<?php echo (int) $request->id; ?>">
            <select name="status">
                <?php foreach (RequestRepository::STATUSES as $st) : ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($request->status, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($wide) : ?>
                <textarea name="status_note" rows="3" class="large-text" placeholder="<?php echo esc_attr__('Why. Required to reject, and printed in the email the customer receives.', 'plogins-withdraw'); ?>"><?php echo esc_textarea($note); ?></textarea>
            <?php else : ?>
                <input type="text" name="status_note" value="<?php echo esc_attr($note); ?>" size="18" placeholder="<?php echo esc_attr__('Why (needed to reject)', 'plogins-withdraw'); ?>">
            <?php endif; ?>
            <button type="submit" class="button button-small"><?php echo esc_html__('Save', 'plogins-withdraw'); ?></button>
        </form>
        <?php
    }
}
