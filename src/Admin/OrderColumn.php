<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

/**
 * Display the withdrawal request status directly on the WooCommerce Orders list.
 *
 * Keeps shop staff from shipping or fulfilling an order that has a pending or
 * accepted withdrawal declaration without having to consult the requests log
 * separately. Works across both legacy CPT orders and HPOS custom order tables.
 */
final class OrderColumn implements HasHooks
{
    public const COLUMN = 'withdraw_status';

    /**
     * Cache of withdrawal requests per order ID for the current request.
     *
     * @var array<int, array{id:int, status:string, late_submission:int}|null>
     */
    private array $shown = [];

    /**
     * Visible order IDs on the current admin screen, for batch prefetching.
     *
     * @var list<int>
     */
    private array $pageIds = [];

    public function __construct(private readonly RequestRepository $repository)
    {
    }

    public function registerHooks(): void
    {
        // Legacy post-based orders screen.
        add_filter('manage_edit-shop_order_columns', [$this, 'addColumn']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderLegacy'], 10, 2);

        // HPOS custom order tables screen.
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'addColumn']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'renderHpos'], 10, 2);
    }

    /**
     * Insert the Withdrawal column right after the order status column.
     *
     * @param array<string, string> $columns Existing table columns.
     * @return array<string, string> Columns with the Withdrawal column included.
     */
    public function addColumn(array $columns): array
    {
        $new = [];
        $inserted = false;

        foreach ($columns as $key => $title) {
            $new[$key] = $title;
            if ($key === 'order_status') {
                $new[self::COLUMN] = __('Withdrawal', 'plogins-withdraw');
                $inserted = true;
            }
        }

        if (! $inserted) {
            $new[self::COLUMN] = __('Withdrawal', 'plogins-withdraw');
        }

        return $new;
    }

    public function renderLegacy(string $column, int $postId): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        if ($this->pageIds === [] && isset($GLOBALS['wp_query']->posts) && is_array($GLOBALS['wp_query']->posts)) {
            foreach ($GLOBALS['wp_query']->posts as $post) {
                $this->pageIds[] = is_object($post) ? (int) $post->ID : (int) $post;
            }
        }

        $this->render($postId);
    }

    public function renderHpos(string $column, mixed $order): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        $orderId = $order instanceof \WC_Order ? $order->get_id() : (int) $order;
        $this->render($orderId);
    }

    private function render(int $orderId): void
    {
        if ($orderId <= 0) {
            echo '<span class="na">-</span>';
            return;
        }

        if (! array_key_exists($orderId, $this->shown)) {
            $prefetchIds = in_array($orderId, $this->pageIds, true) ? $this->pageIds : [$orderId];
            $this->prefetch($prefetchIds);
        }

        $req = $this->shown[$orderId] ?? null;
        if ($req === null) {
            echo '<span class="na">-</span>';
            return;
        }

        $id         = (int) $req['id'];
        $st         = (string) $req['status'];
        $url        = admin_url('admin.php?page=' . RequestsAdmin::PAGE . '&request=' . $id);
        $badgeClass = match ($st) {
            'pending'   => 'status-pending',
            'accepted'  => 'status-completed',
            'rejected'  => 'status-failed',
            'processed' => 'status-processing',
            default     => 'status-on-hold',
        };

        $labels = [
            'pending'   => _x('Pending', 'withdrawal request status', 'plogins-withdraw'),
            'accepted'  => _x('Accepted', 'withdrawal request status', 'plogins-withdraw'),
            'rejected'  => _x('Rejected', 'withdrawal request status', 'plogins-withdraw'),
            'processed' => _x('Processed', 'withdrawal request status', 'plogins-withdraw'),
        ];
        $label = $labels[$st] ?? ucfirst($st);

        printf(
            '<a href="%s" title="%s"><mark class="order-status %s"><span>%s</span></mark></a>',
            esc_url($url),
            /* translators: %d: withdrawal request id */
            esc_attr(sprintf(__('Withdrawal request #%d', 'plogins-withdraw'), $id)),
            esc_attr($badgeClass),
            esc_html($label),
        );

        if (! empty($req['late_submission'])) {
            printf(
                ' <span class="dashicons dashicons-warning" title="%s" style="color:#b32d2e;font-size:16px;vertical-align:middle;margin-left:2px"></span>',
                esc_attr(__('Submitted after deadline', 'plogins-withdraw')),
            );
        }
    }

    /**
     * Batch fetch withdrawal requests for the requested order IDs.
     *
     * @param list<int> $orderIds Order IDs to prefetch.
     */
    private function prefetch(array $orderIds): void
    {
        $needed = [];
        foreach ($orderIds as $id) {
            if ($id > 0 && ! array_key_exists($id, $this->shown)) {
                $needed[] = $id;
            }
        }

        if ($needed === []) {
            return;
        }

        $found = $this->repository->forOrders($needed);

        foreach ($needed as $id) {
            $this->shown[$id] = $found[$id] ?? null;
        }
    }
}
