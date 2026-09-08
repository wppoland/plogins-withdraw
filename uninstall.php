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

// Access-link tokens and their rate counters are transients, so their option
// names are derived from a hash and cannot be deleted by name. The timeout twin
// goes with each of them.
$withdraw_link_like  = $wpdb->esc_like('_transient_wd_link_') . '%';
$withdraw_link_tmout = $wpdb->esc_like('_transient_timeout_wd_link_') . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $withdraw_link_like,
    $withdraw_link_tmout
));

$withdraw_rl_like  = $wpdb->esc_like('_transient_wd_rl_') . '%';
$withdraw_rl_tmout = $wpdb->esc_like('_transient_timeout_wd_rl_') . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $withdraw_rl_like,
    $withdraw_rl_tmout
));
