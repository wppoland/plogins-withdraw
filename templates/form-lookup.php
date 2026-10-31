<?php
/**
 * Withdrawal form - step 1: look up the order by number + billing email.
 *
 * @package Withdraw
 *
 * @var string $error
 * @var int    $period
 * @var string $intro
 * @var bool   $magic   true when the form emails a one-time link instead
 * @var bool   $offer_unmatched true after a lookup found no order and the shop takes unmatched declarations
 * @var string $nonce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pre-fill only, no state change.
$withdraw_order_id = isset($_GET['wd_order']) ? absint(wp_unslash($_GET['wd_order'])) : 0;
?>
<div class="withdraw-form">
    <?php if ($intro !== '') : ?>
        <p class="withdraw-form__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>
    <?php if ($magic) : ?>
        <p class="withdraw-form__lede"><?php echo esc_html__('Enter your order number and billing email. We will send a one-time link to that address.', 'plogins-withdraw'); ?></p>
    <?php endif; ?>
    <p class="withdraw-form__legal">
        <?php
        echo esc_html(sprintf(
            /* translators: %d: number of days */
            __('You have the right to withdraw from this contract within %d days without giving any reason.', 'plogins-withdraw'),
            $period,
        ));
        ?>
    </p>

    <?php if ($error !== '') : ?>
        <p class="withdraw-form__error" role="alert"><?php echo esc_html($error); ?></p>
    <?php endif; ?>

    <?php if (! empty($offer_unmatched)) : ?>
        <?php
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- echoes back what the lookup step just verified.
        $withdraw_typed_order = isset($_POST['withdraw_order']) ? sanitize_text_field(wp_unslash($_POST['withdraw_order'])) : '';
        $withdraw_typed_email = isset($_POST['withdraw_email']) ? sanitize_email(wp_unslash($_POST['withdraw_email'])) : '';
        // phpcs:enable
        ?>
        <form method="post" class="withdraw-form__form withdraw-form__unmatched">
            <input type="hidden" name="withdraw_step" value="unmatched">
            <input type="hidden" name="withdraw_order" value="<?php echo esc_attr($withdraw_typed_order); ?>">
            <input type="hidden" name="withdraw_email" value="<?php echo esc_attr($withdraw_typed_email); ?>">
            <?php wp_nonce_field('withdraw_lookup', 'withdraw_nonce'); ?>
            <p><?php echo esc_html__('Not sure of the order number or the email used? You can still send your withdrawal declaration and we will match it to your order.', 'plogins-withdraw'); ?></p>
            <p class="withdraw-form__actions">
                <button type="submit" class="button"><?php echo esc_html__('Send the declaration without a matching order', 'plogins-withdraw'); ?></button>
            </p>
        </form>
    <?php endif; ?>

    <form method="post" class="withdraw-form__form">
        <input type="hidden" name="withdraw_step" value="<?php echo esc_attr($magic ? 'link' : 'items'); ?>">
        <?php wp_nonce_field('withdraw_lookup', 'withdraw_nonce'); ?>
        <p class="withdraw-form__field">
            <label for="withdraw_order"><?php echo esc_html__('Order number', 'plogins-withdraw'); ?></label>
            <input type="text" inputmode="numeric" id="withdraw_order" name="withdraw_order" value="<?php echo $withdraw_order_id > 0 ? esc_attr((string) $withdraw_order_id) : ''; ?>" required>
        </p>
        <p class="withdraw-form__field">
            <label for="withdraw_email"><?php echo esc_html__('Billing email', 'plogins-withdraw'); ?></label>
            <input type="email" id="withdraw_email" name="withdraw_email" required>
        </p>
        <p class="withdraw-form__actions">
            <button type="submit" class="button withdraw-form__submit"><?php echo esc_html__('Continue', 'plogins-withdraw'); ?></button>
        </p>
    </form>
</div>
