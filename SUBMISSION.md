# Plogins Withdraw - wp.org submission packet

Queued FREE plugin (after tiers and Pair). `.distignore` excludes `/SUBMISSION.md`.

## Upload

- **Zip:** `/tmp/plogins-withdraw.zip` (32 KB, built honouring `.distignore`, 0 forbidden folders, version 0.1.0).
- **Add your plugin:** https://wordpress.org/plugins/developers/add/
- **Requested slug:** `plogins-withdraw` (text domain already `plogins-withdraw`, so 0 TextDomainMismatch).

## Plugin Check (wp-env, WooCommerce + plugin-check, folder named plogins-withdraw)

- Severity 7 (errors): **0**.
- Severity 5 (warnings): 8, all the same accepted pattern — a dynamic table name (`$wpdb->prefix . 'withdraw_requests'`) interpolated into SQL (`PluginCheck.Security.DirectDB.UnescapedDBParameter` x5, `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` x3, in `src/Service/RequestRepository.php`). A table identifier cannot be passed through `$wpdb->prepare()`; the name is built from the trusted `$wpdb->prefix`, never user input. This is the standard WordPress pattern and is not a guideline violation.
- Fixed during review-prep: removed `load_plugin_textdomain()` (auto-loaded since WP 4.6), prefixed template/uninstall variables, trimmed the short description to 136 chars.

## Listing copy

- **Display name:** Plogins Withdraw - Right of Withdrawal Button for WooCommerce
- **Short description (136 chars):** Full or partial EU right-of-withdrawal requests (Directive 2023/2673) for WooCommerce orders, with an admin log and email notifications.
- **Full description / FAQ / screenshots / changelog:** `readme.txt`.

## After approval

- Deploy `.wordpress-org/blueprints/blueprint.json` to SVN `/assets/blueprints/` (Live Preview) via `_deploy-blueprints-svn.sh` (add `plogins-withdraw` to it).
- Set the registry `plogins-withdraw` status live.

## Reply-to-reviewer note (if they ask about the DB warnings)

> The flagged SQL uses an interpolated table name (`{$wpdb->prefix}withdraw_requests`).
> Table identifiers cannot be bound with `$wpdb->prepare()`; the value is derived
> solely from `$wpdb->prefix`, never from user input. All user-supplied values in
> those queries use `%d`/`%s` placeholders.
