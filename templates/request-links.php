<?php
/**
 * Withdrawal form - the requests already made on an order, each linked to its
 * status page, shown when a one-time link or the signed-in owner opens the form.
 *
 * existing-checked: templates/form-items.php and templates/form-lookup.php - the list is shown above either one (the form, or the lookup with the reason the order cannot be withdrawn from again), so it lives once here instead of twice there.
 *
 * @package Withdraw
 *
 * @var list<array{id:int, status:string, url:string}> $links
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if ($links === []) {
    return;
}
?>
<div class="withdraw-form withdraw-form__requests">
    <h3 class="withdraw-form__heading"><?php echo esc_html__('Your withdrawal requests for this order', 'plogins-withdraw'); ?></h3>
    <ul class="withdraw-form__summary">
        <?php foreach ($links as $withdraw_link) : ?>
            <li>
                <a href="<?php echo esc_url($withdraw_link['url']); ?>">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: withdrawal request id, 2: status */
                        __('Request #%1$d: %2$s', 'plogins-withdraw'),
                        $withdraw_link['id'],
                        $withdraw_link['status'],
                    ));
                    ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="withdraw-form__legal"><?php echo esc_html__('Open a request to see where it stands, download the PDF, or cancel it while the shop has not decided yet.', 'plogins-withdraw'); ?></p>
</div>
