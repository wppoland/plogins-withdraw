<?php
/**
 * Art. 16(c)-(e) exceptions per product and per category (1.8.0).
 *
 * Run inside wp-env:  wp eval-file tests/product-exclusion-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Migrator;

require __DIR__ . '/_gap-harness.php';

$meta = '_withdraw_exclusion';
$savedSettings = get_option(Migrator::OPTION_SETTINGS);
wd_settings(['period_days' => 14]);

$cat = wp_insert_term('Fresh food ' . wp_generate_password(4, false), 'product_cat');
$catId = is_array($cat) ? (int) $cat['term_id'] : 0;
update_term_meta($catId, $meta, 'perishable');

$custom = wd_product('Engraved ring');
update_post_meta($custom->get_id(), $meta, 'custom');
$fresh  = wd_product('Strawberries', $catId);
$sealed = wd_product('Earplugs');
update_post_meta($sealed->get_id(), $meta, 'sealed');
$plain  = wd_product('Plain mug');

echo "1. eligibility honours product and category marks\n";
$order = wd_order([[$custom, 1], [$fresh, 1], [$sealed, 1], [$plain, 1]], time() - DAY_IN_SECONDS);
$ids   = wd_item_ids($order);
$e     = wd_call('eligibility', [$order]);
$ex    = $e['excluded'];
wd_ok(in_array($ids['Engraved ring'], $ex, true), 'Art. 16(c) product meta excludes the line');
wd_ok(in_array($ids['Strawberries'], $ex, true), 'Art. 16(d) category meta excludes the line');
wd_ok(! in_array($ids['Earplugs'], $ex, true), 'Art. 16(e) sealed goods stay withdrawable');
wd_ok(! in_array($ids['Plain mug'], $ex, true), 'unmarked product stays withdrawable');

$_POST['withdraw_qty'] = array_fill_keys(array_values($ids), 1);
$picked = array_column(wd_call('selectedItems', [$order, $e['remaining'] ?? $ex]), 'item_id');
unset($_POST['withdraw_qty']);
wd_ok(! in_array($ids['Engraved ring'], $picked, true) && in_array($ids['Plain mug'], $picked, true), 'a posted excluded line is dropped on the server');

$only = wd_order([[$custom, 1]], time() - DAY_IN_SECONDS);
$e2   = wd_call('eligibility', [$only]);
wd_ok($e2['eligible'] === false && str_contains($e2['reason'], 'does not apply'), 'an order of excluded goods only is refused');

echo "2. product page notice (Art. 6(1)(k))\n";
$GLOBALS['product'] = wc_get_product($custom->get_id());
ob_start();
do_action('woocommerce_single_product_summary');
$html = (string) ob_get_clean();
wd_ok(str_contains($html, 'withdraw-product-notice') && str_contains($html, 'personalised'), 'custom product shows the notice');
$GLOBALS['product'] = wc_get_product($plain->get_id());
ob_start();
do_action('woocommerce_single_product_summary');
wd_ok(! str_contains((string) ob_get_clean(), 'withdraw-product-notice'), 'unmarked product shows nothing');
unset($GLOBALS['product']);

echo "3. the mark is fixed at purchase (Art. 6(1)(k) disclosure)\n";
$before = wd_product('Mug sold plain');
$after  = wd_product('Ring sold engraved');
update_post_meta($after->get_id(), $meta, 'custom');
// Built the way checkout builds it: classic and Store API both fire this hook.
$bought = wc_create_order();
foreach ([$before, $after] as $p) {
    $line = new WC_Order_Item_Product();
    $line->set_product($p);
    $line->set_quantity(1);
    do_action('woocommerce_checkout_create_order_line_item', $line, 'k' . $p->get_id(), [], $bought);
    $bought->add_item($line);
}
$bought->set_billing_email('gap@example.com');
$bought->set_status('completed');
$bought->set_date_completed(time() - DAY_IN_SECONDS);
$bought->save();
// add_product() skips the checkout hook, so this line has no snapshot.
$legacy = wd_order([[$before, 1]], time() - DAY_IN_SECONDS);
// The shop changes its mind after the sale.
update_post_meta($before->get_id(), $meta, 'custom');
update_post_meta($after->get_id(), $meta, '');
$bid = wd_item_ids(wc_get_order($bought->get_id()));
$e3  = wd_call('eligibility', [wc_get_order($bought->get_id())]);
wd_ok(! in_array($bid['Mug sold plain'], $e3['excluded'], true), 'marking a product excluded after the sale does not take the right away');
wd_ok(in_array($bid['Ring sold engraved'], $e3['excluded'], true), 'clearing the mark after the sale does not grant the right');
$e4 = wd_call('eligibility', [wc_get_order($legacy->get_id())]);
wd_ok($e4['eligible'] === false, 'an order without a snapshot (before 1.8.0) follows the current mark');

foreach ([$order, $only, $bought, $legacy] as $o) {
    $o->delete(true);
}
foreach ([$custom, $fresh, $sealed, $plain, $before, $after] as $p) {
    $p->delete(true);
}
wp_delete_term($catId, 'product_cat');
$savedSettings === false ? delete_option(Migrator::OPTION_SETTINGS) : update_option(Migrator::OPTION_SETTINGS, $savedSettings);
wd_done();
