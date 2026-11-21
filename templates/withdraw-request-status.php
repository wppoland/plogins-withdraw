<?php
/**
 * Withdrawal form page, one request's status: what was declared, the steps,
 * a PDF copy and, while the shop has not decided, a two-step cancel.
 *
 * Override by copying to yourtheme/woocommerce/withdraw-request-status.php.
 *
 * existing-checked: templates/confirmation.php - that is the one-off screen after a declaration; this view is reached again later, carries the steps and the cancel form, and is theme-overridable.
 *
 * @package Withdraw
 *
 * @var object                                                  $withdraw_request
 * @var \WC_Order|null                                          $withdraw_order
 * @var array<int, mixed>                                       $withdraw_items
 * @var list<array{label:string, state:string, date:string}>    $withdraw_steps
 * @var string                                                  $withdraw_key
 * @var string                                                  $withdraw_notice
 * @var bool                                                    $withdraw_confirming
 * @var bool                                                    $withdraw_can_cancel
 * @var string                                                  $withdraw_pdf_url
 */

declare(strict_types=1);

use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

$withdraw_id = (int) $withdraw_request->id;
?>
<div class="withdraw-form withdraw-status">
    <h3 class="withdraw-form__heading">
        <?php
        echo esc_html(sprintf(
            /* translators: %d: withdrawal request id */
            __('Withdrawal request #%d', 'plogins-withdraw'),
            $withdraw_id,
        ));
        ?>
    </h3>

    <?php if ($withdraw_notice !== '') : ?>
        <p class="withdraw-form__sent" role="status"><?php echo esc_html($withdraw_notice); ?></p>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html(sprintf(
            /* translators: 1: order number, 2: status */
            __('Order #%1$s. Status: %2$s.', 'plogins-withdraw'),
            $withdraw_order instanceof \WC_Order ? $withdraw_order->get_order_number() : (string) $withdraw_request->order_hint,
            RequestRepository::statusLabel((string) $withdraw_request->status),
        ));
        ?>
    </p>

    <ol class="withdraw-status__steps">
        <?php foreach ($withdraw_steps as $withdraw_step) : ?>
            <li class="withdraw-status__step is-<?php echo esc_attr($withdraw_step['state']); ?>"<?php echo $withdraw_step['state'] === 'current' ? ' aria-current="step"' : ''; ?>>
                <span class="withdraw-status__label"><?php echo esc_html($withdraw_step['label']); ?></span>
                <?php if ($withdraw_step['date'] !== '') : ?>
                    <span class="withdraw-status__date"><?php echo esc_html($withdraw_step['date']); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ($withdraw_items !== []) : ?>
        <h4><?php echo esc_html__('What you declared', 'plogins-withdraw'); ?></h4>
        <ul class="withdraw-form__summary">
            <?php foreach ($withdraw_items as $withdraw_item) : ?>
                <?php if (is_array($withdraw_item)) : ?>
                    <li><?php echo esc_html((string) ($withdraw_item['name'] ?? '')); ?> &times; <?php echo (int) ($withdraw_item['qty'] ?? 0); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (trim((string) $withdraw_request->admin_note) !== '' && $withdraw_request->status !== 'pending') : ?>
        <p class="withdraw-form__legal"><?php echo esc_html((string) $withdraw_request->admin_note); ?></p>
    <?php endif; ?>

    <?php if ($withdraw_pdf_url !== '') : ?>
        <p><a class="button wp-element-button withdraw-status__pdf" href="<?php echo esc_url($withdraw_pdf_url); ?>"><?php echo esc_html__('Download PDF', 'plogins-withdraw'); ?></a></p>
    <?php endif; ?>

    <?php if ($withdraw_can_cancel) : ?>
        <form method="post" class="withdraw-status__cancel">
            <input type="hidden" name="withdraw_request" value="<?php echo (int) $withdraw_id; ?>">
            <?php if ($withdraw_key !== '') : ?>
                <input type="hidden" name="withdraw_rkey" value="<?php echo esc_attr($withdraw_key); ?>">
            <?php endif; ?>
            <?php if ($withdraw_confirming) : ?>
                <p><?php echo esc_html__('Cancel this withdrawal request? The shop is told that you no longer want to withdraw from these items. While the withdrawal period runs you can declare a new withdrawal on this page.', 'plogins-withdraw'); ?></p>
                <input type="hidden" name="withdraw_step" value="cancel_confirm">
                <input type="hidden" name="withdraw_nonce" value="<?php echo esc_attr(wp_create_nonce('withdraw_cancel_confirm_' . $withdraw_id)); ?>">
                <button type="submit" class="button wp-element-button"><?php echo esc_html__('Yes, cancel my withdrawal', 'plogins-withdraw'); ?></button>
            <?php else : ?>
                <input type="hidden" name="withdraw_step" value="cancel">
                <input type="hidden" name="withdraw_nonce" value="<?php echo esc_attr(wp_create_nonce('withdraw_cancel_' . $withdraw_id)); ?>">
                <button type="submit" class="button wp-element-button"><?php echo esc_html__('Cancel this withdrawal', 'plogins-withdraw'); ?></button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
