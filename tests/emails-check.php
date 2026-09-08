<?php
/**
 * Does every withdrawal email actually leave the building?
 *
 * Run inside wp-env:  wp eval-file tests/emails-check.php
 *
 * Not a unit test. It drives the real actions on a real order and watches
 * wp_mail(), because the failure this guards against is silence: a WC_Email
 * whose trigger was registered by a constructor nobody ran sends nothing and
 * reports nothing.
 *
 * @package Withdraw/Tests
 */

// No declare(strict_types=1): wp-cli eval-file wraps this in eval().

use Withdraw\Migrator;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\ReturnPolicy;

$fails = 0;
$ok    = static function (bool $cond, string $label) use (&$fails): void {
    echo ($cond ? "  ok   " : "  FAIL ") . $label . "\n";
    if (! $cond) {
        ++$fails;
    }
};

/* --- the trap, armed before anything can spring it -------------------- */

$classesBuilt = false;
$probe        = static function ($e) use (&$classesBuilt) {
    $classesBuilt = true;

    return $e;
};
add_filter('woocommerce_email_classes', $probe, 99);

/** How many callbacks are listening on a hook. */
$listeners = static function (string $hook): int {
    global $wp_filter;
    if (! isset($wp_filter[$hook])) {
        return 0;
    }
    $n = 0;
    foreach ($wp_filter[$hook]->callbacks as $callbacks) {
        $n += count($callbacks);
    }

    return $n;
};

/**
 * Put the site back into the state a storefront POST starts from: no mailer
 * built yet, and nothing left over from an earlier pass still listening.
 *
 * Without this the run proves the wrong thing. WP-CLI builds WC_Emails during
 * bootstrap, so the very trap being tested cannot occur, and every email object
 * is a cached instance that read its `enabled` option once, minutes ago.
 *
 * The email classes register their triggers from their constructors, so a stale
 * registration of the filter that builds them means a second set of objects and
 * two of every message. That is a property of this reset, not of the plugin: in
 * a request the filter is registered once.
 */
$freshRequest = static function () use (&$classesBuilt, $probe): void {
    remove_all_actions('withdraw/declared');
    remove_all_actions('withdraw/status_changed');
    remove_all_actions('withdraw/link_issued');
    remove_all_filters('woocommerce_email_classes');

    $instance = new ReflectionProperty('WC_Emails', 'instance');
    $instance->setAccessible(true);
    $instance->setValue(null, null);

    $classesBuilt = false;

    (new \Withdraw\Service\EmailService())->registerHooks();
    add_filter('woocommerce_email_classes', $probe, 99);
};

/* --- capture outgoing mail -------------------------------------------- */

$sent = [];
add_filter('pre_wp_mail', static function ($short, $atts) use (&$sent) {
    $sent[] = $atts;

    return true; // Short-circuit: nothing leaves the container.
}, 10, 2);

$drain = static function () use (&$sent): array {
    $out  = $sent;
    $sent = [];

    return $out;
};

/* --- schema ------------------------------------------------------------ */

global $wpdb;
$table = Migrator::table();
$cols  = $wpdb->get_col("DESC {$table}", 0);
$ok(in_array('declaration', (array) $cols, true), 'requests table has a declaration column');

$wpdb->query("TRUNCATE TABLE {$table}");

/* --- an order to withdraw from ----------------------------------------- */

$product = new WC_Product_Simple();
$product->set_name('Test lamp');
$product->set_regular_price('120');
$product->save();

$order = new WC_Order();
$order->set_billing_email('buyer@example.test');
$order->set_billing_first_name('Anna');
$order->add_product($product, 2);
$order->set_status('completed');
$order->calculate_totals();
$order->save();

$repo        = new RequestRepository();
$items       = [['product_id' => $product->get_id(), 'name' => 'Test lamp', 'qty' => 2]];
$declaration = "I hereby give notice that I withdraw from my contract of sale of Test lamp x2.\nOrdered on 1 September 2026. Anna Nowak.";
$submittedAt = (int) current_time('timestamp');

$id = $repo->create($order->get_id(), 'buyer@example.test', $items, 'Too bright', 'tok123', 'Anna Nowak', $declaration);
$ok($id > 0, 'declaration row stored');

$stored = $repo->find($id);
$ok(is_object($stored) && (string) $stored->declaration === $declaration, 'declaration comes back byte for byte');

/* --- 1. the lazy-mailer trap ------------------------------------------- */

$freshRequest();
$ok($classesBuilt === false, 'nothing built the mailer before the trigger fired');
$ok($listeners('withdraw/declared') === 1, 'only the priority-1 mailer loader listens at this point, found ' . $listeners('withdraw/declared'));

$drain();
do_action('withdraw/declared', $id, $order, $submittedAt);
$declared = $drain();

$ok($classesBuilt === true, 'firing withdraw/declared built the mailer');
$ok(count($declared) === 2, 'withdraw/declared sent 2 messages, got ' . count($declared));

