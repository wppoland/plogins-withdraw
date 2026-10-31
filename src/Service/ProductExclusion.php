<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Art. 16 exceptions a shop marks on a product or a product category.
 *
 * Directive 2011/83/EU Art. 16:
 *   (c) goods made to the consumer's specifications or clearly personalised;
 *   (d) goods liable to deteriorate or expire rapidly;
 *   (e) sealed goods not suitable for return for health protection or hygiene
 *       reasons, and only once unsealed after delivery.
 *
 * (c) and (d) take the line out of the form. (e) cannot: the right is lost
 * only if the seal was broken after delivery, which the shop cannot know when
 * the consumer declares, so it stays withdrawable and carries a notice. The
 * product page states the exception before purchase, which Art. 6(1)(k) asks
 * for.
 *
 * The reason is copied onto each order line at checkout, so a later change
 * to the product or category never adds or removes a right the consumer
 * already has under the contract as concluded.
 */
final class ProductExclusion implements HasHooks
{
    public const META = '_withdraw_exclusion';

    /** Reasons that remove a line from the form. */
    private const EXCLUDING = ['custom', 'perishable'];

    public function registerHooks(): void
    {
        add_action('woocommerce_single_product_summary', [$this, 'renderProductNotice'], 25);
        // Classic checkout and the Store API (block checkout) both build lines through WC_Checkout.
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'snapshotLine']);

        if (is_admin()) {
            add_action('woocommerce_product_options_general_product_data', [$this, 'renderProductField']);
            add_action('woocommerce_admin_process_product_object', [$this, 'saveProductField']);
            add_action('product_cat_add_form_fields', [$this, 'renderTermAddField']);
            add_action('product_cat_edit_form_fields', [$this, 'renderTermEditField']);
            add_action('created_product_cat', [$this, 'saveTermField']);
            add_action('edited_product_cat', [$this, 'saveTermField']);
        }
    }

    /** @return array<string, string> reason => admin label */
    public static function options(): array
    {
        return [
            ''           => __('The right of withdrawal applies', 'plogins-withdraw'),
            'custom'     => __('Made to specification or clearly personalised (Art. 16(c))', 'plogins-withdraw'),
            'perishable' => __('Liable to deteriorate or expire rapidly (Art. 16(d))', 'plogins-withdraw'),
            'sealed'     => __('Sealed for health or hygiene reasons (Art. 16(e))', 'plogins-withdraw'),
        ];
    }

    /** What the customer reads, on the product page and in the form. */
    public static function notice(string $reason): string
    {
        return match ($reason) {
            'custom'     => __('This product is made to your specifications or clearly personalised, so the right of withdrawal does not apply to it.', 'plogins-withdraw'),
            'perishable' => __('This product is liable to deteriorate or expire rapidly, so the right of withdrawal does not apply to it.', 'plogins-withdraw'),
            'sealed'     => __('This product is sealed for health protection or hygiene reasons. You lose the right of withdrawal if you unseal it after delivery.', 'plogins-withdraw'),
            default      => '',
        };
    }

    /**
     * The product's own setting, else the first of its categories that has one.
     * A variation is judged by its parent, which is where the field lives.
     */
    public function reasonFor(int $productId): string
    {
        $own = (string) get_post_meta($productId, self::META, true);
        if (isset(self::options()[$own]) && $own !== '') {
            return $own;
        }

        // ponytail: direct categories only, no inheritance from parent terms.
        $terms = get_the_terms($productId, 'product_cat');
        foreach (is_array($terms) ? $terms : [] as $term) {
            $value = (string) get_term_meta($term->term_id, self::META, true);
            if ($value !== '' && isset(self::options()[$value])) {
                return $value;
            }
        }

        return '';
    }

    /** @return array<int,string> order item id => 'custom' | 'perishable' */
    public function excludedItems(\WC_Order $order): array
    {
        $out = [];
        foreach ($this->reasons($order) as $itemId => $reason) {
            if (in_array($reason, self::EXCLUDING, true)) {
                $out[$itemId] = $reason;
            }
        }

        return $out;
    }

    /** @return list<int> order item ids marked sealed (Art. 16(e)) */
    public function sealedItems(\WC_Order $order): array
    {
        return array_keys(array_filter($this->reasons($order), static fn (string $r): bool => $r === 'sealed'));
    }

    /** Stored on the line before it is saved; 'none' records that the right applied. */
    public function snapshotLine(\WC_Order_Item_Product $item): void
    {
        $item->update_meta_data(self::META, $this->reasonFor((int) $item->get_product_id()) ?: 'none');
    }

    /** @return array<int,string> */
    private function reasons(\WC_Order $order): array
    {
        $out = [];
        foreach ($order->get_items() as $itemId => $item) {
            if ($item instanceof \WC_Order_Item_Product) {
                // Lines bought before 1.8.0 have no snapshot: fall back to the current mark.
                $reason = $item->meta_exists(self::META)
                    ? (string) $item->get_meta(self::META)
                    : $this->reasonFor((int) $item->get_product_id());
                if (! isset(self::options()[$reason])) {
                    $reason = '';
                }
                if ($reason !== '') {
                    $out[(int) $itemId] = $reason;
                }
            }
        }

        return $out;
    }

    public function renderProductNotice(): void
    {
        global $product;
        if (! $product instanceof \WC_Product) {
            return;
        }

        $text = self::notice($this->reasonFor($product->get_parent_id() ?: $product->get_id()));
        if ($text !== '') {
            echo '<p class="withdraw-product-notice">' . esc_html($text) . '</p>';
        }
    }

    public function renderProductField(): void
    {
        woocommerce_wp_select([
            'id'          => self::META,
            'label'       => __('Right of withdrawal', 'plogins-withdraw'),
            'options'     => self::options(),
            'desc_tip'    => true,
            'description' => __('Overrides the category setting. Made-to-specification and perishable products are left out of the withdrawal form; sealed hygiene products stay in it with a notice, because the right is only lost once the seal is broken after delivery.', 'plogins-withdraw'),
        ]);
    }

    public function saveProductField(\WC_Product $product): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product edit nonce before firing this action.
        $value = isset($_POST[self::META]) ? sanitize_key(wp_unslash($_POST[self::META])) : '';
        $product->update_meta_data(self::META, isset(self::options()[$value]) ? $value : '');
    }

    public function renderTermAddField(): void
    {
        echo '<div class="form-field"><label for="withdraw-exclusion">' . esc_html__('Right of withdrawal', 'plogins-withdraw') . '</label>';
        $this->renderTermSelect('');
        echo '<p>' . esc_html__('Applies to every product in this category that has no setting of its own.', 'plogins-withdraw') . '</p></div>';
    }

    public function renderTermEditField(\WP_Term $term): void
    {
        echo '<tr class="form-field"><th scope="row"><label for="withdraw-exclusion">' . esc_html__('Right of withdrawal', 'plogins-withdraw') . '</label></th><td>';
        $this->renderTermSelect((string) get_term_meta($term->term_id, self::META, true));
        echo '<p class="description">' . esc_html__('Applies to every product in this category that has no setting of its own.', 'plogins-withdraw') . '</p></td></tr>';
    }

    private function renderTermSelect(string $current): void
    {
        echo '<select id="withdraw-exclusion" name="' . esc_attr(self::META) . '">';
        foreach (self::options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function saveTermField(int $termId): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verified the term form nonce and the edit_term capability before firing this action.
        if (! isset($_POST[self::META]) || ! current_user_can('edit_term', $termId)) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
        $value = sanitize_key(wp_unslash($_POST[self::META]));
        update_term_meta($termId, self::META, isset(self::options()[$value]) ? $value : '');
    }
}
