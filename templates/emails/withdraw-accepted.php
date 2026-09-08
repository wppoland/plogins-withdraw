<?php
/**
 * Withdrawal accepted email (HTML).
 *
 * Carries the art. 14(1) information: the return deadline counted from the day
 * the consumer declared, where the goods go, and who bears the direct cost of
 * sending them back. The address and the cost sentence are resolved by
 * Withdraw\Service\ReturnPolicy before they reach this template, so an override
 * receives exactly what the settings screen showed the shop.
 *
 * Override at yourtheme/woocommerce/emails/withdraw-accepted.php.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var int            $request_id
 * @var string         $body_text
 * @var string         $declaration
 * @var int            $submitted_at
 * @var array<int, array{name:string, qty:int}> $items
 * @var int            $return_deadline
 * @var int            $return_days
 * @var string         $return_address
 * @var string         $return_cost_text
 * @var string         $email_heading
 * @var string         $additional_content
 * @var bool           $plain_text
 * @var \WC_Email|null $email
 *
 * @package Withdraw/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$withdraw_date_format = (string) get_option('date_format');
$withdraw_submitted   = $submitted_at > 0
    ? date_i18n($withdraw_date_format . ' ' . (string) get_option('time_format'), $submitted_at)
    : '';
$withdraw_deadline    = $return_deadline > 0 ? date_i18n($withdraw_date_format, $return_deadline) : '';

do_action('woocommerce_email_header', $email_heading, $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email header.
?>

<p><?php echo esc_html($body_text); ?></p>

<h2><?php echo esc_html__('Your declaration', 'plogins-withdraw'); ?></h2>
<table cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse;width:100%;margin:0 0 16px">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php echo esc_html__('Request', 'plogins-withdraw'); ?></th>
            <td>#<?php echo esc_html((string) $request_id); ?></td>
        </tr>
        <tr>
            <th align="left"><?php echo esc_html__('Order', 'plogins-withdraw'); ?></th>
            <td>#<?php echo esc_html($order_number); ?></td>
        </tr>
        <?php if ($withdraw_submitted !== '') : ?>
            <tr>
                <th align="left"><?php echo esc_html__('Declared on', 'plogins-withdraw'); ?></th>
                <td><?php echo esc_html($withdraw_submitted); ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($items !== []) : ?>
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
    <?php if ($withdraw_deadline !== '') : ?>
        <?php
        echo esc_html(sprintf(
            /* translators: 1: number of days, 2: the last date the goods may be sent back */
            __('Please send the goods back without undue delay and in any case within %1$d days of the day you told us you were withdrawing, that is by %2$s.', 'plogins-withdraw'),
            $return_days,
            $withdraw_deadline,
        ));
        ?>
    <?php else : ?>
        <?php
        echo esc_html(sprintf(
            /* translators: %d: number of days */
            __('Please send the goods back without undue delay and in any case within %d days of the day you told us you were withdrawing.', 'plogins-withdraw'),
            $return_days,
        ));
        ?>
    <?php endif; ?>
</p>

<?php if ($return_address !== '') : ?>
    <p><strong><?php echo esc_html__('Send them to:', 'plogins-withdraw'); ?></strong></p>
    <p style="white-space:pre-wrap"><?php echo esc_html($return_address); ?></p>
<?php endif; ?>

<?php if ($return_cost_text !== '') : ?>
    <p><?php echo esc_html($return_cost_text); ?></p>
<?php endif; ?>

<p><?php echo esc_html__('We will refund you using the same means of payment you used, unless we have expressly agreed otherwise.', 'plogins-withdraw'); ?></p>

<?php
if ($additional_content !== '') {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer.