$toCustomer = array_values(array_filter($declared, static fn (array $m): bool => $m['to'] === 'buyer@example.test'));
$toShop     = array_values(array_filter($declared, static fn (array $m): bool => $m['to'] !== 'buyer@example.test'));

$ok($toCustomer !== [], 'the customer got the acknowledgement');
$ok($toShop !== [], 'the shop got the notification at ' . ($toShop[0]['to'] ?? 'nowhere'));

/* --- 2. art. 11a(4) must not have regressed ---------------------------- */

$ackBody = $toCustomer[0]['message'] ?? '';
$ackHtml = html_entity_decode(wp_strip_all_tags($ackBody), ENT_QUOTES);

$ok(str_contains($ackHtml, 'I hereby give notice that I withdraw'), 'acknowledgement repeats the declaration back');
$ok(str_contains($ackHtml, 'Anna Nowak'), 'acknowledgement carries the name on the contract');

$stamp = date_i18n((string) get_option('date_format') . ' ' . (string) get_option('time_format'), $submittedAt);
$ok(str_contains($ackHtml, $stamp), 'acknowledgement states the date AND time (' . $stamp . ')');

/* --- 3. accepted: the art. 14(1) information --------------------------- */

update_option('woocommerce_store_address', 'ul. Testowa 1');
update_option('woocommerce_store_city', 'Warszawa');
update_option('woocommerce_store_postcode', '00-001');

$settings = get_option(Migrator::OPTION_SETTINGS, []);
$settings = is_array($settings) ? $settings : [];

update_option(Migrator::OPTION_SETTINGS, array_merge($settings, [
    'return_address'   => '',
    'return_cost'      => 'not_stated',
    'return_cost_note' => '',
]));

$repo->updateStatus($id, 'accepted');
$drain();
do_action('withdraw/status_changed', $id, 'accepted', 'pending');
$accepted = $drain();

$ok(count($accepted) === 1, 'accepted sent exactly 1 message, got ' . count($accepted));
$accHtml = html_entity_decode(wp_strip_all_tags($accepted[0]['message'] ?? ''), ENT_QUOTES);

$deadline = date_i18n((string) get_option('date_format'), $submittedAt + (14 * DAY_IN_SECONDS));
$ok(str_contains($accHtml, $deadline), 'accepted states the return deadline ' . $deadline);
$ok(str_contains($accHtml, 'ul. Testowa 1'), 'accepted falls back to the WooCommerce store address');
$ok(! str_contains($accHtml, 'bear the direct cost'), 'with the cost unset, accepted says nothing about who pays');

// Now the shop states the rule.
update_option(Migrator::OPTION_SETTINGS, array_merge($settings, [
    'return_address'   => "Zwroty Sp. z o.o.\nul. Magazynowa 7\n02-234 Warszawa",
    'return_cost'      => 'customer',
    'return_cost_note' => 'Bulky items are collected by courier, around 40 EUR.',
]));

$drain();
do_action('withdraw/status_changed', $id, 'accepted', 'pending');
$accepted2 = $drain();
$accHtml2  = html_entity_decode(wp_strip_all_tags($accepted2[0]['message'] ?? ''), ENT_QUOTES);

$ok(str_contains($accHtml2, 'ul. Magazynowa 7'), 'the configured return address wins over the store address');
$ok(str_contains($accHtml2, 'You bear the direct cost of returning the goods.'), 'accepted states that the customer pays');
$ok(str_contains($accHtml2, 'around 40 EUR'), 'the cost note is printed');

/* --- 4. the other three statuses --------------------------------------- */

foreach ([
    'rejected'  => 'could not be accepted',
    'processed' => 'refund has been issued',
    'pending'   => 'being reviewed',
] as $status => $needle) {
    $repo->updateStatus($id, $status);
    $drain();
    do_action('withdraw/status_changed', $id, $status, 'accepted');
    $got = $drain();

    $body = html_entity_decode(wp_strip_all_tags($got[0]['message'] ?? ''), ENT_QUOTES);
    $ok(count($got) === 1, $status . ' sent exactly 1 message, got ' . count($got));
    $ok(str_contains($body, $needle), $status . ' says the right thing');
}

/* --- 5. one status must not wake the other three ----------------------- */

$drain();
do_action('withdraw/status_changed', $id, 'processed', 'pending');
$one = $drain();
$ok(count($one) === 1, 'a status change wakes one email, not four (got ' . count($one) . ')');

/* --- 6. a switched-off email stays quiet -------------------------------- */

update_option('woocommerce_withdraw_processed_settings', ['enabled' => 'no']);
// The switch is read when the object is built, so it only takes effect on a
// request that builds one. Reusing the instance from the pass above would test
// nothing but PHP object identity.
$freshRequest();
$drain();
do_action('withdraw/status_changed', $id, 'processed', 'pending');
$off = $drain();
$ok($off === [], 'a disabled email sends nothing (got ' . count($off) . ')');
delete_option('woocommerce_withdraw_processed_settings');
$freshRequest();

