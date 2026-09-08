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
    private const OPTION_SCHEMA_REV = 'withdraw_schema_rev';

    /**
     * Bumped whenever the table shape changes.
     *
     * The plugin VERSION gate alone is not enough: it only re-runs dbDelta when
     * the plugin version moves, so a shipped column change and a version bump
     * have to travel together or the column silently never appears and every
     * insert naming it fails. This revision is the column's own gate.
     */
    private const SCHEMA_REV = 2;

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

        $revIsStale = (int) get_option(self::OPTION_SCHEMA_REV, 0) < self::SCHEMA_REV;

        if (! $revIsStale && version_compare((string) get_option(self::OPTION_VERSION, ''), VERSION, '>=')) {
            return;
        }

        $this->createTable();
        update_option(self::OPTION_VERSION, VERSION);
        update_option(self::OPTION_SCHEMA_REV, self::SCHEMA_REV);
    }

    private function createTable(): void
    {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta is intentionally strict about formatting (two spaces after KEY,
        // no backticks on the table name in the CREATE line).
        //
        // `declaration` holds the exact wording shown to the consumer when they
        // declared, so the art. 11a(4) acknowledgement repeats back what was
        // declared instead of words rebuilt later from a changed locale, a
        // renamed product or a reworded template. Same reasoning the plugin
        // already applies to the art. 16(m) consent text. dbDelta adds the
        // column to an existing table on the next version-gated run.
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            customer_name varchar(191) NOT NULL DEFAULT '',
            customer_email varchar(191) NOT NULL DEFAULT '',
            token varchar(64) NOT NULL DEFAULT '',
            items longtext NOT NULL,
            reason text NOT NULL,
            declaration longtext NOT NULL,
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
