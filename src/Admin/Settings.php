<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;
use Withdraw\Service\DigitalConsentService;
use Withdraw\Service\EmailService;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\ReturnPolicy;
use Withdraw\Service\StatutoryText;

use const Withdraw\VERSION;

defined('ABSPATH') || exit;

/**
 * A single, sectioned settings screen (WooCommerce → Withdrawal) for the
 * `withdraw_settings` option, with inline help on every field.
 */
final class Settings implements HasHooks
{
    public const PAGE  = 'plogins-withdraw';
    private const OPTION = Migrator::OPTION_SETTINGS;

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Withdrawal', 'plogins-withdraw'),
            __('Withdrawal', 'plogins-withdraw'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_style('withdraw-admin', WITHDRAW_URL . 'assets/css/admin.css', [], VERSION);
        wp_enqueue_script('withdraw-admin', WITHDRAW_URL . 'assets/js/admin.js', [], VERSION, true);
    }

    public function registerSettings(): void
    {
        register_setting(self::PAGE, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
        add_filter('option_page_capability_' . self::PAGE, static fn (): string => 'manage_woocommerce');
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $in       = is_array($input) ? $input : [];
        $statuses = [];
        if (! empty($in['eligible_statuses']) && is_array($in['eligible_statuses'])) {
            foreach ($in['eligible_statuses'] as $st) {
                $statuses[] = sanitize_key((string) $st);
            }
        }

        return [
            'period_days'       => max(1, min(365, absint($in['period_days'] ?? 14))),
            'form_page_id'      => absint($in['form_page_id'] ?? 0),
            'eligible_statuses' => $statuses !== [] ? $statuses : ['completed', 'processing'],
            'notify_email'      => sanitize_email((string) ($in['notify_email'] ?? '')),
            'footer_link'       => ! empty($in['footer_link']),
            'magic_link'        => ! empty($in['magic_link']),
            // This method rebuilds the option from scratch, so a key missing
            // here is a field the screen accepts and then silently discards.
            'digital_consent'       => ! empty($in['digital_consent']),
            'digital_consent_intro' => sanitize_textarea_field((string) ($in['digital_consent_intro'] ?? '')),
            'link_text'         => sanitize_text_field((string) ($in['link_text'] ?? '')),
            'return_address'    => sanitize_textarea_field((string) ($in['return_address'] ?? '')),
            // Whitelisted rather than sanitised: an unknown value falls back to
            // saying nothing, which is the only answer that cannot assert a cost
            // rule on the shop's behalf.
            'return_cost'       => in_array((string) ($in['return_cost'] ?? ''), ReturnPolicy::COSTS, true)
                ? (string) $in['return_cost']
                : 'not_stated',
            'return_cost_note'  => sanitize_textarea_field((string) ($in['return_cost_note'] ?? '')),
            'seller_name'       => sanitize_text_field((string) ($in['seller_name'] ?? '')),
            'seller_email'      => sanitize_email((string) ($in['seller_email'] ?? '')),
            'seller_phone'      => sanitize_text_field((string) ($in['seller_phone'] ?? '')),
            'intro_text'        => sanitize_textarea_field((string) ($in['intro_text'] ?? '')),
            'model_form_text'   => sanitize_textarea_field((string) ($in['model_form_text'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(self::OPTION, []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        $s      = $this->settings();
        $counts = (new RequestRepository())->counts();

        /** A small help tooltip. */
        $help = static function (string $text): void {
            echo '<span class="withdraw-help" tabindex="0" aria-label="' . esc_attr($text) . '" data-withdraw-help="' . esc_attr($text) . '">?</span>';
        };
        ?>
        <div class="wrap withdraw-settings">
            <h1><span class="withdraw-logo" aria-hidden="true"></span> <?php echo esc_html__('Right of Withdrawal', 'plogins-withdraw'); ?></h1>
            <p class="withdraw-intro"><?php echo esc_html__('Complies with EU Directive 2023/2673 (Art. 11a): an easy withdrawal function letting customers declare a full or partial withdrawal for their orders. Requests are logged under WooCommerce → Withdrawal Requests.', 'plogins-withdraw'); ?></p>

            <p class="withdraw-counts">
                <strong><?php echo esc_html__('Requests:', 'plogins-withdraw'); ?></strong>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: pending, 2: accepted, 3: processed, 4: rejected */
                    __('%1$d pending, %2$d accepted, %3$d processed, %4$d rejected', 'plogins-withdraw'),
                    $counts['pending'],
                    $counts['accepted'],
                    $counts['processed'],
                    $counts['rejected'],
                ));
                ?>
                &middot; <a href="<?php echo esc_url(admin_url('admin.php?page=' . RequestsAdmin::PAGE)); ?>"><?php echo esc_html__('View requests', 'plogins-withdraw'); ?></a>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="withdraw-cards">

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('General', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-period"><?php echo esc_html__('Withdrawal period (days)', 'plogins-withdraw'); ?></label>
                                <input type="number" id="wd-period" min="1" max="365" name="<?php echo esc_attr(self::OPTION); ?>[period_days]" value="<?php echo esc_attr((string) $s['period_days']); ?>">
                                <?php $help(__('Statutory minimum is 14 days from delivery. The window starts when the order is marked completed, or at order creation if it never was.', 'plogins-withdraw')); ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Form page', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-page"><?php echo esc_html__('Withdrawal form page', 'plogins-withdraw'); ?></label>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => esc_attr(self::OPTION) . '[form_page_id]',
                                    'id'                => 'wd-page',
                                    'selected'          => (int) $s['form_page_id'],
                                    'show_option_none'  => esc_html__('Select a page', 'plogins-withdraw'),
                                    'option_none_value' => '0',
                                ]);
                                $help(__('Create a page with the [withdraw_form] shortcode, then select it here so the My Account button can link to it.', 'plogins-withdraw'));
                                ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header>
                            <h2>
                                <?php echo esc_html__('Eligibility', 'plogins-withdraw'); ?>
                                <?php $help(__('Customers can only submit a withdrawal for orders in one of these statuses. If none are checked, Completed and Processing are used.', 'plogins-withdraw')); ?>
                            </h2>
                        </header>
                        <div class="withdraw-fields">
                            <p class="withdraw-statuses" data-withdraw-statuses>
                                <?php foreach (wc_get_order_statuses() as $key => $label) :
                                    $slug = preg_replace('/^wc-/', '', $key); ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[eligible_statuses][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $s['eligible_statuses'], true)); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </p>
                            <p class="withdraw-note" data-withdraw-statuses-note hidden><?php echo esc_html__('No status checked: Completed and Processing will be saved instead.', 'plogins-withdraw'); ?></p>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Where the function appears', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[footer_link]" value="1" <?php checked(! empty($s['footer_link'])); ?>>
                                    <?php echo esc_html__('Show a withdrawal link in the site footer', 'plogins-withdraw'); ?>
                                </label>
                                <?php $help(__('The order view only reaches a signed-in customer looking at that order. Article 11a asks for the function to be easily accessible for the whole withdrawal period, so a footer link, a menu item or the [withdraw_link] shortcode on a legal page covers the shoppers the order view does not.', 'plogins-withdraw')); ?>
                            </p>
                            <p>
                                <label for="wd-link-text"><?php echo esc_html__('Link text', 'plogins-withdraw'); ?></label>
                                <input type="text" id="wd-link-text" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[link_text]" value="<?php echo esc_attr((string) $s['link_text']); ?>" placeholder="<?php echo esc_attr__('Withdraw from contract here', 'plogins-withdraw'); ?>">
                                <?php $help(__('Leave empty for the statutory wording. Article 11a(1) prescribes "withdraw from contract here" or an unambiguous equivalent, so if you change it, keep it unambiguous.', 'plogins-withdraw')); ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Guest access', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[magic_link]" value="1" <?php checked(! empty($s['magic_link'])); ?>>
                                    <?php echo esc_html__('Email a one-time link instead of opening the form straight away', 'plogins-withdraw'); ?>
                                </label>
                                <?php $help(__('Off by default: a customer opens the form with their order number and billing email. Turn this on and the form emails a one-time link to the order billing address instead, so knowing the order number is not enough. Signed-in customers reaching the form from My Account are never asked for a link. This makes the withdrawal function depend on your shop outgoing email working.', 'plogins-withdraw')); ?>
                            </p>
                            <?php if (! is_ssl()) : ?>
                                <p class="withdraw-note"><?php echo esc_html__('This site is not served over HTTPS. The one-time link travels in a page address and in a form field, so anyone able to watch the connection can read it.', 'plogins-withdraw'); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Digital content', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[digital_consent]" value="1" <?php checked(! empty($s['digital_consent'])); ?>>
                                    <?php echo esc_html__('Ask for Article 16(m) consent at checkout for downloadable products', 'plogins-withdraw'); ?>
                                </label>
                                <?php $help(__('Adds one optional, unticked checkbox to the checkout when the cart contains a downloadable product. It carries both halves of Article 16(m): the request to start supply immediately and the acknowledgement of losing the right of withdrawal. Once a file has actually been downloaded, that item stops being withdrawable. Virtual products that are not downloadable are services and are not covered.', 'plogins-withdraw')); ?>
                            </p>
                            <p class="withdraw-note">
                                <?php echo esc_html__('The box is never required. A customer who declines keeps the right of withdrawal and still gets the download, because WooCommerce grants download access regardless. That is the law working as intended, not a fault: consent that is a condition of purchase is not freely given, and would not hold up as an exclusion.', 'plogins-withdraw'); ?>
                            </p>
                            <p class="withdraw-note">
                                <?php echo esc_html__('Turning this back off restores the right of withdrawal on past orders too. Granting a withdrawal you could have refused is always lawful; honouring consent from a feature you have switched off would be the surprising outcome.', 'plogins-withdraw'); ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Emails', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-email"><?php echo esc_html__('Notification email', 'plogins-withdraw'); ?></label>
                                <input type="email" id="wd-email" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[notify_email]" value="<?php echo esc_attr((string) $s['notify_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <?php $help(__('Where new-request notifications are sent. Leave empty to use the site admin email. The customer always gets a confirmation at their own address.', 'plogins-withdraw')); ?>
                            </p>
                            <p class="withdraw-note">
                                <?php echo esc_html__('Every message this plugin sends is a WooCommerce email: same template, same logo, same footer as your order emails, and each one can be reworded or switched off on its own.', 'plogins-withdraw'); ?>
                            </p>
                            <ul class="withdraw-email-list">
                                <?php foreach (EmailService::catalogue() as $wd_email_id => $wd_email_title) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(EmailService::settingsUrl($wd_email_id)); ?>"><?php echo esc_html($wd_email_title); ?></a>
                                        <?php if (! EmailService::isEnabled($wd_email_id)) : ?>
                                            <em><?php echo esc_html__('(off)', 'plogins-withdraw'); ?></em>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>

                    <section class="withdraw-card">
                        <header><h2><?php echo esc_html__('Returns', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-return-address"><?php echo esc_html__('Return address', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Printed in the message that tells the customer their withdrawal was accepted. Leave empty to use your WooCommerce store address.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-return-address" class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION); ?>[return_address]" placeholder="<?php echo esc_attr(ReturnPolicy::storeAddress()); ?>"><?php echo esc_textarea((string) $s['return_address']); ?></textarea>
                            </p>
                            <?php if (trim((string) $s['return_address']) === '' && ReturnPolicy::storeAddress() === '') : ?>
                                <p class="withdraw-note"><?php echo esc_html__('Neither this field nor your WooCommerce store address is filled in, so the acceptance message will not say where to send the goods.', 'plogins-withdraw'); ?></p>
                            <?php endif; ?>
                            <p>
                                <label for="wd-return-cost"><?php echo esc_html__('Who pays to send the goods back', 'plogins-withdraw'); ?></label>
                                <select id="wd-return-cost" name="<?php echo esc_attr(self::OPTION); ?>[return_cost]">
                                    <option value="not_stated" <?php selected((string) $s['return_cost'], 'not_stated'); ?>><?php echo esc_html__('Say nothing', 'plogins-withdraw'); ?></option>
                                    <option value="customer" <?php selected((string) $s['return_cost'], 'customer'); ?>><?php echo esc_html__('The customer', 'plogins-withdraw'); ?></option>
                                    <option value="shop" <?php selected((string) $s['return_cost'], 'shop'); ?>><?php echo esc_html__('We do', 'plogins-withdraw'); ?></option>
                                </select>
                                <?php $help(__('Article 14(1) only lets you charge the customer for the return if you told them so before they bought, in your withdrawal information. If you did not, the cost is yours, which is why this says nothing until you choose.', 'plogins-withdraw')); ?>
                            </p>
                            <p>
                                <label for="wd-return-cost-note"><?php echo esc_html__('Return cost, extra wording', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Optional, added after the sentence above. Article 6(1)(i) wants an estimate of the cost for goods that cannot normally be sent back by post.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-return-cost-note" class="large-text" rows="2" name="<?php echo esc_attr(self::OPTION); ?>[return_cost_note]" placeholder="<?php echo esc_attr__('For example: bulky items are collected by courier, around 40 EUR.', 'plogins-withdraw'); ?>"><?php echo esc_textarea((string) $s['return_cost_note']); ?></textarea>
                            </p>
                            <p class="withdraw-note">
                                <?php
                                $wd_cost_preview = ReturnPolicy::costSentence();
                                echo esc_html($wd_cost_preview !== ''
                                    ? sprintf(
                                        /* translators: %s: the sentence the customer will read */
                                        __('The customer will read: %s', 'plogins-withdraw'),
                                        $wd_cost_preview,
                                    )
                                    : __('The acceptance message will not mention who pays for the return.', 'plogins-withdraw'));
                                ?>
                            </p>
                            <p class="withdraw-note">
                                <?php echo esc_html__('Your own clock: Article 13(1) gives you 14 days from the day the customer tells you they are withdrawing to refund them, including the standard delivery cost they paid. The request log shows that date and marks it when it passes.', 'plogins-withdraw'); ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card withdraw-card--wide">
                        <header><h2><?php echo esc_html__('Legal texts', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-seller-name"><?php echo esc_html__('Seller name', 'plogins-withdraw'); ?></label>
                                <input type="text" id="wd-seller-name" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[seller_name]" value="<?php echo esc_attr((string) $s['seller_name']); ?>" placeholder="<?php echo esc_attr(get_option('blogname')); ?>">
                                <?php $help(__('The trading name that appears in the model withdrawal form and the withdrawal instructions. Empty uses the site title. The address comes from your WooCommerce store address.', 'plogins-withdraw')); ?>
                            </p>
                            <p>
                                <label for="wd-seller-email"><?php echo esc_html__('Seller email', 'plogins-withdraw'); ?></label>
                                <input type="email" id="wd-seller-email" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[seller_email]" value="<?php echo esc_attr((string) $s['seller_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <label for="wd-seller-phone"><?php echo esc_html__('Seller phone', 'plogins-withdraw'); ?></label>
                                <input type="text" id="wd-seller-phone" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[seller_phone]" value="<?php echo esc_attr((string) $s['seller_phone']); ?>">
                                <?php $help(__('Annex I asks for a telephone number and email address where available, so a consumer can declare by any means.', 'plogins-withdraw')); ?>
                            </p>
                            <?php if (! StatutoryText::sellerIsComplete()) : ?>
                                <p class="withdraw-note"><?php echo esc_html__('Your WooCommerce store address is empty, so the model withdrawal form cannot state a geographical address. Fill it in under WooCommerce, Settings, General.', 'plogins-withdraw'); ?></p>
                            <?php endif; ?>
                            <p>
                                <label for="wd-intro"><?php echo esc_html__('Form intro text', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Shown above the order lookup step. Leave empty for the wording below, which is translated with the rest of the plugin.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-intro" class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION); ?>[intro_text]" placeholder="<?php echo esc_attr(StatutoryText::intro()); ?>"><?php echo esc_textarea((string) $s['intro_text']); ?></textarea>
                            </p>
                            <p>
                                <label for="wd-model"><?php echo esc_html__('Model withdrawal text', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Shown on the items step. Leave empty and the plugin generates the statutory model withdrawal form (Annex I.B) from the seller details above, in the language of the site.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-model" class="large-text" rows="6" name="<?php echo esc_attr(self::OPTION); ?>[model_form_text]" placeholder="<?php echo esc_attr(StatutoryText::modelForm()); ?>"><?php echo esc_textarea((string) $s['model_form_text']); ?></textarea>
                            </p>
                            <p class="withdraw-note">
                                <?php echo esc_html__('The model instructions on withdrawal (Annex I.A) are pre-contractual information, so they belong on a page of their own rather than on the form. Put the [withdraw_instructions] shortcode on your terms or returns page and the plugin generates them from the same details, including your withdrawal period.', 'plogins-withdraw'); ?>
                            </p>
                            <p>
                                <label for="wd-consent-intro"><?php echo esc_html__('Digital content consent, extra wording', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Optional, and added in front of the statutory sentence rather than replacing it. The plugin refuses withdrawal for downloaded items on the strength of the acknowledgement in that sentence, so wording that only promised immediate delivery would take away a right nobody gave up. Whatever the customer sees is stored with their order, so editing this later does not rewrite what earlier customers agreed to.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-consent-intro" class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION); ?>[digital_consent_intro]" placeholder="<?php echo esc_attr__('For example: these files are available the moment your payment clears.', 'plogins-withdraw'); ?>"><?php echo esc_textarea((string) $s['digital_consent_intro']); ?></textarea>
                            </p>
                            <p class="withdraw-note">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: the statutory Article 16(m) consent sentence */
                                    __('The checkbox always ends with: %s', 'plogins-withdraw'),
                                    DigitalConsentService::statutoryConsentText(),
                                ));
                                ?>
                            </p>
                        </div>
                    </section>

                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
