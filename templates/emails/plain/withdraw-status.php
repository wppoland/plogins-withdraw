<?php
/**
 * Withdrawal status-change email (plain text). Mirror of the HTML twin, and
 * likewise shared by the rejected, processed and under-review messages.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var int            $request_id
 * @var string         $body_text
 * @var string         $email_heading
 * @var string         $additional_content
 * @var bool           $plain_text
 * @var \WC_Email|null $email
 *
 * @package Withdraw/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

echo esc_html($body_text) . "\n\n";

echo esc_html(sprintf(
    /* translators: 1: withdrawal request id, 2: order number */
    __('This concerns withdrawal request #%1$d for order #%2$s.', 'plogins-withdraw'),
    $request_id,
    $order_number,
)) . "\n\n";

if ($additional_content !== '') {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer text.
