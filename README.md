# Plogins Withdraw

EU right-of-withdrawal button and form for WooCommerce (**Directive 2023/2673**, Art. 11a). Lets customers declare a full or partial withdrawal from a distance contract, logs it in the admin, and emails the customer and the shop. Request-and-log only — the merchant refunds in the normal order screen.

## Features

- `[withdraw_form]` shortcode: two-step form — order lookup (number + billing email, guest-safe) → item/quantity selection + withdrawal declaration.
- Full or partial (per-item) withdrawal.
- "Withdraw from this order" button under My Account order details (the visible withdrawal button), pre-fills the order.
- Configurable withdrawal period (default 14 days) from delivery/completion; eligible order statuses.
- Admin log (WooCommerce → Withdrawal Requests) with statuses: pending / accepted / rejected / processed.
- Customer confirmation + shop notification emails.
- Editable model withdrawal text (Annex I.B).
- HPOS + Cart/Checkout Blocks compatible.

## Architecture

Family kit pattern (namespace `Withdraw\`): `Plugin` singleton + `Container`, `Migrator` (creates `{prefix}_withdraw_requests`, version-gated, multisite-safe), `Service\WithdrawalService` (shortcode + flow + emails), `Service\RequestRepository` (prepared CRUD), `Frontend\MyAccount` (order button), `Admin\Settings` + `Admin\RequestsAdmin`. Fires `withdraw/booted` for a future PRO.

## Development

```bash
composer install
composer cs        # PHPCS
composer analyse   # PHPStan level 6
npx @wordpress/env start
```
