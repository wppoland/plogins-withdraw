<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * Adds the required "easy withdrawal" entry point to the customer's order view:
 * a button under the order details linking to the withdrawal form page with the
 * order pre-filled. This is the visible "withdrawal button" the directive asks
 * for (Art. 11a) on top of the standalone [withdraw_form] page.
 */
final class MyAccount implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderButton']);
    }

    public function renderButton(\WC_Order $order): void
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);
        $s        = array_merge($defaults, is_array($stored) ? $stored : []);

        $pageId = (int) $s['form_page_id'];
        if ($pageId < 1) {
            return; // No withdrawal page configured yet.
        }
        if (! in_array($order->get_status(), (array) $s['eligible_statuses'], true)) {
            return;
        }

        $url = add_query_arg(
            ['wd_order' => $order->get_id()],
            get_permalink($pageId) ?: home_url('/'),
        );
        ?>
        <p class="withdraw-order-action">
            <a class="button withdraw-order-action__btn" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html__('Withdraw from this order', 'plogins-withdraw'); ?>
            </a>
        </p>
        <?php
    }
}
