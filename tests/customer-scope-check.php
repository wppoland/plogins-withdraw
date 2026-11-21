<?php
/**
 * The My Account Withdrawals tab shows a customer their own requests only.
 *
 * existing-checked: tests/review-fixes-check.php - that file covers the 2026-10-02 review; this is a new feature with its own run.
 *
 * Run inside wp-env:  wp eval-file tests/customer-scope-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Plugin;
use Withdraw\Service\RequestRepository;

require __DIR__ . '/_gap-harness.php';

$repo = Plugin::instance()->container()->get(RequestRepository::class);
$p    = wd_product('Scope check mug');

$userA = wp_insert_user(['user_login' => 'scope_a_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'scope-a-' . time() . '@example.com']);
$userB = wp_insert_user(['user_login' => 'scope_b_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'scope-b-' . time() . '@example.com']);
$mailA = get_userdata($userA)->user_email;

$own = wd_order([[$p, 1]], time());
$own->set_customer_id($userA);
$own->save();

$other = wd_order([[$p, 1]], time());
$other->set_customer_id($userB);
$other->save();

$guest = wd_order([[$p, 1]], time()); // customer_id 0, same email as A
$guest->set_billing_email($mailA);
$guest->save();

$mine     = $repo->create($own->get_id(), $mailA, [], '', '', 'A');
$onB      = $repo->create($other->get_id(), $mailA, [], '', '', 'A typed on B'); // A's email on B's order
$onGuest  = $repo->create($guest->get_id(), $mailA, [], '', '', 'A as guest');
$unmatched = $repo->create(0, $mailA, [], '', '', 'no order');

$ids = static fn (array $rows): array => array_map(static fn ($r): int => (int) $r->id, $rows);
$a   = $ids($repo->forCustomer($userA));

wd_ok(in_array($mine, $a, true), 'A sees the request on their own order');
wd_ok(! in_array($onB, $a, true), "A does not see a request on B's order, even with A's email on it");
wd_ok(! in_array($onGuest, $a, true), 'A does not see a guest order that carries their email');
wd_ok(! in_array($unmatched, $a, true), 'A does not see a declaration that matched no order');
wd_ok(in_array($onB, $ids($repo->forCustomer($userB)), true), 'B sees the request on their order');
wd_ok($repo->forCustomer(0) === [], 'a logged-out visitor (user 0) gets nothing, not every guest order');
wd_ok(count($repo->forCustomer($userA, 1, 1)) === 0, 'limit and offset bound the list');

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user($userA);
wp_delete_user($userB);

wd_done();
