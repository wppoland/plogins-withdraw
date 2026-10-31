<?php

/**
 * Deleting the plugin on a network must clean every site, not only the one
 * the uninstall runs in: options, tables and scheduled events live per site,
 * and each site's tables carry that site's prefix. User meta is global and is
 * deleted once.
 *
 * Run: php tests/uninstall-multisite-check.php
 */

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'withdraw/plogins-withdraw.php');

$blog    = 1;
$deleted = [];
$px      = static fn (int $id): string => 1 === $id ? 'wp_' : "wp_{$id}_";

final class FakeWpdb
{
    public string $prefix  = 'wp_';
    public string $options = 'wp_options';
    public string $postmeta = 'wp_postmeta';
    public string $termmeta = 'wp_termmeta';

    public function query(string $sql): int
    {
        $GLOBALS['deleted'][] = $GLOBALS['blog'] . ':query:' . $sql;

        return 0;
    }

    public function get_var(string $sql): ?string
    {
        return null;
    }

    public function prepare(string $sql, ...$args): string
    {
        foreach ($args as $arg) {
            $sql = (string) preg_replace('/%[is]/', str_replace('\\', '\\\\', (string) $arg), $sql, 1);
        }

        return $sql;
    }

    public function esc_like(string $text): string
    {
        return $text;
    }
}

$wpdb = new FakeWpdb();

function is_multisite(): bool { return true; }
function get_sites(array $args): array { return [1, 2, 3]; }
function switch_to_blog(int $id): void
{
    $GLOBALS['blog'] = $id;
    $GLOBALS['wpdb']->prefix   = $GLOBALS['px']($id);
    $GLOBALS['wpdb']->options  = $GLOBALS['wpdb']->prefix . 'options';
    $GLOBALS['wpdb']->postmeta = $GLOBALS['wpdb']->prefix . 'postmeta';
    $GLOBALS['wpdb']->termmeta = $GLOBALS['wpdb']->prefix . 'termmeta';
}
function restore_current_blog(): void { switch_to_blog(1); }
function delete_option(string $name): void { $GLOBALS['deleted'][] = $GLOBALS['blog'] . ':' . $name; }
function delete_metadata(string $type, int $id, string $key, string $value = '', bool $all = false): void { $GLOBALS['deleted'][] = 'user:' . $key; }
function wp_clear_scheduled_hook(string $hook): int { $GLOBALS['deleted'][] = $GLOBALS['blog'] . ':cron:' . $hook; return 0; }

require __DIR__ . '/../uninstall.php';

$expected = [];
foreach ([1, 2, 3] as $site) {
    array_push($expected, "{$site}:withdraw_settings", "{$site}:withdraw_schema_version", "{$site}:withdraw_schema_rev", "{$site}:withdraw_texts_swept", "{$site}:withdraw_setup_page", "{$site}:woocommerce_withdraw_acknowledgement_settings", "{$site}:woocommerce_withdraw_new_request_settings", "{$site}:woocommerce_withdraw_accepted_settings", "{$site}:woocommerce_withdraw_rejected_settings", "{$site}:woocommerce_withdraw_processed_settings", "{$site}:woocommerce_withdraw_under_review_settings", "{$site}:woocommerce_withdraw_access_link_settings", "{$site}:query:DELETE FROM {$px($site)}postmeta WHERE meta_key = _withdraw_exclusion", "{$site}:query:DELETE FROM {$px($site)}termmeta WHERE meta_key = _withdraw_exclusion", "{$site}:query:DROP TABLE IF EXISTS {$px($site)}withdraw_requests", "{$site}:query:DELETE FROM {$px($site)}options WHERE option_name LIKE _transient_wd_link_% OR option_name LIKE _transient_timeout_wd_link_%", "{$site}:query:DELETE FROM {$px($site)}options WHERE option_name LIKE _transient_wd_rl_% OR option_name LIKE _transient_timeout_wd_rl_%");
}
sort($expected);
sort($deleted);

if ($deleted !== $expected) {
    echo "FAIL: uninstall did not clean every site the same way.\n  missing: " . implode(' | ', array_diff($expected, $deleted))
        . "\n  extra:   " . implode(' | ', array_diff($deleted, $expected)) . "\n";
    exit(1);
}

echo "OK: uninstall cleans every site of the network\n";
