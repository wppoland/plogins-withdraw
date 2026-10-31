<?php
/**
 * A withdrawal declaration that matched no order: edit, review, done.
 *
 * Review is its own step with a control labelled only "Confirm withdrawal",
 * as Art. 11a(3) requires, exactly like the matched flow.
 *
 * @package Withdraw
 *
 * @var string $mode        'edit' | 'review' | 'done'
 * @var array{number:string, email:string, name:string, goods:string, reason:string} $fields
 * @var string $error
 * @var string $statement
 * @var int    $submittedAt site-time timestamp, set on 'done'
 * @var string $receiptHash  SHA-256 receipt hash, set on 'done'
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-form withdraw-form--unmatched">
    <?php if ($mode === 'done') : ?>
        <h3 class="withdraw-form__heading"><?php echo esc_html__('Withdrawal declaration received', 'plogins-withdraw'); ?></h3>
        <p role="status">
            <?php
            echo esc_html(sprintf(
                /* translators: %s: date and time of submission */
                __('We received your declaration on %s. It did not match an order automatically, so we will match it by hand and reply to the address you gave. Keep a copy of this page.', 'plogins-withdraw'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $submittedAt),
            ));
            ?>
        </p>
        <blockquote class="withdraw-form__statement"><?php echo nl2br(esc_html($statement)); ?></blockquote>
        <?php if (! empty($receiptHash)) : ?>
            <p class="withdraw-form__receipt-hash">
                <small><?php echo esc_html__('Receipt verification code:', 'plogins-withdraw'); ?> <code><?php echo esc_html($receiptHash); ?></code></small>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <h3 class="withdraw-form__heading"><?php echo esc_html__('Withdrawal declaration', 'plogins-withdraw'); ?></h3>

        <?php if ($error !== '') : ?>
            <p class="withdraw-form__error" role="alert"><?php echo esc_html($error); ?></p>
        <?php endif; ?>

        <form method="post" class="withdraw-form__form">
            <?php if ($mode === 'review') : ?>
                <p class="withdraw-form__lede"><?php echo esc_html__('This is the declaration that will be sent. Nothing has been submitted yet.', 'plogins-withdraw'); ?></p>
                <blockquote class="withdraw-form__statement"><?php echo nl2br(esc_html($statement)); ?></blockquote>
                <input type="hidden" name="withdraw_step" value="unmatched_confirm">
                <?php foreach (['order' => 'number', 'email' => 'email', 'name' => 'name', 'goods' => 'goods', 'reason' => 'reason'] as $withdraw_post => $withdraw_key) : ?>
                    <input type="hidden" name="withdraw_<?php echo esc_attr($withdraw_post); ?>" value="<?php echo esc_attr($fields[$withdraw_key]); ?>">
                <?php endforeach; ?>
                <?php wp_nonce_field('withdraw_unmatched_confirm', 'withdraw_nonce'); ?>
                <p class="withdraw-form__declare">
                    <label>
                        <input type="checkbox" name="withdraw_declare" value="1" required>
                        <?php echo esc_html__('I confirm that the declaration above is mine and that I am withdrawing from the contract.', 'plogins-withdraw'); ?>
                    </label>
                </p>
                <p class="withdraw-form__actions">
                    <button type="submit" class="button withdraw-form__confirm"><?php echo esc_html__('Confirm withdrawal', 'plogins-withdraw'); ?></button>
                </p>
            <?php else : ?>
                <p class="withdraw-form__lede"><?php echo esc_html__('We could not match these details to an order. Tell us what you are withdrawing from and we will match it by hand.', 'plogins-withdraw'); ?></p>
                <input type="hidden" name="withdraw_step" value="unmatched_review">
                <?php wp_nonce_field('withdraw_unmatched', 'withdraw_nonce'); ?>
                <p class="withdraw-form__field">
                    <label for="withdraw-u-name"><?php echo esc_html__('Name on the contract', 'plogins-withdraw'); ?></label>
                    <input type="text" id="withdraw-u-name" name="withdraw_name" value="<?php echo esc_attr($fields['name']); ?>" required>
                </p>
                <p class="withdraw-form__field">
                    <label for="withdraw-u-order"><?php echo esc_html__('Order number', 'plogins-withdraw'); ?></label>
                    <input type="text" id="withdraw-u-order" name="withdraw_order" value="<?php echo esc_attr($fields['number']); ?>" required>
                </p>
                <p class="withdraw-form__field">
                    <label for="withdraw-u-email"><?php echo esc_html__('Email for our reply', 'plogins-withdraw'); ?></label>
                    <input type="email" id="withdraw-u-email" name="withdraw_email" value="<?php echo esc_attr($fields['email']); ?>" required>
                </p>
                <p class="withdraw-form__field">
                    <label for="withdraw-u-goods"><?php echo esc_html__('Goods you are withdrawing from', 'plogins-withdraw'); ?></label>
                    <textarea id="withdraw-u-goods" name="withdraw_goods" rows="3" required><?php echo esc_textarea($fields['goods']); ?></textarea>
                </p>
                <p class="withdraw-form__field">
                    <label for="withdraw-u-reason"><?php echo esc_html__('Reason (optional)', 'plogins-withdraw'); ?></label>
                    <textarea id="withdraw-u-reason" name="withdraw_reason" rows="2"><?php echo esc_textarea($fields['reason']); ?></textarea>
                </p>
                <p class="withdraw-form__actions">
                    <button type="submit" class="button withdraw-form__submit"><?php echo esc_html__('Continue', 'plogins-withdraw'); ?></button>
                </p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
