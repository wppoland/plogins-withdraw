=== Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full or partial EU right-of-withdrawal requests (Directive 2023/2673) for WooCommerce orders, with an admin log and email notifications.

== Description ==

Plogins Withdraw adds an easy withdrawal function to WooCommerce, aligned with **EU Directive 2023/2673** (the "withdrawal button", Art. 11a): a clear way for customers to declare that they withdraw from a distance contract, in full or per item, within the statutory withdrawal period.

It is a **request-and-log** plugin: it records the customer's withdrawal declaration, emails a confirmation to the customer and a notification to the shop, and tracks the request status in the admin. It never moves money on its own, you process any refund in the normal WooCommerce order screen, matching the legal model where the customer declares and the trader acts.

= What it does =

* **Withdrawal form**: the `[withdraw_form]` shortcode renders a two-step form: look up an order by number and billing email (works for guests too), then select items and quantities and submit the withdrawal declaration.
* **Full or partial withdrawal**: the customer chooses how many of each item to withdraw from.
* **Withdrawal button in My Account**: a "Withdraw from this order" button appears under the order details and links to your withdrawal page with the order pre-filled.
* **Withdrawal-period check**: configurable period (statutory minimum 14 days), measured from delivery (order completion) or, if never completed, from the order date.
* **Admin log**: a WooCommerce → Withdrawal Requests screen lists every request with its items, customer and status (pending, accepted, rejected, processed), filterable by status.
* **Emails**: automatic confirmation to the customer and notification to the shop.
* **Model withdrawal text**: an editable block on the form for the statutory model withdrawal form (Annex I.B).
* **Guest friendly**: no account needed; the order-number + billing-email lookup works for guest orders.
* **HPOS + Blocks compatible**: reads orders through the WooCommerce order API.

= Requirements =

* WordPress 6.5 or later
* PHP 8.1 or later
* WooCommerce 8.0 or later

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Plogins Withdraw.
3. Create a page and add the `[withdraw_form]` shortcode.
4. Go to **WooCommerce → Withdrawal**, select that page as the withdrawal form page, set the withdrawal period and eligible order statuses, and adjust the notification email and legal texts.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
No. This records the withdrawal request and tracks its status. Process any refund in the normal WooCommerce order screen; the request status is managed on the Withdrawal Requests screen.

= Does it work for guest orders? =
Yes. Customers look up their order with the order number and the billing email used at checkout, so guests can submit a withdrawal without an account.

= Is this legal advice? =
No. The plugin provides the technical withdrawal function and editable legal texts. Configure the period and wording to match your jurisdiction and the statutory model withdrawal form.

= Is it compatible with HPOS? =
Yes. Orders are read through the WooCommerce order API, which is HPOS-compatible.

== Screenshots ==

1. The withdrawal request form: order lookup by number and billing email (guest-friendly), with the 14-day right-of-withdrawal notice.
2. Item selection: full or partial withdrawal with per-item quantities, the model withdrawal text and the declaration.
3. Settings (WooCommerce → Withdrawal): withdrawal period, form page, eligible statuses, notification email and legal texts, with the request log.

== Translations ==

Plogins Withdraw includes Polish, German and Spanish translations for the plugin interface. The text domain is `plogins-withdraw`, so WordPress.org language packs can also override or extend these bundled translations.

== Changelog ==

= 1.0.3 =
* Corrected the German and Polish translations: "withdrawal" was rendered as "Auszahlung" (payout) in German and "wypłata" (payout) in Polish; both now use the correct right-of-withdrawal terms (Widerruf / odstąpienie od umowy). Also fixed a German grammar slip and standardised the Polish wording.

= 1.0.2 =
* Added bundled Polish, German and Spanish translations for the plugin interface.

= 1.0.1 =
* First stable release.

= 0.1.1 =
* Plugin Check: escaping/sanitisation/i18n/hygiene fixes (table-name identifiers now passed via %i placeholders in prepared statements).

= 0.1.0 =
* Initial release: `[withdraw_form]` shortcode (order lookup + full/partial item selection), My Account withdrawal button, configurable withdrawal period and eligible statuses, admin request log with statuses, customer and shop emails, editable model withdrawal text. HPOS + Blocks compatible.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
