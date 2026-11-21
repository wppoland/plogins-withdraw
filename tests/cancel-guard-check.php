<?php
/**
 * Who may cancel a withdrawal request, and when.
 *
 * existing-checked: tests/customer-scope-check.php - that one checks which rows the My Account list reads; this checks the write path, RequestStatus::cancel().
 *
 * Run inside wp-env:  wp eval-file tests/cancel-guard-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Frontend\RequestStatus;
use Withdraw\Plugin;
use Withdraw\Service\AccessLink;
use Withdraw\Service\RequestRepository;

require __DIR__ . '/_gap-harness.php';

$c      = Plugin::instance()->container();
$repo   = $c->get(RequestRepository::class);
$status = $c->get(RequestStatus::class);
$links  = $c->get(AccessLink::class);

$owner = wp_insert_user(['user_login' => 'cg_own_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'cg-own-' . time() . '@example.com']);
$other = wp_insert_user(['user_login' => 'cg_oth_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'cg-oth-' . time() . '@example.com']);

$p     = wd_product('Cancel check lamp');
$order = wd_order([[$p, 1]], time());
$order->set_customer_id($owner);
$order->save();
$itemId = array_values(wd_item_ids($order))[0];
$line   = [['item_id' => $itemId, 'product_id' => $p->get_id(), 'name' => 'Cancel check lamp', 'qty' => 1]];

$fired = 0;
add_action('withdraw/cancelled', static function () use (&$fired): void {
    ++$fired;
});

$hash = static fn (): string => hash('sha256', (string) wp_rand());

// Another customer, no key: refused.
$a = $repo->create($order->get_id(), 'gap@example.com', $line, '', $hash(), 'Owner');
wp_set_current_user($other);
wd_ok(! $status->cancel($a, ''), 'another signed-in customer cannot cancel');
wd_ok(! $status->cancel($a, str_repeat('a', 32)), 'a made-up key is refused');
wd_ok($repo->find($a)->status === 'pending', 'the request is still pending after both attempts');

// A guest with the key from the acknowledgement: allowed.
wp_set_current_user(0);
wd_ok($status->cancel($a, $links->statusKey($repo->find($a))), 'a guest with the status key can cancel');
wd_ok($repo->find($a)->status === 'cancelled', 'status is cancelled');
wd_ok($fired === 1, 'withdraw/cancelled fired once');
wd_ok(! $status->cancel($a, $links->statusKey($repo->find($a))), 'a cancelled request cannot be cancelled twice');

// The shop can neither undo a cancellation nor set one.
wd_ok(! $repo->updateStatus($a, 'accepted'), 'the shop cannot move a cancelled request to another status');
wd_ok($repo->find($a)->status === 'cancelled', 'the cancelled status is untouched');

// The cancelled request no longer blocks the goods.
wd_ok(($repo->committedQuantities($order->get_id())[$itemId] ?? 0) === 0, 'a cancelled request commits no quantity');

// The owner, signed in: allowed while pending, refused once the shop decided.
$b = $repo->create($order->get_id(), 'gap@example.com', $line, '', $hash(), 'Owner');
$repo->updateStatus($b, 'accepted');
wp_set_current_user($owner);
wd_ok(! $status->cancel($b, ''), 'the owner cannot cancel after the shop accepted');
wd_ok($repo->find($b)->status === 'accepted', 'the accepted status is untouched');

wd_ok(! $repo->updateStatus($b, 'cancelled'), 'the shop cannot set cancelled itself');
$repo->updateStatus($b, 'pending');
wd_ok($status->cancel($b, ''), 'the owner can cancel while pending');
wd_ok($fired === 2, 'withdraw/cancelled fired for the owner too');

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user(0);
wp_delete_user($owner);
wp_delete_user($other);

wd_done();
