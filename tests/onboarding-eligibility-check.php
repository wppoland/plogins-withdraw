<?php
/**
 * Fresh-install onboarding and the withdrawal-period gate (1.7.0).
 *
 * Run inside wp-env:  wp eval-file tests/onboarding-eligibility-check.php
 *
 * 1. A fresh install (no settings option) gets a DRAFT form page, selected as
 *    form_page_id, with the footer link on. An existing install is untouched.
 * 2. While that page is a draft, nothing links to it.
 * 3. The My Account orders-list action and the processing/completed email
 *    link appear inside the withdrawal period and disappear after it.
 *
 * @package Withdraw/Tests
 */

// No declare(strict_types=1): wp-cli eval-file wraps this in eval().

use Withdraw\Frontend\MyAccount;
use Withdraw\Frontend\WithdrawLink;
use Withdraw\Migrator;
use Withdraw\Plugin;

$fails = 0;
$ok    = static function (bool $cond, string $label) use (&$fails): void {
    echo ($cond ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (! $cond) {
        ++$fails;
    }
};

$saved      = get_option(Migrator::OPTION_SETTINGS);
$savedSetup = get_option(Migrator::OPTION_SETUP_PAGE);
$created    = [];

$formPages = static fn (): array => get_posts([
    'post_type'      => 'page',
    'post_status'    => 'any',
    's'              => '[withdraw_form]',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

// Start from a site with no form page at all, so "fresh" really is fresh.
$preexisting = $formPages();
foreach ($preexisting as $id) {
    wp_update_post(['ID' => $id, 'post_status' => 'trash']);
}

/* --- 1. fresh install ------------------------------------------------- */

echo "1. fresh vs existing install\n";
delete_option(Migrator::OPTION_SETTINGS);
delete_option(Migrator::OPTION_SETUP_PAGE);
Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();

$s      = get_option(Migrator::OPTION_SETTINGS);
$pageId = (int) ($s['form_page_id'] ?? 0);
$created[] = $pageId;
$ok($pageId > 0, 'fresh install selects a form page');
$ok(get_post_status($pageId) === 'draft', 'the page is a draft, not published');
$ok(str_contains((string) get_post_field('post_content', $pageId), '[withdraw_form]'), 'the page holds [withdraw_form]');
$ok(! empty($s['footer_link']), 'fresh install turns the footer link on');
$ok((int) get_option(Migrator::OPTION_SETUP_PAGE) === $pageId, 'setup notice points at the draft');

// Existing install: settings present, no page selected. Nothing may change.
$existing = array_merge($s, ['form_page_id' => 0, 'footer_link' => false]);
update_option(Migrator::OPTION_SETTINGS, $existing);
$before = count($formPages());
Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();
$after = get_option(Migrator::OPTION_SETTINGS);
$ok(count($formPages()) === $before, 'existing install creates no page');
$ok((int) $after['form_page_id'] === 0 && empty($after['footer_link']), 'existing install settings untouched');

// Reinstall after uninstall: the old page is reused, not duplicated.
delete_option(Migrator::OPTION_SETTINGS);
Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();
$ok((int) get_option(Migrator::OPTION_SETTINGS)['form_page_id'] === $pageId, 'reinstall reuses the earlier page');
$ok(count($formPages()) === $before, 'reinstall creates no duplicate');

/* --- 2. a draft is never linked --------------------------------------- */

echo "2. draft page links nowhere\n";
$s = WithdrawLink::settings();
$ok(WithdrawLink::formUrl($s) === '', 'formUrl is empty while the page is a draft');
wp_update_post(['ID' => $pageId, 'post_status' => 'publish']);
$ok(WithdrawLink::formUrl($s) !== '', 'formUrl resolves once published');

/* --- 3. eligibility gate: list and email ------------------------------ */

echo "3. withdrawal-period gate\n";
update_option(Migrator::OPTION_SETTINGS, array_merge($s, ['period_days' => 14, 'eligible_statuses' => ['completed', 'processing']]));

$product = new WC_Product_Simple();
$product->set_name('Gate check');
$product->set_regular_price('10');
$product->save();

$makeOrder = static function (int $daysAgo) use ($product): WC_Order {
    $order = wc_create_order();
    $order->add_product($product, 1);
    $order->set_billing_email('gate@example.com');
    $order->calculate_totals();
    $order->set_status('completed');
    $order->set_date_completed(time() - $daysAgo * DAY_IN_SECONDS);
    $order->save();
    return $order;
};

$fresh = $makeOrder(2);
$stale = $makeOrder(20);

$account = Plugin::instance()->container()->get(MyAccount::class);
$inList  = static fn (WC_Order $o): bool => isset($account->addOrderAction([], $o)['withdraw']);
$ok($inList($fresh), 'orders list: action shown within the period');
$ok(! $inList($stale), 'orders list: action hidden after the period');

$mailer = WC()->mailer();
$emails = $mailer->get_emails();
$link   = new WithdrawLink();
$render = static function (WC_Order $o, string $class, bool $plain, bool $admin = false) use ($link, $emails): string {
    ob_start();
    $link->renderEmailLink($o, $admin, $plain, $emails[$class]);
    return (string) ob_get_clean();
};
$url = WithdrawLink::formUrl(WithdrawLink::settings());

foreach (['WC_Email_Customer_Processing_Order', 'WC_Email_Customer_Completed_Order'] as $class) {
    $ok(str_contains($render($fresh, $class, false), esc_url($url)), "$class HTML: link within the period");
    $ok(str_contains($render($fresh, $class, true), $url), "$class plain: link within the period");
    $ok($render($stale, $class, false) === '' && $render($stale, $class, true) === '', "$class: no link after the period");
}
$ok($render($fresh, 'WC_Email_New_Order', false) === '', 'admin new-order email: no link');
$ok($render($fresh, 'WC_Email_Customer_Processing_Order', false, true) === '', 'sent_to_admin: no link');

// Also through the real hook, so the registration itself is checked.
ob_start();
do_action('woocommerce_email_after_order_table', $fresh, false, false, $emails['WC_Email_Customer_Completed_Order']);
$ok(str_contains((string) ob_get_clean(), 'withdraw-email-link'), 'hooked on woocommerce_email_after_order_table');

update_option(Migrator::OPTION_SETTINGS, array_merge(WithdrawLink::settings(), ['form_page_id' => 0]));
$ok($render($fresh, 'WC_Email_Customer_Completed_Order', false) === '', 'no form page: no email link');
$ok(! $inList($fresh), 'no form page: no list action');

/* --- cleanup ---------------------------------------------------------- */

foreach ([$fresh, $stale] as $o) {
    $o->delete(true);
}
$product->delete(true);
foreach (array_unique($created) as $id) {
    wp_delete_post($id, true);
}
foreach ($preexisting as $id) {
    wp_untrash_post($id);
}
$saved === false ? delete_option(Migrator::OPTION_SETTINGS) : update_option(Migrator::OPTION_SETTINGS, $saved);
$savedSetup === false ? delete_option(Migrator::OPTION_SETUP_PAGE) : update_option(Migrator::OPTION_SETUP_PAGE, $savedSetup);

echo $fails === 0 ? "ALL GREEN\n" : "{$fails} FAILED\n";
if ($fails > 0) {
    exit(1);
}
