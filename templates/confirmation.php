<?php
/**
 * Withdrawal form - confirmation shown after a request is stored.
 *
 * @package Withdraw
 *
 * @var \WC_Order                                              $order
 * @var array<int, array{product_id:int, name:string, qty:int}> $items
 * @var int                                                    $id
 * @var string                                                 $hash
 * @var string                                                 $status_url
 * @var string                                                 $pdf_url
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-form withdraw-form--done">
    <h3 class="withdraw-form__heading"><?php echo esc_html__('Withdrawal request received', 'plogins-withdraw'); ?></h3>
    <p>
        <?php
        echo esc_html(sprintf(
            /* translators: 1: request id, 2: order number */
            __('Your withdrawal request #%1$d for order #%2$s has been recorded. We have emailed you a confirmation and will follow up with the next steps.', 'plogins-withdraw'),
            $id,
            $order->get_order_number(),
        ));
        ?>
    </p>
    <ul class="withdraw-form__summary">
        <?php foreach ($items as $withdraw_item) : ?>
            <li><?php echo esc_html((string) $withdraw_item['name']); ?> &times;<?php echo (int) $withdraw_item['qty']; ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if (! empty($status_url) || ! empty($pdf_url)) : ?>
        <p class="withdraw-form__links">
            <?php if (! empty($pdf_url)) : ?>
                <a class="button wp-element-button" href="<?php echo esc_url($pdf_url); ?>"><?php echo esc_html__('Download PDF', 'plogins-withdraw'); ?></a>
            <?php endif; ?>
            <?php if (! empty($status_url)) : ?>
                <a href="<?php echo esc_url($status_url); ?>"><?php echo esc_html__('Follow or cancel this withdrawal', 'plogins-withdraw'); ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if (! empty($hash)) : ?>
        <p class="withdraw-form__receipt-hash">
            <small><?php echo esc_html__('Receipt verification code:', 'plogins-withdraw'); ?> <code><?php echo esc_html($hash); ?></code></small>
        </p>
    <?php endif; ?>
</div>
