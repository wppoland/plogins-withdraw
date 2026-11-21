<?php
/**
 * My Account, Withdrawals tab: the signed-in customer's withdrawal requests.
 *
 * Override by copying to yourtheme/woocommerce/myaccount/withdraw-requests.php,
 * the same way the plugin's email templates are overridden.
 *
 * existing-checked: templates/form-*.php - those render steps of the form, not the account list, and load through a plain require that a theme cannot override.
 *
 * @package Withdraw
 *
 * @var list<object>                                              $withdraw_rows
 * @var array<int, array{order:\WC_Order|null, new:string}>      $withdraw_orders
 * @var string                                                    $withdraw_form_url
 * @var string                                                    $withdraw_label
 * @var int                                                       $withdraw_page
 * @var bool                                                      $withdraw_more
 */

declare(strict_types=1);

use Withdraw\Frontend\MyAccount;
use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

if ($withdraw_rows === [] && $withdraw_page === 1) : ?>
    <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
        <?php echo esc_html__('You have not withdrawn from any order yet.', 'plogins-withdraw'); ?>
        <?php if ($withdraw_form_url !== '') : ?>
            <a class="woocommerce-Button wc-forward button" href="<?php echo esc_url($withdraw_form_url); ?>"><?php echo esc_html($withdraw_label); ?></a>
        <?php endif; ?>
    </div>
    <?php
    return;
endif;
?>
<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table withdraw-requests-table">
    <thead>
        <tr>
            <th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html__('Date', 'plogins-withdraw'); ?></span></th>
            <th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html__('Order', 'plogins-withdraw'); ?></span></th>
            <th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html__('Items', 'plogins-withdraw'); ?></span></th>
            <th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html__('Status', 'plogins-withdraw'); ?></span></th>
            <th scope="col" class="woocommerce-orders-table__header"><span class="screen-reader-text"><?php echo esc_html__('Actions', 'plogins-withdraw'); ?></span></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($withdraw_rows as $withdraw_row) : ?>
            <?php
            $withdraw_entry = $withdraw_orders[(int) $withdraw_row->order_id] ?? ['order' => null, 'new' => ''];
            $withdraw_order = $withdraw_entry['order'];
            $withdraw_items = json_decode((string) $withdraw_row->items, true);
            ?>
            <tr class="woocommerce-orders-table__row">
                <td class="woocommerce-orders-table__cell" data-title="<?php echo esc_attr__('Date', 'plogins-withdraw'); ?>">
                    <time datetime="<?php echo esc_attr(mysql2date('c', (string) $withdraw_row->created_at, false)); ?>"><?php echo esc_html(mysql2date(wc_date_format(), (string) $withdraw_row->created_at)); ?></time>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php echo esc_attr__('Order', 'plogins-withdraw'); ?>">
                    <?php if ($withdraw_order instanceof \WC_Order) : ?>
                        <a href="<?php echo esc_url($withdraw_order->get_view_order_url()); ?>"><?php echo esc_html('#' . $withdraw_order->get_order_number()); ?></a>
                    <?php else : ?>
                        <?php echo esc_html('#' . (int) $withdraw_row->order_id); ?>
                    <?php endif; ?>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php echo esc_attr__('Items', 'plogins-withdraw'); ?>">
                    <?php
                    $withdraw_lines = [];
                    foreach (is_array($withdraw_items) ? $withdraw_items : [] as $withdraw_item) {
                        if (is_array($withdraw_item)) {
                            $withdraw_lines[] = (string) ($withdraw_item['name'] ?? '') . " \u{00D7} " . (int) ($withdraw_item['qty'] ?? 0);
                        }
                    }
                    echo esc_html(implode(', ', $withdraw_lines));
                    ?>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php echo esc_attr__('Status', 'plogins-withdraw'); ?>">
                    <?php echo esc_html(RequestRepository::statusLabel((string) $withdraw_row->status)); ?>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
                    <?php if ($withdraw_entry['new'] !== '') : ?>
                        <a class="woocommerce-button button" href="<?php echo esc_url($withdraw_entry['new']); ?>"><?php echo esc_html($withdraw_label); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($withdraw_page > 1 || $withdraw_more) : ?>
    <div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
        <?php if ($withdraw_page > 1) : ?>
            <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url(wc_get_endpoint_url(MyAccount::ENDPOINT, (string) ($withdraw_page - 1))); ?>"><?php echo esc_html__('Previous', 'plogins-withdraw'); ?></a>
        <?php endif; ?>
        <?php if ($withdraw_more) : ?>
            <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url(wc_get_endpoint_url(MyAccount::ENDPOINT, (string) ($withdraw_page + 1))); ?>"><?php echo esc_html__('Next', 'plogins-withdraw'); ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
