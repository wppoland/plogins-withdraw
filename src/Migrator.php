<?php

declare(strict_types=1);

namespace Withdraw;

defined('ABSPATH') || exit;

/**
 * Version-gated schema installer. Creates the withdrawal-requests table and
 * seeds default settings. Runs on activation and self-heals per-site on boot
 * (Multisite: activation only runs on the main site).
 */
final class Migrator
{
    private const OPTION_VERSION  = 'withdraw_schema_version';
    public const OPTION_SETTINGS  = 'withdraw_settings';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'withdraw_requests';
    }

    public function maybeMigrate(): void
    {
        if (get_option(self::OPTION_SETTINGS, null) === null) {
            /** @var array<string, mixed> $defaults */
            $defaults = require WITHDRAW_DIR . 'config/defaults.php';
            add_option(self::OPTION_SETTINGS, $defaults);
        }

        if (version_compare((string) get_option(self::OPTION_VERSION, ''), VERSION, '>=')) {
            return;
        }

        $this->createTable();
        update_option(self::OPTION_VERSION, VERSION);
    }

    private function createTable(): void
    {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta is intentionally strict about formatting (two spaces after KEY,
        // no backticks on the table name in the CREATE line).
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            customer_email varchar(191) NOT NULL DEFAULT '',
            token varchar(64) NOT NULL DEFAULT '',
            items longtext NOT NULL,
            reason text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY token (token)
        ) {$charset};";

        dbDelta($sql);
    }
}
