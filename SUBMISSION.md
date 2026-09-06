# Plogins Withdraw - wp.org submission packet

Next FREE plugin in the queue, now that Pair is approved and published. Not
part of the shipped zip (`.distignore` excludes `/SUBMISSION.md`).

## Upload

- **Zip:** `/tmp/plogins-withdraw.zip` (32 KB, 38 files, built with `bash scripts/build-zip.sh` honouring `.distignore`).
- **Add your plugin:** https://wordpress.org/plugins/developers/add/
- **Requested slug:** `plogins-withdraw` (text domain is already `plogins-withdraw`, so no TextDomainMismatch).
- **Version in this package:** 1.0.6

## Short description (140 chars max)

Full or partial EU right-of-withdrawal requests (Directive 2023/2673) for WooCommerce orders, with an admin log and email notifications.

(136 characters. This is also the readme's short description, so the two agree.)

## One-paragraph description (paste into the submission form)

Withdraw adds the EU right-of-withdrawal function to WooCommerce, aligned with
Directive 2023/2673 (the "withdrawal button", Art. 11a). A `[withdraw_form]`
shortcode renders a two-step form: the customer looks up an order by number and
billing email, which works for guest orders with no account, then selects items
and quantities and submits a full or partial withdrawal declaration. A "Withdraw
from this order" button also appears under the order in My Account. The
withdrawal period is configurable with the statutory 14-day minimum, measured
from delivery or, if the order was never completed, from the order date. Every
request is logged under WooCommerce with a status workflow (pending, accepted,
rejected, processed), and both the customer and the shop get an email. It is a
request-and-log plugin: it never moves money, so refunds stay in the normal
WooCommerce order screen, which matches the legal model where the customer
declares and the trader acts. Tested on WordPress 7.1 with WooCommerce 11.1.

## Listing copy

- **Display name:** Withdraw - Right of Withdrawal Button for WooCommerce
- **Full description / FAQ / changelog:** `readme.txt` (the directory renders this).

## Pre-submission checks run

- `phpcs`: clean, 19 files.
- `php -l`: clean across every non-vendor file.
- Package audit: no `vendor/`, `tests/`, `.wordpress-org/`, `.po`, `.mo`,
  `composer.json` or `SUBMISSION.md` in the zip. Top-level folder is
  `plogins-withdraw`, matching the text domain and the requested slug.
- Version agreement: header, `const VERSION` and `Stable tag` all read 1.0.6.
- `.pot` regenerated (70 to 75 msgids). The shipped template had fallen behind
  the code: it was missing five strings and still carried the pre-rename plugin
  name. GlotPress takes its originals from the released package, so shipping it
  stale would have made those five untranslatable.

- Official **Plugin Check** (severity 7, errors) run against the built package in
  wp-env with WooCommerce active: **PASS**, 0 errors. This is the reviewer's
  actual gate, so it is the check that matters. See [[plugin-check-before-submit]].

## Still to do

- Upload the zip. That step needs a WordPress.org login and only one plugin can
  sit in review at a time, so it is the user's to run.

## After approval

- Add `withdraw:plogins-withdraw` to the `PUBLISHED` map in
  `scripts/release/wporg-release.sh`. Without it a later bump silently never ships.
- Commit trunk alone first, then `assets/`, then the tag: on a brand-new repo the
  pre-commit hook refuses `assets/` and `tags/` in the commit that creates `trunk/`.
- Push `.wordpress-org/` icon, banner and screenshots to SVN `assets/`, plus
  `blueprints/blueprint.json` to turn on Live Preview.
- Registry: set `status: "live"` **and** `wpOrgLive: true` together, and change the
  product badge off "coming soon" in all four locales (pl, en, de in
  `plugins.config.ts`, es in `i18n-es.generated.ts`). `check-registry` and
  `check-claims` both fail if these drift apart.
- Check the install docs for "when live on WordPress.org" hedging and the
  `notFor` bullets: `check-claims` fails the build on both once `wpOrgLive` is set.
- Deploy the store so the wp.org link and `/go/plogins-withdraw/` resolve.
