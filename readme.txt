=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full or partial EU right-of-withdrawal requests (Directive 2023/2673) for WooCommerce orders, with an admin log and WooCommerce emails.

== Description ==

Plogins Withdraw adds an easy withdrawal function to WooCommerce, aligned with **EU Directive 2023/2673** (the "withdrawal button", Art. 11a): a clear way for customers to declare that they withdraw from a distance contract, in full or per item, within the statutory withdrawal period.

It is a **request-and-log** plugin: it records the customer's withdrawal declaration, emails a confirmation to the customer and a notification to the shop, and tracks the request status in the admin. It never moves money on its own, you process any refund in the normal WooCommerce order screen, matching the legal model where the customer declares and the trader acts.

= What it does =

* **Withdrawal form**: the `[withdraw_form]` shortcode renders the whole flow: look up an order by number and billing email (works for guests too), select items and quantities, then confirm on a step of its own, because Art. 11a(3) wants the confirmation to be a separate control labelled only "confirm withdrawal".
* **Full or partial withdrawal**: the customer chooses how many of each item to withdraw from.
* **Withdrawal button in My Account**: a "Withdraw from this order" button appears under the order details and links to your withdrawal page with the order pre-filled.
* **Withdrawal-period check**: configurable period (statutory minimum 14 days), measured from delivery (order completion) or, if never completed, from the order date.
* **Admin log**: a WooCommerce → Withdrawal Requests screen lists every request with its items, customer and status (pending, accepted, rejected, processed), filterable by status.
* **WooCommerce emails**: seven of them, each with its own entry under WooCommerce → Settings → Emails, using your store template, logo and footer. The declaration acknowledgement, the shop notification, one per request status, and the guest access link. Any of them can be reworded, restyled or switched off on its own.
* **The acknowledgement is a record**: it repeats the declaration back in the exact words the customer confirmed, stored at the time, with the date and time of submission, which is what Art. 11a(4) asks for.
* **Return information (Art. 14(1))**: the acceptance message states where to send the goods, taken from a return address field or your store address, the deadline counted from the day the customer declared, and who bears the direct cost of the return. That last one says nothing until you choose it, because you may only charge the customer if you told them before the sale.
* **Your own refund deadline (Art. 13(1))**: the request log shows the date you owe the refund by, 14 days from the declaration, and marks it once it has passed.
* **Digital content consent (Art. 16(m))**: optional. One unticked checkbox at checkout, on both the classic and the block checkout, asking the customer to start supply immediately and acknowledging that the right of withdrawal is lost once it has. What was agreed, when, in which exact words and for which products is stored with the order and confirmed back on the order screen and in the order email. Once WooCommerce has actually served a download, that item drops out of the withdrawal form, server side.
* **Emailed one-time link (optional)**: off by default. With it on, step one asks for the order number and billing email and emails a single-use link to the address on the order instead of opening the form, so knowing an order number is not enough to read what somebody bought. The answer is the same whether the order exists or not, requests are rate limited, and signed-in customers arriving from My Account skip the link entirely.
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
5. Still on that screen, fill in the return address and say who pays to send goods back, so the acceptance message can carry the Art. 14(1) information.
6. Optionally reword or restyle any of the messages under **WooCommerce → Settings → Emails**.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
No. This records the withdrawal request and tracks its status. Process any refund in the normal WooCommerce order screen; the request status is managed on the Withdrawal Requests screen.

= Does it work for guest orders? =
Yes. Customers look up their order with the order number and the billing email used at checkout, so guests can submit a withdrawal without an account.

= Should I turn the emailed one-time link on? =
Only if your outgoing email is reliable, which is why it ships off. With it off, anyone holding an order number and the billing address can open the form, which is the same pair WooCommerce itself accepts for guest order tracking. With it on, the form emails a single-use link valid for one hour instead, so the address has to actually be reachable by the person asking. That makes the withdrawal function depend on your shop being able to send mail: if SMTP breaks, a guest cannot withdraw at all.

= Is this legal advice? =
No. The plugin provides the technical withdrawal function and editable legal texts. Configure the period and wording to match your jurisdiction and the statutory model withdrawal form.

= What does the digital content consent do, and does it block anything? =
It is off until you turn it on, and even then it never blocks a purchase. The checkbox is optional and never pre-ticked, because consent that is a condition of buying is not freely given and would not hold up as an exclusion. A customer who leaves it unticked still receives the download and still keeps the right of withdrawal. The exclusion only applies to items the customer consented to AND that WooCommerce has recorded as downloaded.

