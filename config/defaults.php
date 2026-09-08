<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Default values for the `withdraw_settings` option.
 *
 * @return array<string, mixed>
 */
return [
    'period_days'       => 14,
    'form_page_id'      => 0,
    'eligible_statuses' => ['completed', 'processing'],
    'notify_email'      => '',
    // Art. 11a(1) wants the function reachable throughout the withdrawal
    // period. Off by default because a site-wide footer link is a visible
    // change to every page and that is the shop's call, not an update's.
    'footer_link'       => false,
    // Turning this on makes the shop's outgoing email a dependency of a
    // statutory function: with broken SMTP a guest cannot withdraw at all. That
    // is the shop's decision to take, never an update's, so it ships off.
    'magic_link'        => false,
    // Art. 16(m) consent at checkout. Off because the plugin is already running
    // on live shops and an update must not put a new checkbox in front of their
    // customers without the shop asking for it. While off nothing registers,
    // nothing renders, and eligibility is exactly what it is today.
    'digital_consent'      => false,
    // Optional wording shown BEFORE the statutory sentence, never instead of
    // it. The acknowledgement in that sentence is what the exclusion rests on,
    // so it is not a shop's to delete. `link_text` is free-text because it only
    // labels a link; this one decides whether a right is lost.
    'digital_consent_intro' => '',
    'link_text'         => '',
    'intro_text'        => 'Use this form to withdraw from your purchase. Select the items you want to withdraw from and submit the declaration.',
    'model_form_text'   => "Model withdrawal form. To [seller name and address]: I hereby give notice that I withdraw from my contract for the sale of the following goods. Ordered on / received on. Name of consumer. Date.",
];
