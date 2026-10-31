<?php
/**
 * Partial withdrawals (1.8.0): after one declaration the rest of the order stays
 * withdrawable, quantity by quantity.
 *
 * Run inside wp-env:  wp eval-file tests/partial-remaining-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Migrator;
use Withdraw\Plugin;
use Withdraw\Service\RequestRepository;

require __DIR__ . '/_gap-harness.php';

$savedSettings = get_option(Migrator::OPTION_SETTINGS);
wd_settings(['period_days' => 14]);

$a     = wd_product('Partial A');
$b     = wd_product('Partial B');
$order = wd_order([[$a, 3], [$b, 1]], time() - DAY_IN_SECONDS);
$ids   = wd_item_ids($order);
$repo  = Plugin::instance()->container()->get(RequestRepository::class);
$rows  = [];

$line = static fn (string $n, int $q): array => ['item_id' => $ids[$n], 'product_id' => 0, 'name' => $n, 'qty' => $q];

echo "1. a pending partial leaves the rest open\n";
$rows[] = $repo->create($order->get_id(), 'gap@example.com', [$line('Partial A', 2)], '', 't1');
$e = wd_call('eligibility', [$order]);
wd_ok($e['eligible'] === true, 'order still eligible after a partial declaration');
wd_ok(($e['remaining'] ?? null) === [$ids['Partial A'] => 1, $ids['Partial B'] => 1], 'remaining = ordered less declared');

$_POST['withdraw_qty'] = [$ids['Partial A'] => 5, $ids['Partial B'] => 1];
$picked = wd_call('selectedItems', [$order, $e['remaining'] ?? $e['excluded']]);
unset($_POST['withdraw_qty']);
$qty = array_column($picked, 'qty', 'item_id');
wd_ok(($qty[$ids['Partial A']] ?? 0) === 1, 'a posted quantity is clamped to what remains');

echo "2. rejected covers nothing, processed covers its lines\n";
$rows[] = $id = $repo->create($order->get_id(), 'gap@example.com', [$line('Partial A', 1)], '', 't2');
$repo->updateStatus($id, 'rejected');
wd_ok(((wd_call('eligibility', [$order])['remaining'] ?? [])[$ids["Partial A"]] ?? 0) === 1, 'a rejected declaration frees its quantity');
$rows[] = $id = $repo->create($order->get_id(), 'gap@example.com', [$line('Partial A', 1), $line('Partial B', 1)], '', 't3');
$repo->updateStatus($id, 'processed');
$e = wd_call('eligibility', [$order]);
wd_ok($e['eligible'] === false && str_contains($e['reason'], 'already covered'), 'everything declared: refused with the right reason');

echo "3. a pre-1.6 open row without line ids keeps the old refusal\n";
global $wpdb;
foreach ($rows as $r) {
    $wpdb->delete(Migrator::table(), ['id' => $r]); // phpcs:ignore
}
$rows   = [$repo->create($order->get_id(), 'gap@example.com', [['product_id' => $a->get_id(), 'name' => 'Partial A', 'qty' => 1]], '', 't4')];
$e = wd_call('eligibility', [$order]);
wd_ok($e['eligible'] === false && str_contains($e['reason'], 'already being processed'), 'legacy open row: refused as before');

foreach ($rows as $r) {
    $wpdb->delete(Migrator::table(), ['id' => $r]); // phpcs:ignore
}

echo "4. a replayed confirm (refresh, double click) records one declaration\n";
// Before 1.8.0 any open request blocked the order, so a resubmitted confirm was
// refused. With partial withdrawals the replay would declare one more unit.
wd_settings(['period_days' => 14, 'magic_link' => false]);
$svc     = Plugin::instance()->container()->get(\Withdraw\Service\WithdrawalService::class);
$confirm = [
    'withdraw_step'    => 'confirm',
    'withdraw_order'   => (string) $order->get_order_number(),
    'withdraw_email'   => 'gap@example.com',
    'withdraw_name'    => 'Gap Tester',
    'withdraw_qty'     => [$ids['Partial A'] => 1],
    'withdraw_declare' => '1',
];
foreach ([1, 2] as $_) {
    $_POST = $confirm + ['withdraw_nonce' => wp_create_nonce('withdraw_confirm')];
    $svc->renderShortcode();
    $_POST = [];
}
$count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE order_id = %d', Migrator::table(), $order->get_id())); // phpcs:ignore
wd_ok($count === 1, 'two identical confirms store one row (got ' . $count . ')');
$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE order_id = %d', Migrator::table(), $order->get_id())); // phpcs:ignore

$order->delete(true);
$a->delete(true);
$b->delete(true);
$savedSettings === false ? delete_option(Migrator::OPTION_SETTINGS) : update_option(Migrator::OPTION_SETTINGS, $savedSettings);
wd_done();