/* --- 7. the access link follows its own setting ------------------------- */

update_option(Migrator::OPTION_SETTINGS, array_merge($settings, ['magic_link' => false]));
$drain();
do_action('withdraw/link_issued', $order, 'https://example.test/withdraw/?wd_key=abc', 60);
$ok($drain() === [], 'with guest access off, no link email');

update_option(Migrator::OPTION_SETTINGS, array_merge($settings, ['magic_link' => true]));
$drain();
do_action('withdraw/link_issued', $order, 'https://example.test/withdraw/?wd_key=abc', 60);
$link = $drain();
$ok(count($link) === 1, 'with guest access on, one link email');
$linkBody = html_entity_decode(wp_strip_all_tags($link[0]['message'] ?? ''), ENT_QUOTES);
$ok(str_contains($linkBody, 'wd_key=abc'), 'the link email carries the link');

/* --- 8. the trader own clock, art. 13(1) -------------------------------- */

$repo->updateStatus($id, 'accepted');
$row = $repo->find($id);
$ok(ReturnPolicy::refundDeadline($row) === $submittedAt + (14 * DAY_IN_SECONDS), 'refund is due 14 days after the declaration');
$ok(ReturnPolicy::refundOverdue($row) === false, 'a fresh request is not overdue');

$wpdb->update($table, ['created_at' => gmdate('Y-m-d H:i:s', $submittedAt - (20 * DAY_IN_SECONDS))], ['id' => $id]);
$old = $repo->find($id);
$ok(ReturnPolicy::refundOverdue($old) === true, 'a 20-day-old open request is overdue');

$repo->updateStatus($id, 'processed');
$done = $repo->find($id);
$ok(ReturnPolicy::refundDeadline($done) === 0, 'a processed request owes nothing');

/* --- 9. the real POST, end to end --------------------------------------- */

// Everything above drives the actions directly. This drives the form, because
// the declaration only reaches the acknowledgement if handleSubmit() stores it.
$wpdb->query("TRUNCATE TABLE {$table}");
// Section 7 left guest access on, and with it on the form wants a one-time key
// rather than an order number, so the lookup branch would never be reached.
update_option(Migrator::OPTION_SETTINGS, array_merge($settings, ['magic_link' => false]));
$freshRequest();

$lineIds = [];
foreach ($order->get_items() as $itemId => $item) {
    $lineIds[] = (int) $itemId;
}

$_POST = [
    'withdraw_step'    => 'confirm',
    'withdraw_nonce'   => wp_create_nonce('withdraw_confirm'),
    'withdraw_order'   => (string) $order->get_id(),
    'withdraw_email'   => 'buyer@example.test',
    'withdraw_name'    => 'Anna Nowak',
    'withdraw_reason'  => 'Too bright',
    'withdraw_declare' => '1',
    'withdraw_qty'     => [(string) $lineIds[0] => '2'],
];

$drain();
do_shortcode('[withdraw_form]');
$posted = $drain();
$_POST  = [];

$ok(count($posted) === 2, 'a real form submit sent 2 messages, got ' . count($posted));

$storedRows = $repo->all(['limit' => 5]);
$ok(count($storedRows) === 1, 'the submit stored exactly one request, got ' . count($storedRows));

$row = $storedRows[0] ?? null;
$ok(is_object($row) && trim((string) $row->declaration) !== '', 'handleSubmit stored the declaration text');
$ok(is_object($row) && str_contains((string) $row->declaration, 'Anna Nowak'), 'the stored declaration names the consumer');

$postedAck = array_values(array_filter($posted, static fn (array $m): bool => $m['to'] === 'buyer@example.test'));
$ackText   = html_entity_decode(wp_strip_all_tags($postedAck[0]['message'] ?? ''), ENT_QUOTES);
$firstLine = is_object($row) ? trim(explode("\n", (string) $row->declaration)[0]) : 'x';
$ok($firstLine !== '' && str_contains($ackText, $firstLine), 'the acknowledgement carries what was stored');

/* --- 10. both hook branches list the email service ---------------------- */

$branches = substr_count((string) file_get_contents(WITHDRAW_DIR . 'config/hooks.php'), 'EmailService::class');
$ok($branches === 2, 'EmailService is registered in both hook branches, found ' . $branches);

$loaded = require WITHDRAW_DIR . 'config/hooks.php';
$ok(in_array(\Withdraw\Service\EmailService::class, (array) $loaded, true), 'the branch this request loaded includes it');

/* --- 11. no raw wp_mail left anywhere ------------------------------------ */

$raw = 0;
foreach (['src/Service/WithdrawalService.php', 'src/Admin/RequestsAdmin.php', 'src/Service/DigitalConsentService.php'] as $file) {
    $raw += substr_count((string) file_get_contents(WITHDRAW_DIR . $file), 'wp_mail(');
}
$ok($raw === 0, 'no raw wp_mail() left in the services (' . $raw . ' found)');

echo "\n" . ($fails === 0 ? "ALL GREEN\n" : $fails . " FAILED\n");
