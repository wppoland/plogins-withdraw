<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;

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
 */
final class WithdrawLink implements HasHooks
{
    public function registerHooks(): void
    {
        add_shortcode('withdraw_link', [$this, 'renderShortcode']);
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

    private function label(): string
    {
        $custom = trim((string) $this->settings()['link_text']);

        // The statutory wording is the default and stays translatable. A shop
        // may reword it, which the directive allows as long as the alternative
        // is unambiguous, so this is deliberately not locked down.
        return $custom !== '' ? $custom : __('Withdraw from contract here', 'plogins-withdraw');
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
            esc_html($this->label()),
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
            esc_html($this->label()),
        );
    }
}
