<?php
/**
 * Withdrawal status-change email (HTML).
 *
 * Shared by the rejected, processed and under-review messages, which differ
 * only in one sentence. The acceptance has a template of its own because it
 * carries the art. 14(1) return information.
 *
 * Overriding this file at yourtheme/woocommerce/emails/withdraw-status.php
 * therefore changes all three of those messages at once.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var int            $request_id
 * @var string         $body_text
 * @var string         $admin_note
 * @var string         $email_heading
 * @var string         $additional_content
 * @var bool           $plain_text
 * @var \WC_Email|null $email
 *
 * @package Withdraw/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email header.
?>

<p><?php echo esc_html($body_text); ?></p>

<?php if ($admin_note !== '') : ?>
    <p style="white-space:pre-wrap"><?php echo esc_html($admin_note); ?></p>
<?php endif; ?>

<p>
    <?php
    echo esc_html(sprintf(
        /* translators: 1: withdrawal request id, 2: order number */
        __('This concerns withdrawal request #%1$d for order #%2$s.', 'plogins-withdraw'),
        $request_id,
        $order_number,
    ));
    ?>
</p>

<?php
if ($additional_content !== '') {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer.
