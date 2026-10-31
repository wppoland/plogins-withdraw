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
     * Store a declaration.
     *
     * `$declaration` is the exact text the consumer was shown and confirmed. It
     * is frozen here rather than rebuilt at send time, because art. 11a(4) makes
     * the acknowledgement the consumer's proof: it has to repeat back the words
     * that were on screen, not words a later locale, a renamed product or a
     * reworded template would produce.
     *
     * @param array<int, array{product_id:int, name:string, qty:int}> $items
     */
    public function create(int $orderId, string $email, array $items, string $reason, string $token, string $name = '', string $declaration = '', bool $late = false, string $orderHint = ''): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            Migrator::table(),
            [
                'order_id'       => $orderId,
                'customer_name'  => $name,
                'customer_email' => $email,
                'token'          => $token,
                'items'          => (string) wp_json_encode(array_values($items)),
                'reason'         => $reason,
                'declaration'    => $declaration,
                'status'         => 'pending',
                'late_submission' => $late ? 1 : 0,
                // The order number as typed, kept only when no order matched
                // (order_id 0), so the shop can match it by hand.
                'order_hint'     => $orderHint,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Move a request to a new status, with the shop's note on the outcome.
     *
     * The note is written whenever one is supplied, and left alone when it is
     * not, so re-saving a status without retyping the note does not wipe it.
     */
    public function updateStatus(int $id, string $status, ?string $note = null): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }
        global $wpdb;

        $data    = ['status' => $status, 'updated_at' => current_time('mysql')];
        $formats = ['%s', '%s'];

        if ($note !== null) {
            $data['admin_note'] = $note;
            $formats[]          = '%s';
        }

        return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            Migrator::table(),
            $data,
            ['id' => $id],
            $formats,
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

    /**
     * Id of a pending declaration with exactly this order, address and text, or 0.
     *
     * A confirm POST can arrive twice (refresh, double click, back and resubmit).
     * Before 1.8.0 any open request blocked the order, so the replay was refused;
     * with partial withdrawals it would declare the same goods a second time.
     */
    public function pendingDuplicate(int $orderId, string $email, string $declaration): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i WHERE order_id = %d AND customer_email = %s AND declaration = %s AND status = 'pending' ORDER BY id DESC LIMIT 1",
            Migrator::table(),
            $orderId,
            $email,
            $declaration,
        ));
    }

    /**
     * Quantity per order line already covered by a pending, accepted or
     * processed declaration. Rejected ones cover nothing.
     *
     * Rows stored before 1.6.0 carry no line id. An open one of those makes the
     * answer unknown (null), and the caller then refuses as it always did;
     * a processed one is ignored, as it always was.
     *
     * @return array<int,int>|null
     */
    public function committedQuantities(int $orderId): ?array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT items, status FROM %i WHERE order_id = %d AND status IN ('pending','accepted','processed')",
            Migrator::table(),
            $orderId,
        ));

        $out = [];
        foreach ((array) $rows as $row) {
            $items = json_decode((string) $row->items, true);
            foreach (is_array($items) ? $items : [] as $item) {
                if (! is_array($item) || ! isset($item['item_id'])) {
                    if ($row->status !== 'processed') {
                        return null;
                    }
                    continue;
                }
                $id       = (int) $item['item_id'];
                $out[$id] = ($out[$id] ?? 0) + (int) ($item['qty'] ?? 0);
            }
        }

        return $out;
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

        // $where is built only from hard-coded SQL fragments (' AND status = %s', ' AND (customer_email LIKE %s OR order_id = %d)') with placeholders; every bound value goes through $params into $wpdb->prepare(). No user input is interpolated.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where contains only trusted hard-coded fragments with placeholders; values bound via $params.
        $sql = "SELECT * FROM %i WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is trusted hard-coded SQL with placeholders only.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- $sql is trusted hard-coded SQL with placeholders; values bound via $params through prepare().
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        return is_array($rows) ? $rows : [];
    }

    /**
     * How many rows a given all() call would match, ignoring limit and offset.
     *
     * Same WHERE, built the same way, because a pager that counts something
     * other than what the list shows sends people to empty pages.
     *
     * @param array{status?:string, search?:string} $args
     */
    public function total(array $args = []): int
    {
        global $wpdb;
        $table  = Migrator::table();
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
        array_unshift($params, $table);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where contains only trusted hard-coded fragments with placeholders; values bound via $params.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE {$where}", $params));
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

    /**
     * @return list<object>
     */
    public function findByEmail(string $email, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $table  = Migrator::table();
        $limit  = max(1, $limit);
        $offset = max(0, $offset);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
                $table,
                $email,
                $limit,
                $offset,
            ),
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Strip a person out of their declarations.
     *
     * The declaration text quotes the consumer's name and email address back
     * (Art. 11a(2)), so blanking the two columns alone left both readable in
     * the stored statement while the eraser reported the email as cleared.
     * Order id, items, status and dates stay as the trader's record.
     */
    public function anonymizeByEmail(string $email): int
    {
        global $wpdb;
        $table = Migrator::table();

        $count = 0;
        foreach ($this->findByEmail($email, PHP_INT_MAX) as $row) {
            // Whole words only, so a short name cannot eat letters out of the
            // surrounding sentence.
            $declaration = (string) $row->declaration;
            foreach ([(string) $row->customer_email, (string) $row->customer_name] as $needle) {
                if (trim($needle) !== '') {
                    $declaration = (string) preg_replace('/(?<![\\w@.])' . preg_quote(trim($needle), '/') . '(?![\\w@])/iu', '[removed]', $declaration);
                }
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $updated = $wpdb->update(
                $table,
                [
                    'customer_email' => 'anonymized@privacy.invalid',
                    'customer_name'  => '',
                    'reason'         => '',
                    'declaration'    => $declaration,
                ],
                ['id' => (int) $row->id],
                ['%s', '%s', '%s', '%s'],
                ['%d'],
            );
            if ($updated !== false) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Read the newest withdrawal request for each order in the given list.
     *
     * @param list<int> $orderIds
     * @return array<int, array{id:int, status:string, late_submission:int}>
     */
    public function forOrders(array $orderIds): array
    {
        $clean = array_values(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0));
        if ($clean === []) {
            return [];
        }

        global $wpdb;
        $table        = Migrator::table();
        $placeholders = implode(',', array_fill(0, count($clean), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, order_id, status, late_submission FROM %i WHERE order_id IN ($placeholders) ORDER BY id DESC",
                array_merge([$table], $clean),
            ),
        );

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $oid = (int) $r->order_id;
                if (! isset($out[$oid])) {
                    $out[$oid] = [
                        'id'              => (int) $r->id,
                        'status'          => (string) $r->status,
                        'late_submission' => (int) $r->late_submission,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Verifiable SHA-256 receipt hash over the declaration record.
     *
     * Art. 11a(4) makes the acknowledgement the consumer's proof: a SHA-256
     * digest over the declaration fields provides an immutable verification code
     * for both consumer and merchant records.
     *
     * @param object|array<string, mixed> $request
     */
    public static function receiptHash(object|array $request): string
    {
        $token = is_object($request) ? (string) ($request->token ?? '') : (string) ($request['token'] ?? '');
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            return $token;
        }

        $orderId     = is_object($request) ? (int) ($request->order_id ?? 0) : (int) ($request['order_id'] ?? 0);
        $email       = is_object($request) ? (string) ($request->customer_email ?? '') : (string) ($request['customer_email'] ?? '');
        $declaration = is_object($request) ? (string) ($request->declaration ?? '') : (string) ($request['declaration'] ?? '');
        $createdAt   = is_object($request) ? (string) ($request->created_at ?? '') : (string) ($request['created_at'] ?? '');

        return hash('sha256', $orderId . '|' . $email . '|' . $declaration . '|' . $createdAt);
    }
}