= My files are delivered by email or by an external portal. Will the exclusion work? =
No, not on its own. The plugin decides that supply has begun by reading WooCommerce download logs, so a file WooCommerce never served reads as not downloaded and the item stays withdrawable. Erring towards the customer is deliberate. If you deliver outside WooCommerce, the `withdraw/digital_supply_begun` filter lets you supply the truth.

= Does the customer hear from me when I accept or reject a request? =
Yes. Each of the four request statuses has its own WooCommerce email, sent when you change the status in the request log. The acceptance one also carries the return address, the return deadline and who pays for the return. Switch any of them off under WooCommerce → Settings → Emails if you would rather write yourself.

= Who pays to send the goods back? =
Whoever you say, and the plugin says nothing until you choose. Article 14(1) only lets you put the direct cost of the return on the customer if you told them so before the contract, in your withdrawal information. If you did not, it is yours, so a plugin that assumed the customer pays would be making a claim on your behalf that might not hold.

= Is it compatible with HPOS? =
Yes. Orders are read through the WooCommerce order API, which is HPOS-compatible.

== Screenshots ==

1. The withdrawal request form: order lookup by number and billing email (guest-friendly), with the 14-day right-of-withdrawal notice.
2. Item selection: full or partial withdrawal with per-item quantities, the model withdrawal text and the declaration.
3. Settings (WooCommerce → Withdrawal): withdrawal period, form page, eligible statuses, notification email and legal texts, with the request log.

== Translations ==

