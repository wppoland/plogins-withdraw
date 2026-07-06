<?php

declare(strict_types=1);

/**
 * Plogins Withdraw uninstall: drop the requests table and delete options.
 *
 * @package Withdraw
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

delete_option('withdraw_settings');
delete_option('withdraw_schema_version');

$withdraw_table = $wpdb->prefix . 'withdraw_requests';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $withdraw_table));
