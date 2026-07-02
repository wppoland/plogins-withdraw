<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;
use Withdraw\Service\RequestRepository;

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
                        <header><h2><?php echo esc_html__('Emails', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-email"><?php echo esc_html__('Notification email', 'plogins-withdraw'); ?></label>
                                <input type="email" id="wd-email" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[notify_email]" value="<?php echo esc_attr((string) $s['notify_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <?php $help(__('Where new-request notifications are sent. Leave empty to use the site admin email. The customer always gets a confirmation at their own address.', 'plogins-withdraw')); ?>
                            </p>
                        </div>
                    </section>

                    <section class="withdraw-card withdraw-card--wide">
                        <header><h2><?php echo esc_html__('Legal texts', 'plogins-withdraw'); ?></h2></header>
                        <div class="withdraw-fields">
                            <p>
                                <label for="wd-intro"><?php echo esc_html__('Form intro text', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Shown above the order lookup step of the withdrawal form.', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-intro" class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION); ?>[intro_text]"><?php echo esc_textarea((string) $s['intro_text']); ?></textarea>
                            </p>
                            <p>
                                <label for="wd-model"><?php echo esc_html__('Model withdrawal text', 'plogins-withdraw'); ?></label>
                                <?php $help(__('Shown on the form. Adapt to the statutory model withdrawal form (Annex I.B).', 'plogins-withdraw')); ?><br>
                                <textarea id="wd-model" class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION); ?>[model_form_text]"><?php echo esc_textarea((string) $s['model_form_text']); ?></textarea>
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
