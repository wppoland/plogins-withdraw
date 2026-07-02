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
    'intro_text'        => 'Use this form to withdraw from your purchase. Select the items you want to withdraw from and submit the declaration.',
    'model_form_text'   => "Model withdrawal form. To [seller name and address]: I hereby give notice that I withdraw from my contract for the sale of the following goods. Ordered on / received on. Name of consumer. Date.",
];
