<?php
/**
 * Declarations that match no order (1.8.0): recorded for the shop to match by
 * hand instead of being lost, behind the accept_unmatched setting.
 *
 * Run inside wp-env:  wp eval-file tests/unmatched-declaration-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Migrator;
use Withdraw\Plugin;
use Withdraw\Service\WithdrawalService;

require __DIR__ . '/_gap-harness.php';

global $wpdb;
$clearThrottle = static function () use ($wpdb): void {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wd\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wd\\_rl\\_%'");
    wp_cache_flush();
};
$clearThrottle();

$savedSettings = get_option(Migrator::OPTION_SETTINGS);
$svc  = Plugin::instance()->container()->get(WithdrawalService::class);
$post = static function (array $fields) use ($svc): string {
    $_POST = $fields;
    $out   = $svc->renderShortcode();
    $_POST = [];
    return $out;
};
$typed = ['withdraw_order' => '999999', 'withdraw_email' => 'typo@example.com'];
$offer = 'Send the declaration without a matching order';

echo "1. setting off: a failed lookup offers nothing new\n";
wd_settings(['accept_unmatched' => false, 'magic_link' => false]);
$html = $post($typed + ['withdraw_step' => 'items', 'withdraw_nonce' => wp_create_nonce('withdraw_lookup')]);
wd_ok(str_contains($html, 'could not find an order') && ! str_contains($html, $offer), 'off: only the not-found error');
$html = $post($typed + ['withdraw_step' => 'unmatched', 'withdraw_nonce' => wp_create_nonce('withdraw_lookup')]);
wd_ok(! str_contains($html, 'withdraw_goods'), 'off: the unmatched step is closed server side');

echo "2. setting on: lookup, edit, review, confirm\n";
wd_settings(['accept_unmatched' => true, 'magic_link' => false]);
$html = $post($typed + ['withdraw_step' => 'items', 'withdraw_nonce' => wp_create_nonce('withdraw_lookup')]);
wd_ok(str_contains($html, $offer), 'on: a failed lookup offers the unmatched path');

$html = $post($typed + ['withdraw_step' => 'unmatched', 'withdraw_nonce' => wp_create_nonce('withdraw_lookup')]);
wd_ok(str_contains($html, 'withdraw_goods') && str_contains($html, 'value="999999"'), 'edit step pre-fills what was typed');

$filled = $typed + ['withdraw_name' => 'Ann Typo', 'withdraw_goods' => 'Blue mug x1', 'withdraw_reason' => ''];
$html   = $post($filled + ['withdraw_step' => 'unmatched_review', 'withdraw_nonce' => wp_create_nonce('withdraw_unmatched')]);
wd_ok(str_contains($html, 'Confirm withdrawal') && str_contains($html, 'Blue mug x1'), 'review step shows the statement and the confirm control');

$declared = 0;
$unverified = 0;
add_action('withdraw/declared', static function () use (&$declared): void { ++$declared; });
add_action('withdraw/declared_unverified', static function () use (&$unverified): void { ++$unverified; });

$html = $post($filled + ['withdraw_step' => 'unmatched_confirm', 'withdraw_nonce' => wp_create_nonce('withdraw_unmatched_confirm')]);
wd_ok(str_contains($html, 'Please tick the declaration'), 'confirm without the tick is refused');

$html = $post($filled + ['withdraw_step' => 'unmatched_confirm', 'withdraw_declare' => '1', 'withdraw_nonce' => wp_create_nonce('withdraw_unmatched_confirm')]);
wd_ok(str_contains($html, 'Withdrawal declaration received'), 'confirm records the declaration');

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE customer_email = %s ORDER BY id DESC LIMIT 1', Migrator::table(), 'typo@example.com'));
wd_ok($row !== null && (int) $row->order_id === 0, 'stored without an order');
wd_ok($row !== null && (string) ($row->order_hint ?? '') === '999999', 'the typed order number is kept');
wd_ok($row !== null && str_contains((string) $row->declaration, 'Ann Typo'), 'the declaration text is frozen');
wd_ok($unverified === 1 && $declared === 0, 'fires withdraw/declared_unverified, not withdraw/declared');

echo "3. the shop acting on an unmatched row\n";
$repo = Plugin::instance()->container()->get(\Withdraw\Service\RequestRepository::class);
$sent = [];
$capture = static function ($args) use (&$sent) { $sent[] = (string) $args['subject']; return $args; };
add_filter('wp_mail', $capture);
foreach (['accepted', 'rejected', 'processed'] as $status) {
    $rid = $repo->create(0, 'typo@example.com', [], '', 'tok', 'Ann Typo', 'decl', false, '999999');
    $repo->updateStatus($rid, $status, 'checked by hand');
    do_action('withdraw/status_changed', $rid, $status, 'pending');
    $wpdb->delete(Migrator::table(), ['id' => $rid]); // phpcs:ignore
}
remove_filter('wp_mail', $capture);
wd_ok(count($sent) === 3, 'accepted, rejected and processed each send without a fatal');
wd_ok($sent !== [] && str_contains($sent[0], '999999 (not matched)'), 'the email names the typed order number');

if ($row !== null) {
    $wpdb->delete(Migrator::table(), ['id' => (int) $row->id]); // phpcs:ignore
}
$clearThrottle();
$savedSettings === false ? delete_option(Migrator::OPTION_SETTINGS) : update_option(Migrator::OPTION_SETTINGS, $savedSettings);
wd_done();
