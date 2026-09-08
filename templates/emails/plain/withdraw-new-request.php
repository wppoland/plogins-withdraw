<?php
/**
 * New withdrawal request, shop notification (plain text). Mirror of the HTML twin.
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

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

echo esc_html__('A customer submitted a withdrawal declaration.', 'plogins-withdraw') . "\n\n";

echo esc_html(sprintf(
    /* translators: 1: withdrawal request id, 2: order number */
    __('Request #%1$d, order #%2$s.', 'plogins-withdraw'),
    $request_id,
    $order_number,
)) . "\n";

if ($customer_name !== '') {
    echo esc_html(sprintf(
        /* translators: %s: the name the customer gave on the declaration */
        __('Name on the contract: %s', 'plogins-withdraw'),
        $customer_name,
    )) . "\n";
}

echo esc_html(sprintf(
    /* translators: %s: customer email address */
    __('Customer: %s', 'plogins-withdraw'),
    $customer_email,
)) . "\n";

if ($withdraw_submitted !== '') {
    echo esc_html(sprintf(
        /* translators: %s: submission date and time */
        __('Declared on %s.', 'plogins-withdraw'),
        $withdraw_submitted,
    )) . "\n";
}

echo "\n";

if ($items !== []) {
    echo esc_html__('Items withdrawn from', 'plogins-withdraw') . ":\n";
    foreach ($items as $withdraw_item) {
        echo '- ' . esc_html($withdraw_item['name']) . ' x' . esc_html((string) $withdraw_item['qty']) . "\n";
    }
    echo "\n";
}

echo esc_html(sprintf(
    /* translators: %s: the reason the customer gave, or a placeholder when none was given */
    __('Reason given: %s', 'plogins-withdraw'),
    $reason !== '' ? $reason : __('(none given)', 'plogins-withdraw'),
)) . "\n\n";

echo esc_html__('You have 14 days from today to refund the customer, including the standard delivery cost. For goods you may hold the refund until they are back with you, or until the customer proves they sent them.', 'plogins-withdraw') . "\n\n";

echo esc_url(admin_url('admin.php?page=' . \Withdraw\Admin\RequestsAdmin::PAGE)) . "\n\n";

if ($additional_content !== '') {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer text.
