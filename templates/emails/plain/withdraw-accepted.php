<?php
/**
 * Withdrawal accepted email (plain text). Mirror of the HTML twin: the same
 * art. 14(1) information, the return deadline counted from the declaration,
 * the return address and who bears the cost of sending the goods back.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
 * @var int            $request_id
 * @var string         $body_text
 * @var string         $admin_note
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

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

echo esc_html($body_text) . "\n\n";

if ($admin_note !== '') {
    echo esc_html($admin_note) . "\n\n";
}

echo esc_html(sprintf(
    /* translators: 1: withdrawal request id, 2: order number */
    __('Request #%1$d, order #%2$s.', 'plogins-withdraw'),
    $request_id,
    $order_number,
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

if ($withdraw_deadline !== '') {
    echo esc_html(sprintf(
        /* translators: 1: number of days, 2: the last date the goods may be sent back */
        __('Please send the goods back without undue delay and in any case within %1$d days of the day you told us you were withdrawing, that is by %2$s.', 'plogins-withdraw'),
        $return_days,
        $withdraw_deadline,
    )) . "\n\n";
} else {
    echo esc_html(sprintf(
        /* translators: %d: number of days */
        __('Please send the goods back without undue delay and in any case within %d days of the day you told us you were withdrawing.', 'plogins-withdraw'),
        $return_days,
    )) . "\n\n";
}

if ($return_address !== '') {
    echo esc_html__('Send them to:', 'plogins-withdraw') . "\n";
    echo esc_html($return_address) . "\n\n";
}

if ($return_cost_text !== '') {
    echo esc_html($return_cost_text) . "\n\n";
}

echo esc_html__('We will refund you using the same means of payment you used, unless we have expressly agreed otherwise.', 'plogins-withdraw') . "\n\n";

if ($additional_content !== '') {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer text.
