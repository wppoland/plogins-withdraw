<?php

declare(strict_types=1);

namespace Withdraw\Frontend;

use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;
use Withdraw\Service\StatutoryText;
use Withdraw\Service\WithdrawalService;

defined('ABSPATH') || exit;

/**
 * Keeps the withdrawal function reachable from anywhere, for anyone.
 *
 * Art. 11a(1) requires the function to be "prominently displayed" and "easily
 * accessible" for the whole withdrawal period. The My Account control satisfies
 * neither on its own: it lives inside a logged-in order view, so a guest cannot
 * reach it at all, and a shopper who has not signed in has no path to it.
 *
 * This adds three answers. A `[withdraw_link]` shortcode the shop can drop into a
 * menu, footer or legal page, the same link as a block for block themes (the
 * footer template part is where it belongs), and an optional site-wide footer
 * link.
 *
 * It also carries `[withdraw_instructions]`, the model instructions on
 * withdrawal from Annex I(A) to Directive 2011/83/EU, which art. 6(1)(h) makes
 * pre-contractual information rather than anything the withdrawal form can
 * supply after the fact.
 */
final class WithdrawLink implements HasHooks
{
    /** Set once the block printed a working link, so the footer link does not repeat it. */
    private bool $blockShown = false;

    public function registerHooks(): void
    {
        add_shortcode('withdraw_link', [$this, 'renderShortcode']);
        add_shortcode('withdraw_instructions', [$this, 'renderInstructions']);
        add_action('wp_footer', [$this, 'renderFooterLink']);
        add_action('woocommerce_email_after_order_table', [$this, 'renderEmailLink'], 10, 4);
        $this->registerBlock();
    }

    /**
     * The `plogins-withdraw/button` block. Dynamic, so the link always follows
     * the form page and label settings; the editor shows the server render.
     * Runs on init, which is when boot() registers hooks.
     */
    private function registerBlock(): void
    {
        wp_register_script(
            'plogins-withdraw-block',
            WITHDRAW_URL . 'assets/js/block-button.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'],
            \Withdraw\VERSION,
            true,
        );
        // JS translations are not built for this plugin, so the editor's
        // strings travel from PHP.
        wp_add_inline_script('plogins-withdraw-block', 'window.ploginsWithdrawBlock = ' . wp_json_encode([
            'panel'       => __('Link', 'plogins-withdraw'),
            'label'       => __('Link text', 'plogins-withdraw'),
            'placeholder' => self::label(self::settings()),
        ]) . ';', 'before');

        register_block_type(WITHDRAW_DIR . 'blocks/button', ['render_callback' => [$this, 'renderBlock']]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderBlock($attributes = []): string
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $s          = self::settings();
        $url        = self::formUrl($s);
        $text       = trim((string) ($attributes['label'] ?? ''));
        $text       = $text !== '' ? $text : self::label($s);
        $align      = in_array($attributes['align'] ?? '', ['left', 'center', 'right'], true) ? (string) $attributes['align'] : '';
        $wrapper    = get_block_wrapper_attributes($align !== '' ? ['class' => 'has-text-align-' . $align] : []);

        if ($url === '') {
            // A draft form page links nowhere, so the storefront shows nothing.
            // The editor preview still shows the label, or the block would
            // look broken on a fresh install.
            return wp_is_serving_rest_request()
                ? sprintf('<p %1$s><span class="withdraw-link">%2$s</span></p>', $wrapper, esc_html($text))
                : '';
        }

        $this->blockShown = true;

        return sprintf(
            '<p %1$s><a class="withdraw-link" href="%2$s">%3$s</a></p>',
            $wrapper,
            esc_url($url),
            esc_html($text),
        );
    }

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * The form page URL, or '' while there is no PUBLISHED page.
     *
     * A fresh install seeds a draft page for the shop to review. Its permalink
     * is a 404 for every customer, and a withdrawal link that leads nowhere is
     * worse than none, so nothing links to it until it is published.
     *
     * @param array<string, mixed> $settings
     */
    public static function formUrl(array $settings): string
    {
        $pageId = (int) ($settings['form_page_id'] ?? 0);

        if ($pageId < 1 || get_post_status($pageId) !== 'publish') {
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
        $url = self::formUrl(self::settings());

        if ($url === '') {
            return '';
        }

        $atts = shortcode_atts(['class' => 'withdraw-link'], is_array($atts) ? $atts : [], 'withdraw_link');

        return sprintf(
            '<a class="%1$s" href="%2$s">%3$s</a>',
            esc_attr((string) $atts['class']),
            esc_url($url),
            esc_html(self::label(self::settings())),
        );
    }

    public function renderFooterLink(): void
    {
        $s = self::settings();

        if (empty($s['footer_link']) || $this->blockShown) {
            return;
        }

        $url = self::formUrl($s);

        if ($url === '' || is_page((int) $s['form_page_id'])) {
            return;
        }

        printf(
            '<p class="withdraw-footer-link"><a href="%1$s">%2$s</a></p>',
            esc_url($url),
            esc_html(self::label($s)),
        );
    }

    /**
     * The withdrawal link in the customer's processing and completed emails.
     *
     * Only while the order can still be withdrawn from, so an email resent
     * after the period never offers a right that has lapsed. The link carries
     * no order id: the email can be forwarded, and the form asks for the order
     * number and billing email itself.
     *
     * @param mixed $order
     * @param mixed $sentToAdmin
     * @param mixed $plainText
     * @param mixed $email
     */
    public function renderEmailLink($order, $sentToAdmin = false, $plainText = false, $email = null): void
    {
        if (
            ! $order instanceof \WC_Order
            || $sentToAdmin
            || ! $email instanceof \WC_Email
            || ! in_array($email->id, ['customer_processing_order', 'customer_completed_order'], true)
        ) {
            return;
        }

        $s   = self::settings();
        $url = self::formUrl($s);

        if ($url === '' || ! WithdrawalService::withinPeriod($order, $s)) {
            return;
        }

        if ($plainText) {
            echo "\n" . esc_html(self::label($s)) . ': ' . esc_url_raw($url) . "\n\n";
            return;
        }

        printf(
            '<p class="withdraw-email-link"><a href="%1$s">%2$s</a></p>',
            esc_url($url),
            esc_html(self::label($s)),
        );
    }
}
