<?php
/**
 * Shown after a one-time withdrawal link was requested.
 *
 * Rendered identically whether an order matched, nothing matched, or the request
 * was throttled. Any difference in what this panel says, or in which controls it
 * offers, would tell a stranger whether an order number and an address belong
 * together, so it says one thing and offers one control in all three cases.
 *
 * @package Withdraw
 *
 * @var int $minutes How long an issued link stays valid.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-form withdraw-form--sent">
    <p class="withdraw-form__sent" role="status">
        <?php
        echo esc_html(sprintf(
            /* translators: %d: number of minutes the link stays valid */
            __('If that order exists and the email you gave is the billing address on it, we have sent a withdrawal link to that address. The link works for %d minutes. Check your spam folder if it does not arrive.', 'plogins-withdraw'),
            $minutes,
        ));
        ?>
    </p>

    <form method="post" class="withdraw-form__form">
        <?php
        // This posts no step, so it only re-renders the lookup form: it sends
        // nothing and changes nothing, which is why it carries no nonce. It used
        // to print a `withdraw_lookup` nonce that no handler ever verified, and a
        // nonce nobody checks is worse than none, it reads as protection that is
        // not there. The label says what the button does; requesting the next
        // link goes through the form and is rate limited like the first attempt.
        ?>
        <p class="withdraw-form__actions">
            <button type="submit" class="button withdraw-form__submit"><?php echo esc_html__('Back to the withdrawal form', 'plogins-withdraw'); ?></button>
        </p>
    </form>
</div>
