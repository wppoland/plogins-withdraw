<?php
/**
 * Withdrawal form - step 2: pick items + quantities, add a reason, confirm.
 *
 * @package Withdraw
 *
 * @var \WC_Order $order
 * @var string    $email
 * @var string    $key       one-time link token, '' when the flow was not entered with one
 * @var bool      $legacy    true only on the order-number + email flow
 * @var int       $deadline  unix timestamp the window closes
 * @var list<int> $excluded  order item ids the Art. 16(m) exclusion covers
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
        <input type="hidden" name="withdraw_step" value="review">
        <?php if ($key !== '') : ?>
            <?php // On a keyed flow the token IS the identity, so no client-editable
                  // order or address field is emitted at all. ?>
            <input type="hidden" name="withdraw_key" value="<?php echo esc_attr($key); ?>">
        <?php else : ?>
            <input type="hidden" name="withdraw_order" value="<?php echo (int) $order->get_id(); ?>">
            <?php if ($legacy) : ?>
                <input type="hidden" name="withdraw_email" value="<?php echo esc_attr($email); ?>">
            <?php endif; ?>
        <?php endif; ?>
        <?php wp_nonce_field('withdraw_review', 'withdraw_nonce'); ?>

        <p class="withdraw-form__name">
            <label for="withdraw-name"><?php echo esc_html__('Name on the contract', 'plogins-withdraw'); ?></label>
            <input type="text" id="withdraw-name" name="withdraw_name" value="<?php echo esc_attr($name ?? ''); ?>" required>
        </p>

        <table class="withdraw-form__items">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'plogins-withdraw'); ?></th>
                    <th><?php echo esc_html__('Ordered', 'plogins-withdraw'); ?></th>
                    <th><?php echo esc_html__('Withdraw quantity', 'plogins-withdraw'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $withdraw_excluded = isset($excluded) && is_array($excluded) ? $excluded : [];
            $withdraw_skipped  = 0;
            foreach ($order->get_items() as $withdraw_item_id => $withdraw_item) :
                if (! $withdraw_item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                if (in_array((int) $withdraw_item_id, $withdraw_excluded, true)) {
                    // Hidden here, refused on the server too: the row is only
                    // presentation, WithdrawalService::selectedItems() is the rule.
                    ++$withdraw_skipped;
                    continue;
                }
                $withdraw_max = (int) $withdraw_item->get_quantity(); ?>
                <tr>
                    <td><?php echo esc_html($withdraw_item->get_name()); ?></td>
                    <td><?php echo (int) $withdraw_max; ?></td>
                    <td>
                        <input type="number" min="0" max="<?php echo esc_attr((string) $withdraw_max); ?>" value="0"
                            name="withdraw_qty[<?php echo (int) $withdraw_item_id; ?>]"
                            aria-label="<?php echo esc_attr(sprintf(/* translators: %s: product */ __('Withdraw quantity for %s', 'plogins-withdraw'), $withdraw_item->get_name())); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($withdraw_skipped > 0) : ?>
            <p class="withdraw-form__excluded">
                <?php
                echo esc_html(_n(
                    'One item is not listed: it is digital content you asked us to supply immediately, and supply has begun, so the right of withdrawal no longer applies to it.',
                    'Some items are not listed: they are digital content you asked us to supply immediately, and supply has begun, so the right of withdrawal no longer applies to them.',
                    $withdraw_skipped,
                    'plogins-withdraw',
                ));
                ?>
            </p>
        <?php endif; ?>

        <p class="withdraw-form__field">
            <label for="withdraw_reason"><?php echo esc_html__('Reason (optional)', 'plogins-withdraw'); ?></label>
            <textarea id="withdraw_reason" name="withdraw_reason" rows="3"></textarea>
        </p>

        <?php if ($model !== '') : ?>
            <p class="withdraw-form__model"><?php echo nl2br(esc_html($model)); ?></p>
        <?php endif; ?>

        <p class="withdraw-form__actions">
            <button type="submit" class="button withdraw-form__submit"><?php echo esc_html__('Continue', 'plogins-withdraw'); ?></button>
        </p>
    </form>
</div>