Plogins Withdraw is fully translatable and ships the `plogins-withdraw.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

= 1.4.0 =
* New: every message the plugin sends is now a WooCommerce email. They use your store template, logo and footer, and each one has its own entry under WooCommerce, Settings, Emails where it can be reworded, restyled or switched off on its own. Seven of them: the declaration acknowledgement, the shop notification, one per request status, and the guest access link.
* New: the shop notification keeps its own recipient field. Left empty it uses the notification address already set under WooCommerce, Withdrawal, so nothing moves for a shop that never opens the emails screen.
* New: the acknowledgement stores the declaration as the customer confirmed it and repeats back those exact words, rather than rebuilding the sentence when the mail is sent. A later translation, a renamed product or a reworded template can no longer change what a past customer is told they declared.
* New: the acceptance message carries the Article 14(1) information. Where to send the goods back, taken from a new return address field or from your WooCommerce store address, the deadline counted from the day the customer declared rather than from the day you accepted, and who bears the direct cost of the return.
* New: a setting for who pays to send the goods back. It says nothing until you choose, because Article 14(1) only lets you charge the customer if you told them so before the sale, and a default that assumed otherwise would have the plugin make a claim on your behalf that may not hold.
* New: the request log shows your own Article 13(1) deadline, 14 days from the day the customer told you they were withdrawing, and marks it in red once it has passed. It counts from the declaration, not from your acceptance, which is the clock the law actually puts you on.
* Changed: the acceptance message no longer says the 14 days run from that message. They run from the declaration, so a shop that took a week to accept was quietly giving the customer a week too long.

= 1.3.0 =
* Fixed: saving a request's status twice sent the customer a second identical email, and a status outside the allowed list still mailed them the generic "being reviewed" message for a write the database had refused. Both introduced with the status emails in 1.0.8.
* New: Article 16(m) consent for digital content, off by default. When switched on, a cart holding a downloadable product gets one optional, unticked checkbox at checkout carrying both halves of the statement: the request to begin supply immediately and the acknowledgement that the right of withdrawal is lost once supply has begun. It renders on the classic checkout and on the block checkout, and the block version hides itself on carts with no downloadable item.
* New: the consent is recorded on the order with the moment it was given, the exact wording shown at the time, and the products that were downloadable when the order was placed, so editing the wording later cannot rewrite what an earlier customer agreed to. Nothing is written unless the order really holds a downloadable item, whatever the checkout posted.
* New: the consent is confirmed back to the customer on the order screen and in the order email. Article 16(m) only excludes the right if the trader also gave that confirmation, so it is part of the feature rather than an option.
* New: an order screen panel showing whether consent was given, declined or never offered, in which words, and whether each covered product has actually been downloaded, which is what decides whether the exclusion applies at all.
* New: once supply has begun for a consented item, that item is dropped from the withdrawal form and refused on the server, not merely hidden. If every item in the order is covered, the order is refused with its own reason. WooCommerce cannot see downloads it did not serve, so files delivered by email or an external portal always read as not begun and stay withdrawable; the `withdraw/digital_supply_begun` filter is there for those shops.

* New: an optional emailed one-time link for guest access, off by default. Turned on, the lookup step mails a single-use token to the billing address on the order rather than opening the form, answers identically whether the order exists or not, and rate limits requests per address and per IP. Only the hash of a token is stored, tokens expire after an hour, and one is spent when a declaration is actually submitted. Leave it off and the form behaves exactly as it did.
* New: on the keyed flow the form no longer emits an order or address field the customer could edit, so whoever holds a link cannot redirect the acknowledgement email somewhere else, and a link stops working if the shop changes the order's billing address after sending it.
* Fixed: the panel shown after requesting a link printed a nonce that nothing ever verified, on a button that did not send a second link. The nonce is gone and the button says what it does.
* Fixed: the rate limiter restarted its window on every attempt, so it never drained while attempts kept coming. Anyone who knew a customer's billing address could have kept that address over the cap indefinitely, silently, because the flow answers the same either way. The window is now fixed.

= 1.2.0 =
* Fixed: the form asked for the order number and then looked the order up by its database id. On a stock WooCommerce install those are the same value, so nothing looked wrong, but any plugin that renumbers orders breaks the pair: the shop printed a number in its own emails that its own withdrawal form then rejected. The lookup now resolves the displayed number, falling back to the id, with a `withdraw/resolve_order_number` filter for other numbering schemes.
* Fixed: the status email addressed the order by its database id rather than the number the customer sees, for the same reason.
* New: withdrawals are written into the order's own notes, both when the declaration arrives and when its status changes. The request log is a separate screen nobody has open; whoever opens the order next now sees what happened without knowing this plugin exists.

= 1.1.0 =
* New: a separate confirmation step. Choosing items and declaring withdrawal used to be one click. Article 11a(3) requires a confirmation control carrying no wording other than "confirm withdrawal", which only means something if the customer can read the declaration first, so the declaration is now shown back in full on a step of its own before anything is stored.
* New: the declaration carries the customer's name, the contract it refers to and their electronic contact details, which is what Article 11a(2) asks a withdrawal statement to contain. The name is prefilled from the order and stored with the request.
* New: the acknowledgement email is now a durable record under Article 11a(4). It repeats the declaration in full and states the date and time it was submitted, instead of only saying the request arrived.
* New: `[withdraw_link]` shortcode and an optional footer link. Article 11a(1) requires the function to be easily accessible for the whole withdrawal period, and the My Account control only reaches a signed-in customer already looking at that order, so a guest had no way in.
* Changed: the control now reads "Withdraw from contract here", the wording Article 11a(1) prescribes, instead of "Withdraw from this order".

= 1.0.8 =
* Fixed: a withdrawal could be recorded without the customer ever ticking the declaration. The checkbox carried only the browser's `required` attribute and the server never looked at it, so a request posted without it was stored as a valid declaration. That record is the whole point of the plugin, so it is now refused server-side and nothing is written.
* Fixed: the order link in the request log used the classic post editor URL, which does not open an order once HPOS is on, while the plugin declares HPOS compatibility. It now picks the right URL for whichever order storage the shop uses.
* Fixed: the confirmation sent to the customer ended with "We will confirm the next steps by email" and no code ever sent that email. Changing a request's status now writes to the customer, and the accepted message carries the 14-day return deadline and the refund method, which is information the trader owes anyway.

= 1.0.7 =
* Renamed to Plogins Withdraw so the name leads with the brand rather than a generic word, as the plugin review asked.
* Removed the "Tested up to" header from the main PHP file. It belongs in readme.txt only, where it is already declared; in both places the header can override the readme and show a compatibility version that was never intended.

= 1.0.6 =
* Translations: refreshed the bundled `plogins-withdraw.pot`, which had fallen behind the code. It was missing five strings from the withdrawal declarations admin screen and the privacy eraser, and still carried the plugin's pre-rename name. That template is what translators work from.
* Translations: corrected the Spanish catalogue, which called the right of withdrawal "retiro" throughout. Spanish consumer law calls it *desistimiento*, and the plugin's own description already used that term while its interface did not.

= 1.0.5 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.0.4 =
* New: withdrawal declarations are now covered by the WordPress personal-data tools. A privacy export includes a shopper's declarations, and an erasure request removes them, so a subject access or deletion request can be answered from the standard screen instead of by hand.
* Declared compatibility with WooCommerce 10.9.
* Copy: replaced long dashes with plain punctuation across the interface.
* Housekeeping: the release package no longer carries the translation catalogues, which come from the WordPress.org language packs.

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
