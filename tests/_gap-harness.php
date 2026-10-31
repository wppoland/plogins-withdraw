<?php
/**
 * Shared setup for the 1.8.0 checks. Loaded with require from each check.
 *
 * @package Withdraw/Tests
 */

// No declare(strict_types=1): wp-cli eval-file wraps the caller in eval().

use Withdraw\Migrator;
use Withdraw\Plugin;
use Withdraw\Service\WithdrawalService;

$GLOBALS['wd_fails'] = 0;

function wd_ok(bool $cond, string $label): void
{
    echo ($cond ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (! $cond) {
        ++$GLOBALS['wd_fails'];
    }
}

function wd_done(): void
{
    $fails = $GLOBALS['wd_fails'];
    echo $fails === 0 ? "ALL GREEN\n" : "{$fails} FAILED\n";
    if ($fails > 0) {
        exit(1);
    }
}

function wd_settings(array $over): void
{
    $defaults = require WITHDRAW_DIR . 'config/defaults.php';
    update_option(Migrator::OPTION_SETTINGS, array_merge($defaults, $over));
}

/** Private methods of the form service, reached the way emails-check reaches WC_Emails. */
function wd_call(string $method, array $args)
{
    $svc = Plugin::instance()->container()->get(WithdrawalService::class);
    $ref = new ReflectionMethod($svc, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($svc, $args);
}

function wd_product(string $name, int $catId = 0): WC_Product_Simple
{
    $p = new WC_Product_Simple();
    $p->set_name($name);
    $p->set_regular_price('10');
    if ($catId > 0) {
        $p->set_category_ids([$catId]);
    }
    $p->save();

    return $p;
}

/** @param list<array{0:WC_Product,1:int}> $lines */
function wd_order(array $lines, int $completedAt): WC_Order
{
    $order = wc_create_order();
    foreach ($lines as [$product, $qty]) {
        $order->add_product($product, $qty);
    }
    $order->set_billing_email('gap@example.com');
    $order->calculate_totals();
    $order->set_status('completed');
    $order->set_date_completed($completedAt);
    $order->save();

    return $order;
}

/** @return array<string,int> product name => order item id */
function wd_item_ids(WC_Order $order): array
{
    $out = [];
    foreach ($order->get_items() as $id => $item) {
        $out[$item->get_name()] = (int) $id;
    }

    return $out;
}

Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();
