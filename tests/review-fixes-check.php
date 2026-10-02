<?php
/**
 * Regression checks for the 2026-10-02 live review.
 *
 * Run inside wp-env:  wp eval-file tests/review-fixes-check.php
 *
 * 1. A non-numeric order number ("INV-1001") reaches resolveOrderNumber()
 *    instead of being absint()ed to 0 and refused.
 * 2. The privacy eraser removes the name and email from the stored
 *    declaration text, not only from their own columns, and the exporter
 *    hands back the declaration it holds.
 * 3. uninstall.php deletes every option the plugin writes.
 *
 * @package Withdraw/Tests
 */

// No declare(strict_types=1): wp-cli eval-file wraps this in eval().

use Withdraw\Plugin;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\WithdrawalService;
use Withdraw\Service\WithdrawPrivacyService;

$fails = 0;
$ok    = static function (bool $cond, string $label) use (&$fails): void {
    echo ($cond ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (! $cond) {
        ++$fails;
    }
};

$container = Plugin::instance()->container();

/* --- 1. order number lookup ------------------------------------------- */

$email = 'review-check-' . wp_generate_password(6, false) . '@example.com';
$order = wc_create_order();
$order->set_billing_email($email);
$order->update_meta_data('_order_number', 'INV-' . $order->get_id());
$order->save();

$lookup = new ReflectionMethod(WithdrawalService::class, 'lookupOrder');
$lookup->setAccessible(true);
$service = $container->get(WithdrawalService::class);

$found = $lookup->invoke($service, 'INV-' . $order->get_id(), $email);
$ok($found instanceof WC_Order && $found->get_id() === $order->get_id(), 'prefixed order number resolves through _order_number');

$found = $lookup->invoke($service, (string) $order->get_id(), $email);
$ok($found instanceof WC_Order && $found->get_id() === $order->get_id(), 'plain order id still resolves');

$found = $lookup->invoke($service, 'INV-' . $order->get_id(), 'someone-else@example.com');
$ok($found === null, 'wrong billing email is still refused');

/* --- 2. privacy eraser and exporter ----------------------------------- */

/** @var RequestRepository $repo */
$repo = $container->get(RequestRepository::class);
$name = 'Review Checkperson';
$decl = "I hereby give notice that I withdraw.\nName of consumer: {$name}\nContact: {$email}";
$id   = $repo->create($order->get_id(), $email, [], 'a reason', 'tok', $name, $decl);

$privacy = $container->get(WithdrawPrivacyService::class);
$export  = $privacy->exportWithdrawals($email);
$values  = wp_list_pluck($export['data'][0]['data'] ?? [], 'value');
$ok(in_array($decl, $values, true), 'export includes the stored declaration');

$privacy->eraseWithdrawals($email);
$row = $repo->find($id);
$ok(is_object($row) && stripos((string) $row->declaration, $email) === false, 'erased declaration no longer holds the email');
$ok(is_object($row) && stripos((string) $row->declaration, $name) === false, 'erased declaration no longer holds the name');
$ok(is_object($row) && $row->customer_name === '' && $row->reason === '', 'name and reason columns cleared');
$ok(is_object($row) && str_contains((string) $row->declaration, 'I hereby give notice that I withdraw.'), 'rest of the declaration is kept');

global $wpdb;
$wpdb->delete(Withdraw\Migrator::table(), ['id' => $id], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$order->delete(true);

/* --- 3. uninstall covers every option --------------------------------- */

$uninstall = (string) file_get_contents(WITHDRAW_DIR . 'uninstall.php');
$migrator  = (string) file_get_contents(WITHDRAW_DIR . 'src/Migrator.php');
preg_match_all("/OPTION_[A-Z_]+\s*=\s*'([a-z_]+)'/", $migrator, $m);
foreach ($m[1] as $option) {
    $ok(str_contains($uninstall, "'" . $option . "'"), "uninstall deletes {$option}");
}
foreach (glob(WITHDRAW_DIR . 'src/Email/*.php') ?: [] as $file) {
    if (preg_match("/this->id\s*=\s*'withdraw_([a-z_]+)'/", (string) file_get_contents($file), $e)) {
        $ok(str_contains($uninstall, "'" . $e[1] . "'"), "uninstall deletes woocommerce_withdraw_{$e[1]}_settings");
    }
}

echo $fails === 0 ? "\nAll checks passed.\n" : "\n{$fails} check(s) failed.\n";
