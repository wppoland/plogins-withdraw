<?php
/**
 * Step three: the declaration, shown back before it is made.
 *
 * Art. 11a(3) of Directive 2011/83/EU, as inserted by Directive 2023/2673,
 * requires a confirmation control that carries no wording other than "confirm
 * withdrawal" or an unambiguous equivalent. That only means anything if the
 * consumer can read the declaration on the same screen, which is what this is.
 *
 * @var \WC_Order $order
 * @var string    $email
 * @var string    $name
 * @var array     $items
 * @var string    $reason
 * @var string    $statement
 * @var string    $nonce
 *
 * @package Withdraw/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-form withdraw-form--review">
    <h3 class="withdraw-form__title">
        <?php echo esc_html__('Check your withdrawal declaration', 'plogins-withdraw'); ?>
    </h3>

    <p class="withdraw-form__lede">
        <?php echo esc_html__('This is the declaration that will be sent. Nothing has been submitted yet.', 'plogins-withdraw'); ?>
    </p>

    <blockquote class="withdraw-form__statement"><?php echo nl2br(esc_html($statement)); ?></blockquote>

    <?php if ($reason !== '') : ?>
        <p class="withdraw-form__reason">
            <strong><?php echo esc_html__('Reason given:', 'plogins-withdraw'); ?></strong>
            <?php echo esc_html($reason); ?>
        </p>
    <?php endif; ?>

    <form method="post" class="withdraw-form__form">
        <input type="hidden" name="withdraw_step" value="confirm">
        <input type="hidden" name="withdraw_order" value="<?php echo (int) $order->get_id(); ?>">
        <input type="hidden" name="withdraw_email" value="<?php echo esc_attr($email); ?>">
        <input type="hidden" name="withdraw_name" value="<?php echo esc_attr($name); ?>">
        <input type="hidden" name="withdraw_reason" value="<?php echo esc_attr($reason); ?>">
        <?php foreach ($items as $withdraw_item) : ?>
            <?php // Same field shape the items step posted, so the confirm step
                  // re-reads the selection through one code path and cannot
                  // disagree with what was just shown above. ?>
            <input type="hidden" name="withdraw_qty[<?php echo (int) $withdraw_item['item_id']; ?>]" value="<?php echo (int) $withdraw_item['qty']; ?>">
        <?php endforeach; ?>
        <?php wp_nonce_field('withdraw_confirm', 'withdraw_nonce'); ?>

        <p class="withdraw-form__declare">
            <label>
                <input type="checkbox" name="withdraw_declare" value="1" required>
                <?php echo esc_html__('I confirm that the declaration above is mine and that I am withdrawing from the contract.', 'plogins-withdraw'); ?>
            </label>
        </p>

        <p class="withdraw-form__actions">
            <?php // The label is prescribed by art. 11a(3): nothing but "confirm withdrawal". ?>
            <button type="submit" class="button withdraw-form__confirm"><?php echo esc_html__('Confirm withdrawal', 'plogins-withdraw'); ?></button>
        </p>
    </form>
</div>
