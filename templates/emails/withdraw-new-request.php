<?php
/**
 * New withdrawal request, shop notification (HTML).
 *
 * The admin log under WooCommerce, Withdrawal Requests holds every request
 * whether or not this message is switched on; this only carries the news.
 *
 * Override at yourtheme/woocommerce/emails/withdraw-new-request.php.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var int            $request_id
 * @var array<int, array{name:string, qty:int}> $items
 * @var string         $reason
 * @var string         $customer_email
 * @var string         $customer_name
 * @var int            $submitted_at
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
$withdraw_log_url   = admin_url('admin.php?page=' . \Withdraw\Admin\RequestsAdmin::PAGE);

do_action('woocommerce_email_header', $email_heading, $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email header.
?>

<p><?php echo esc_html__('A customer submitted a withdrawal declaration.', 'plogins-withdraw'); ?></p>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse;width:100%;margin:0 0 16px">
    <tbody>
        <tr>
            <th align="left" width="30%"><?php echo esc_html__('Request', 'plogins-withdraw'); ?></th>
            <td>#<?php echo esc_html((string) $request_id); ?></td>
        </tr>
        <tr>
            <th align="left"><?php echo esc_html__('Order', 'plogins-withdraw'); ?></th>
            <td>#<?php echo esc_html($order_number); ?></td>
        </tr>
        <?php if ($customer_name !== '') : ?>
            <tr>
                <th align="left"><?php echo esc_html__('Name on the contract', 'plogins-withdraw'); ?></th>
                <td><?php echo esc_html($customer_name); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th align="left"><?php echo esc_html__('Customer', 'plogins-withdraw'); ?></th>
            <td><?php echo esc_html($customer_email); ?></td>
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

<p>
    <?php
    echo esc_html__('You have 14 days from today to refund the customer, including the standard delivery cost. For goods you may hold the refund until they are back with you, or until the customer proves they sent them.', 'plogins-withdraw');
    ?>
</p>

<p><a href="<?php echo esc_url($withdraw_log_url); ?>"><?php echo esc_html__('Open the withdrawal request log', 'plogins-withdraw'); ?></a></p>

<?php
if ($additional_content !== '') {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer.
