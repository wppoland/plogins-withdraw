<?php
/**
 * Withdrawal form - step 2: pick items + quantities, add a reason, confirm.
 *
 * @package Withdraw
 *
 * @var \WC_Order $order
 * @var string    $email
 * @var int       $deadline  unix timestamp the window closes
 * @var string    $model     model withdrawal text
 * @var string    $nonce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-form withdraw-form--items">
    <h3 class="withdraw-form__heading">
        <?php
        echo esc_html(sprintf(
            /* translators: %s: order number */
            __('Withdraw from order #%s', 'plogins-withdraw'),
            $order->get_order_number(),
        ));
        ?>
    </h3>
    <p class="withdraw-form__deadline">
        <?php
        echo esc_html(sprintf(
            /* translators: %s: date */
            __('Withdrawal is possible until %s.', 'plogins-withdraw'),
            date_i18n(get_option('date_format'), $deadline),
        ));
        ?>
    </p>

    <form method="post" class="withdraw-form__form">
        <input type="hidden" name="withdraw_step" value="confirm">
        <input type="hidden" name="withdraw_order" value="<?php echo (int) $order->get_id(); ?>">
        <input type="hidden" name="withdraw_email" value="<?php echo esc_attr($email); ?>">
        <?php wp_nonce_field('withdraw_confirm', 'withdraw_nonce'); ?>

        <table class="withdraw-form__items">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'plogins-withdraw'); ?></th>
                    <th><?php echo esc_html__('Ordered', 'plogins-withdraw'); ?></th>
                    <th><?php echo esc_html__('Withdraw quantity', 'plogins-withdraw'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($order->get_items() as $itemId => $item) :
                if (! $item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $max = (int) $item->get_quantity(); ?>
                <tr>
                    <td><?php echo esc_html($item->get_name()); ?></td>
                    <td><?php echo (int) $max; ?></td>
                    <td>
                        <input type="number" min="0" max="<?php echo esc_attr((string) $max); ?>" value="0"
                            name="withdraw_qty[<?php echo (int) $itemId; ?>]"
                            aria-label="<?php echo esc_attr(sprintf(/* translators: %s: product */ __('Withdraw quantity for %s', 'plogins-withdraw'), $item->get_name())); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="withdraw-form__field">
            <label for="withdraw_reason"><?php echo esc_html__('Reason (optional)', 'plogins-withdraw'); ?></label>
            <textarea id="withdraw_reason" name="withdraw_reason" rows="3"></textarea>
        </p>

        <?php if ($model !== '') : ?>
            <p class="withdraw-form__model"><?php echo nl2br(esc_html($model)); ?></p>
        <?php endif; ?>

        <p class="withdraw-form__consent">
            <label>
                <input type="checkbox" name="withdraw_declare" value="1" required>
                <?php echo esc_html__('I hereby give notice that I withdraw from my contract for the selected item(s).', 'plogins-withdraw'); ?>
            </label>
        </p>

        <p class="withdraw-form__actions">
            <button type="submit" class="button withdraw-form__submit"><?php echo esc_html__('Submit withdrawal', 'plogins-withdraw'); ?></button>
        </p>
    </form>
</div>
