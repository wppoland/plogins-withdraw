<?php

declare(strict_types=1);

/**
 * Plugin Name:       Plogins Withdraw - Right of Withdrawal Button for WooCommerce
 * Plugin URI:        https://plogins.com/plogins-withdraw/
 * Description:       EU right-of-withdrawal button and form (Directive 2023/2673, Art. 11a): let customers submit full or partial withdrawal requests for their orders, with an admin log and email notifications.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Author:            WPPoland
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plogins-withdraw
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.6
 */

namespace Withdraw;

defined('ABSPATH') || exit;

const VERSION = '0.1.0';

define('WITHDRAW_FILE', __FILE__);
define('WITHDRAW_DIR', plugin_dir_path(__FILE__));
define('WITHDRAW_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// Declare WooCommerce HPOS + Blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

register_activation_hook(__FILE__, static function (): void {
    require_once __DIR__ . '/autoload.php';
    // Seed the schema on activation; boot() also self-heals per-site on Multisite.
    Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists(\WooCommerce::class)) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Plogins Withdraw requires WooCommerce to be installed and active.', 'plogins-withdraw'),
            );
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    });
});
