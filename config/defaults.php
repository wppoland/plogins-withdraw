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
    'link_text'         => '',
    'intro_text'        => 'Use this form to withdraw from your purchase. Select the items you want to withdraw from and submit the declaration.',
    'model_form_text'   => "Model withdrawal form. To [seller name and address]: I hereby give notice that I withdraw from my contract for the sale of the following goods. Ordered on / received on. Name of consumer. Date.",
];
