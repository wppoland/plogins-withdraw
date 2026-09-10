<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;
use Withdraw\Service\StatutoryText;

defined('ABSPATH') || exit;

/**
 * Keeps the withdrawal function reachable from anywhere, for anyone.
 *
 * Art. 11a(1) requires the function to be "prominently displayed" and "easily
 * accessible" for the whole withdrawal period. The My Account control satisfies
 * neither on its own: it lives inside a logged-in order view, so a guest cannot
 * reach it at all, and a shopper who has not signed in has no path to it.
 *
 * This adds two answers. A `[withdraw_link]` shortcode the shop can drop into a
 * menu, footer or legal page, and an optional site-wide footer link.
 *
 * It also carries `[withdraw_instructions]`, the model instructions on
 * withdrawal from Annex I(A) to Directive 2011/83/EU, which art. 6(1)(h) makes
 * pre-contractual information rather than anything the withdrawal form can
 * supply after the fact.
 */
final class WithdrawLink implements HasHooks
{
    public function registerHooks(): void
    {
        add_shortcode('withdraw_link', [$this, 'renderShortcode']);
        add_shortcode('withdraw_instructions', [$this, 'renderInstructions']);
        add_action('wp_footer', [$this, 'renderFooterLink']);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    private function formUrl(): string
    {
        $pageId = (int) $this->settings()['form_page_id'];

        if ($pageId < 1) {
            return '';
        }

        return (string) (get_permalink($pageId) ?: '');
    }

    /**
     * The label on every control that opens the withdrawal form.
     *
     * Static and shared, because the same rule has to hold on the My Account
     * order view too. It did not: that button printed the statutory English
     * sentence directly, so a shop that reworded the link saw its wording on
     * the shortcode and the footer and the untouched default on the order.
     *
     * @param array<string, mixed> $settings
     */
    public static function label(array $settings): string
    {
        $custom = trim((string) ($settings['link_text'] ?? ''));

        // The statutory wording is the default and stays translatable. A shop
        // may reword it, which the directive allows as long as the alternative
        // is unambiguous, so this is deliberately not locked down.
        return $custom !== '' ? $custom : __('Withdraw from contract here', 'plogins-withdraw');
    }

    /**
     * The model instructions on withdrawal, Annex I(A).
     *
     * Generated from the shop's own details and its withdrawal period, so it
     * cannot drift out of step with the setting the form actually enforces.
     *
     * Attributes: `heading="no"` drops the heading, for a page that already has
     * one; `form="no"` drops the model form printed underneath it.
     *
     * @param array<string, string>|string $atts
     */
    public function renderInstructions($atts = []): string
    {
        $atts = shortcode_atts([
            'heading' => 'yes',
            'form'    => 'yes',
        ], is_array($atts) ? $atts : [], 'withdraw_instructions');

        $out = '<div class="withdraw-instructions">';

        if ($atts['heading'] !== 'no') {
            $out .= '<h2>' . esc_html__('Right of withdrawal', 'plogins-withdraw') . '</h2>';
        }

        $out .= wpautop(esc_html(StatutoryText::instructions()));

        if ($atts['form'] !== 'no') {
            $out .= '<pre class="withdraw-model-form" style="white-space:pre-wrap">'
                . esc_html(StatutoryText::modelForm())
                . '</pre>';
        }

        return $out . '</div>';
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function renderShortcode($atts = []): string
    {
        $url = $this->formUrl();

        if ($url === '') {
            return '';
        }

        $atts = shortcode_atts(['class' => 'withdraw-link'], is_array($atts) ? $atts : [], 'withdraw_link');

        return sprintf(
            '<a class="%1$s" href="%2$s">%3$s</a>',
            esc_attr((string) $atts['class']),
            esc_url($url),
            esc_html(self::label($this->settings())),
        );
    }

    public function renderFooterLink(): void
    {
        if (empty($this->settings()['footer_link'])) {
            return;
        }

        $url = $this->formUrl();

        if ($url === '' || is_page((int) $this->settings()['form_page_id'])) {
            return;
        }

        printf(
            '<p class="withdraw-footer-link"><a href="%1$s">%2$s</a></p>',
            esc_url($url),
            esc_html(self::label($this->settings())),
        );
    }
}
