<?php
/**
 * Withdrawal acknowledgement email (plain text).
 *
 * Mirror of the HTML twin: the same art. 11a(4) record, the declaration as it
 * was made and the date and time it was submitted.
 *
 * Override at yourtheme/woocommerce/emails/plain/withdraw-acknowledgement.php.
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

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

echo esc_html(sprintf(
    /* translators: %s: order number */
    __('We received your withdrawal declaration for order #%s.', 'plogins-withdraw'),
    $order_number,
)) . "\n\n";

if ($withdraw_submitted !== '') {
    echo esc_html(sprintf(
        /* translators: %s: submission date and time */
        __('Submitted on %s.', 'plogins-withdraw'),
        $withdraw_submitted,
    )) . "\n\n";
}

if ($declaration !== '') {
    echo esc_html__('Your declaration', 'plogins-withdraw') . ":\n";
    echo esc_html($declaration) . "\n\n";
}

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

if ($additional_content !== '') {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer text.
