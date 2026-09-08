<?php
/**
 * The Article 16(m) consent, confirmed back to the customer.
 *
 * Art. 16(m) only excludes the right of withdrawal if the trader also gave the
 * Art. 7(2)/8(7) confirmation, and Art. 8(7)(b) requires that confirmation to
 * carry the consent AND the acknowledgement. This block is therefore part of the
 * feature, not an extra: without it the exclusion does not exist. Shared by the
 * order-details screen and the order email so both say the same thing.
 *
 * @package Withdraw
 *
 * @var string $text  the wording the customer agreed to, as stored
 * @var string $given when it was given, in site time ('' if unknown)
 * @var bool   $plain true inside a plain-text email
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$withdraw_heading = __('Digital content: right of withdrawal', 'plogins-withdraw');
$withdraw_when    = $given !== ''
    ? sprintf(
        /* translators: %s: date and time the consent was given */
        __('Given at checkout on %s.', 'plogins-withdraw'),
        $given,
    )
    : '';

// Consent is not the moment the right goes: under Art. 16(m) it goes when
// supply begins. A confirmation that stopped at the acknowledgement would read
// as if the customer had already lost the right, and one who has not downloaded
// anything yet would not use a right they still have.
$withdraw_still = __('This applies to each item from the moment its supply begins. Until then you can still withdraw from it.', 'plogins-withdraw');

if ($plain) {
    echo "\n" . esc_html($withdraw_heading) . "\n";
    echo esc_html($text) . "\n";
    echo esc_html($withdraw_still) . "\n";
    if ($withdraw_when !== '') {
        echo esc_html($withdraw_when) . "\n";
    }
    return;
}
?>
<section class="withdraw-consent-notice">
    <h2 class="withdraw-consent-notice__heading"><?php echo esc_html($withdraw_heading); ?></h2>
    <p class="withdraw-consent-notice__text"><?php echo esc_html($text); ?></p>
    <p class="withdraw-consent-notice__still"><?php echo esc_html($withdraw_still); ?></p>
    <?php if ($withdraw_when !== '') : ?>
        <p class="withdraw-consent-notice__when"><?php echo esc_html($withdraw_when); ?></p>
    <?php endif; ?>
</section>
