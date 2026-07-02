<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;
use Withdraw\Service\RequestRepository;

defined('ABSPATH') || exit;

/**
 * Settings page (WooCommerce → Withdrawal) for the `withdraw_settings` option.
 */
final class Settings implements HasHooks
{
    public const PAGE  = 'plogins-withdraw';
    private const OPTION = Migrator::OPTION_SETTINGS;

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Right of Withdrawal', 'plogins-withdraw'); ?></h1>
            <p><?php echo esc_html__('Complies with EU Directive 2023/2673 (Art. 11a): an easy withdrawal function letting customers declare a full or partial withdrawal for their orders. Requests are logged under Withdrawal → Requests.', 'plogins-withdraw'); ?></p>
            <p>
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
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wd-period"><?php echo esc_html__('Withdrawal period (days)', 'plogins-withdraw'); ?></label></th>
                        <td><input type="number" id="wd-period" min="1" max="365" name="<?php echo esc_attr(self::OPTION); ?>[period_days]" value="<?php echo esc_attr((string) $s['period_days']); ?>"> <span class="description"><?php echo esc_html__('Statutory minimum is 14 days from delivery.', 'plogins-withdraw'); ?></span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wd-page"><?php echo esc_html__('Withdrawal form page', 'plogins-withdraw'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name'              => esc_attr(self::OPTION) . '[form_page_id]',
                                'id'                => 'wd-page',
                                'selected'          => (int) $s['form_page_id'],
                                'show_option_none'  => esc_html__('— Select —', 'plogins-withdraw'),
                                'option_none_value' => '0',
                            ]);
                            ?>
                            <p class="description"><?php echo esc_html__('Create a page with the [withdraw_form] shortcode, then select it here so the My Account button can link to it.', 'plogins-withdraw'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Eligible order statuses', 'plogins-withdraw'); ?></th>
                        <td>
                            <?php foreach (wc_get_order_statuses() as $key => $label) :
                                $slug = preg_replace('/^wc-/', '', $key); ?>
                                <label style="display:inline-block;margin:0 12px 6px 0">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[eligible_statuses][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $s['eligible_statuses'], true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wd-email"><?php echo esc_html__('Notification email', 'plogins-withdraw'); ?></label></th>
                        <td><input type="email" id="wd-email" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[notify_email]" value="<?php echo esc_attr((string) $s['notify_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wd-intro"><?php echo esc_html__('Form intro text', 'plogins-withdraw'); ?></label></th>
                        <td><textarea id="wd-intro" class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION); ?>[intro_text]"><?php echo esc_textarea((string) $s['intro_text']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wd-model"><?php echo esc_html__('Model withdrawal text', 'plogins-withdraw'); ?></label></th>
                        <td><textarea id="wd-model" class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION); ?>[model_form_text]"><?php echo esc_textarea((string) $s['model_form_text']); ?></textarea>
                        <p class="description"><?php echo esc_html__('Shown on the form. Adapt to the statutory model withdrawal form (Annex I.B).', 'plogins-withdraw'); ?></p></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
