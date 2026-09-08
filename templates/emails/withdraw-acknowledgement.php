<?php
/**
 * Withdrawal acknowledgement email (HTML).
 *
 * Art. 11a(4): the consumer's proof that the declaration reached the shop. It
 * repeats the declaration back in the words that were on screen when it was
 * made (read from the stored row, never rebuilt) and states the date and time it
 * was submitted.
 *
 * Override at yourtheme/woocommerce/emails/withdraw-acknowledgement.php.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var string         $declaration
 * @var int            $submitted_at
 * @var array<int, array{name:string, qty:int}> $items
 * @var string         $reason
 * @var string         $email_heading
 * @var string         $additional_content
 * @var bool           $plain_text
 * @var \WC_Email|null $email
 *
 * @package Withdraw/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$withdraw_submitted = $submitted_at > 0
    ? date_i18n((string) get_option('date_format') . ' ' . (string) get_option('time_format'), $submitted_at)
    : '';

do_action('woocommerce_email_header', $email_heading, $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email header.
?>

<p>
    <?php
    echo esc_html(sprintf(
        /* translators: %s: order number */
        __('We received your withdrawal declaration for order #%s.', 'plogins-withdraw'),
        $order_number,
    ));
    ?>
</p>

<?php if ($withdraw_submitted !== '') : ?>
    <p>
        <?php
        echo esc_html(sprintf(
            /* translators: %s: submission date and time */
            __('Submitted on %s.', 'plogins-withdraw'),
            $withdraw_submitted,
        ));
        ?>
    </p>
<?php endif; ?>

<?php if ($declaration !== '') : ?>
    <h2><?php echo esc_html__('Your declaration', 'plogins-withdraw'); ?></h2>
    <div style="white-space:pre-wrap;border-left:3px solid #ddd;padding:8px 12px;margin:0 0 16px"><?php echo esc_html($declaration); ?></div>
<?php endif; ?>

<?php if ($items !== []) : ?>
    <h2><?php echo esc_html__('Items withdrawn from', 'plogins-withdraw'); ?></h2>
    <table cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse;width:100%;margin:0 0 16px">
        <tbody>
        <?php foreach ($items as $withdraw_item) : ?>
            <tr>
                <td><?php echo esc_html($withdraw_item['name']); ?></td>
                <td width="15%">&times;<?php echo esc_html((string) $withdraw_item['qty']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p>
    <?php
    echo esc_html(sprintf(
        /* translators: %s: the reason the customer gave, or a placeholder when none was given */
        __('Reason given: %s', 'plogins-withdraw'),
        $reason !== '' ? $reason : __('(none given)', 'plogins-withdraw'),
    ));
    ?>
</p>

<?php
if ($additional_content !== '') {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer.
