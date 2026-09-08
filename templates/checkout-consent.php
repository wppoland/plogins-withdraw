<?php
/**
 * Article 16(m) consent, classic checkout.
 *
 * Rendered above the Place order button, next to the terms checkbox. Optional
 * and never pre-ticked: consent that is a condition of purchase is not freely
 * given, and the Consumer Rights Directive bans pre-ticked boxes outright.
 *
 * @package Withdraw
 *
 * @var string $text the consent + acknowledgement wording
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="withdraw-checkout-consent">
    <p class="form-row withdraw-checkout-consent__row">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="withdraw-digital-consent">
            <input type="checkbox"
                class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                name="withdraw_digital_consent"
                id="withdraw-digital-consent"
                value="1">
            <span class="withdraw-checkout-consent__text"><?php echo esc_html($text); ?></span>
        </label>
    </p>
</div>
