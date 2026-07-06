<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * Data access for withdrawal requests. All queries are prepared; the table name
 * comes from Migrator::table() (interpolated, not user input).
 */
final class RequestRepository
{
    public const STATUSES = ['pending', 'accepted', 'rejected', 'processed'];

    /**
     * @param array<int, array{product_id:int, name:string, qty:int}> $items
     */
    public function create(int $orderId, string $email, array $items, string $reason, string $token): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            Migrator::table(),
            [
                'order_id'       => $orderId,
                'customer_email' => $email,
                'token'          => $token,
                'items'          => (string) wp_json_encode(array_values($items)),
                'reason'         => $reason,
                'status'         => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        );

        return (int) $wpdb->insert_id;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }
        global $wpdb;

        return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            Migrator::table(),
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );
    }

    /** @return object|null */
    public function find(int $id): ?object
    {
        global $wpdb;
        $table = Migrator::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table, $id));

        return $row ?: null;
    }

    /** @return int number of pending requests already open for this order */
    public function openCountForOrder(int $orderId): int
    {
        global $wpdb;
        $table = Migrator::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE order_id = %d AND status IN ('pending','accepted')",
            $table,
            $orderId,
        ));
    }

    /**
     * @param array{status?:string, search?:string, limit?:int, offset?:int} $args
     * @return array<int, object>
     */
    public function all(array $args = []): array
    {
        global $wpdb;
        $table  = Migrator::table();
        $limit  = max(1, min(200, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        $where  = '1=1';
        $params = [];
        if (! empty($args['status']) && in_array($args['status'], self::STATUSES, true)) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if (! empty($args['search'])) {
            $where   .= ' AND (customer_email LIKE %s OR order_id = %d)';
            $params[] = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params[] = (int) $args['search'];
        }
        $params[] = $limit;
        $params[] = $offset;
        array_unshift($params, $table);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params,
        ));

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, int> status => count */
    public function counts(): array
    {
        global $wpdb;
        $table = Migrator::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) AS n FROM %i GROUP BY status", $table));

        $out = array_fill_keys(self::STATUSES, 0);
        foreach ((array) $rows as $r) {
            $out[$r->status] = (int) $r->n;
        }
        return $out;
    }
}
