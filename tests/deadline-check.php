<?php
/**
 * Withdrawal deadline (1.8.0): Regulation 1182/71 Art. 3 counting, grace days,
 * the withdraw/delivery_date filter and advisory mode.
 *
 * Run inside wp-env:  wp eval-file tests/deadline-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Plugin;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\WithdrawalService;

require __DIR__ . '/_gap-harness.php';

$savedSettings = get_option(\Withdraw\Migrator::OPTION_SETTINGS);
$savedTz       = get_option('timezone_string');
update_option('timezone_string', 'Europe/Warsaw');
$tz = wp_timezone();
$at = static fn (string $local): int => (new DateTimeImmutable($local, $tz))->getTimestamp();

$product = wd_product('Deadline check');
$orders  = [];

echo "1. counting under Regulation 1182/71 Art. 3\n";
$wed = $orders[] = wd_order([[$product, 1]], $at('2026-10-07 10:00:00'));
wd_ok(WithdrawalService::deadline($wed, 14) === $at('2026-10-21 23:59:59'), 'start day not counted, ends at the last second of day 14');

$sat = $orders[] = wd_order([[$product, 1]], $at('2026-10-03 23:30:00'));
wd_ok(WithdrawalService::deadline($sat, 14) === $at('2026-10-19 23:59:59'), 'last day on a Saturday moves to Monday');

wd_ok(WithdrawalService::deadline($wed, 14, 3) === $at('2026-10-24 23:59:59'), 'grace days are added on top');

echo "2. delivery date filter\n";
$filter = static fn ($v, $o) => $o->get_id() === $wed->get_id() ? $at('2026-10-09 15:00:00') : $v;
add_filter('withdraw/delivery_date', $filter, 10, 2);
wd_ok(WithdrawalService::deadline($wed, 14) === $at('2026-10-23 23:59:59'), 'filtered delivery date is the start');
remove_filter('withdraw/delivery_date', $filter, 10);

echo "3. advisory mode\n";
$old = $orders[] = wd_order([[$product, 1]], time() - 40 * DAY_IN_SECONDS);
wd_settings(['period_days' => 14]);
$strict = wd_call('eligibility', [$old]);
wd_ok($strict['eligible'] === false, 'strict: a late order is refused');
wd_settings(['period_days' => 14, 'deadline_mode' => 'advisory']);
$advisory = wd_call('eligibility', [$old]);
wd_ok($advisory['eligible'] === true, 'advisory: a late order is accepted');
wd_ok(($advisory['late'] ?? null) === true, 'advisory: and flagged late');
wd_ok(WithdrawalService::withinPeriod($old, get_option(\Withdraw\Migrator::OPTION_SETTINGS)) === false, 'advisory: links still end at the deadline');

$repo = Plugin::instance()->container()->get(RequestRepository::class);
$id   = $repo->create($old->get_id(), 'gap@example.com', [], '', 'tok', 'Gap', 'decl', true);
$row  = $repo->find($id);
wd_ok($row !== null && (int) ($row->late_submission ?? 0) === 1, 'the stored row carries the late flag');
global $wpdb;
$wpdb->delete(\Withdraw\Migrator::table(), ['id' => $id]); // phpcs:ignore

foreach ($orders as $o) {
    $o->delete(true);
}
$product->delete(true);
$savedSettings === false ? delete_option(\Withdraw\Migrator::OPTION_SETTINGS) : update_option(\Withdraw\Migrator::OPTION_SETTINGS, $savedSettings);
update_option('timezone_string', $savedTz);
wd_done();
