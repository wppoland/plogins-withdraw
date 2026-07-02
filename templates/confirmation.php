<?php
/**
 * Withdrawal form - confirmation shown after a request is stored.
 *
 * @package Withdraw
 *
 * @var \WC_Order                                              $order
 * @var array<int, array{product_id:int, name:string, qty:int}> $items
 * @var int                                                    $id
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
        <?php foreach ($items as $it) : ?>
            <li><?php echo esc_html((string) $it['name']); ?> &times;<?php echo (int) $it['qty']; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
