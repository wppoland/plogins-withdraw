<?php
/**
 * One-time withdrawal link email (HTML).
 *
 * The body is composed by the email class and passed through the
 * `withdraw/magic_link_email` filter before it reaches this template, so a shop
 * that already rewrote that text keeps its wording.
 *
 * Override at yourtheme/woocommerce/emails/withdraw-access-link.php.
 *
 * @var \WC_Order|null $order
 * @var object|null    $request
 * @var string         $order_number
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

do_action('woocommerce_email_header', $email_heading, $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email header.

// Escaped first, then linked: make_clickable() only ever sees text that can no
// longer carry markup, and the link it builds is the only HTML added.
echo wp_kses_post(wpautop(make_clickable(esc_html($body_text))));

if ($additional_content !== '') {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email footer.
