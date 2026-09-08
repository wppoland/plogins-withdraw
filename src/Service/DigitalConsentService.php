<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsSchema\Validation;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Withdraw\Contract\HasHooks;
use Withdraw\Migrator;

defined('ABSPATH') || exit;

/**
 * Article 16(m): the consumer loses the right of withdrawal for digital content
 * not supplied on a tangible medium, but only if three things happened, in this
 * order: the consumer expressly consented to supply beginning before the period
 * ended, acknowledged losing the right, and the trader confirmed both back to
 * them on a durable medium (Art. 7(2)/8(7)(b)). Supply then has to have actually
 * begun. A ticked box on its own excludes nothing.
 *
 * So this service does four jobs and each one is enforced where it counts:
 *   1. offers ONE checkbox carrying both halves of the statement, on the classic
 *      checkout and on the Blocks checkout, never pre-ticked and never required;
 *   2. records what was agreed, when, in which words, and for which products,
 *      writing nothing at all unless the ORDER really holds a downloadable item;
 *   3. prints the confirmation back on the order screen and in the order email,
 *      because without it the exclusion does not exist;
 *   4. answers, per item, whether supply has begun, which is what decides
 *      whether the exclusion actually bites (see excludedItemIds()).
 *
 * Scope is downloadable products only. A virtual product that is not
 * downloadable is a service, excluded if at all under Art. 16(a) with different
 * wording and a different test ("fully performed"), and one checkbox cannot
 * carry both statements truthfully.
 */
final class DigitalConsentService implements HasHooks
{
    /** 'yes' or 'no'. Absent means never offered, which is a third state. */
    public const META_STATE = '_withdraw_digital_consent';

    /** GMT mysql timestamp. */
    public const META_TIME = '_withdraw_digital_consent_time';

    /** The wording shown, verbatim: the setting can be edited afterwards. */
    public const META_TEXT = '_withdraw_digital_consent_text';

    /** Product/variation ids that were downloadable when the order was placed. */
    public const META_PRODUCTS = '_withdraw_digital_consent_products';

    private const FIELD_ID = 'plogins-withdraw/digital-consent';

    private const EXTENSION_NAMESPACE = 'plogins-withdraw';

    /**
     * Download counts per order, keyed order id => product id => count.
     *
     * @var array<int, array<int, int>>
     */
    private array $downloads = [];

    public function registerHooks(): void
    {
        // Module guard. With the setting off nothing registers, so a shop that
        // never asked for this sees no checkout change and no order meta.
        if (! $this->enabled()) {
            return;
        }

        // Blocks. Plugin::boot() runs on init:10, after woocommerce_init
        // (init:0) and long after after_setup_theme, which is what
        // register_checkout_field() warns about. Hooking woocommerce_init from
        // here would be dead code: it has already fired.
        $this->registerCartExtension();
        $this->registerBlocksField();

        // Classic. review_order_before_submit lives in the always-rendered
        // payment template, next to the terms box, which is where consent
        // belongs. checkout_before_terms_and_conditions only fires when a terms
        // page is configured.
        add_action('woocommerce_review_order_before_submit', [$this, 'renderClassicField']);
        add_action('woocommerce_checkout_create_order', [$this, 'captureClassic'], 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'captureBlocks'], 10, 2);

        // The Art. 8(7)(b) confirmation. Without it there is no exclusion, so it
        // is part of the feature, not decoration.
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderOrderNotice']);
        add_action('woocommerce_email_order_meta', [$this, 'renderEmailNotice'], 10, 3);
    }

    /* --------------------------------------------------------------------- */
    /* Settings                                                              */
    /* --------------------------------------------------------------------- */

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $defaults = require WITHDRAW_DIR . 'config/defaults.php';
        $stored   = get_option(Migrator::OPTION_SETTINGS, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function enabled(): bool
    {
        return ! empty($this->settings()['digital_consent']);
    }

    /**
     * The two halves of Art. 16(m) in one sentence: the request to begin supply
     * during the withdrawal period (point (i)) and the acknowledgement that the
     * right is thereby lost (point (ii)).
     *
     * Static so the settings screen can print the exact sentence a merchant is
     * adding wording to, without a second copy of it drifting out of sync.
     */
    public static function statutoryConsentText(): string
    {
        return __('I ask you to begin supplying the digital content immediately and I acknowledge that I lose my right of withdrawal once the supply has begun.', 'plogins-withdraw');
    }

    /**
     * The wording actually shown, and stored with the order.
     *
     * The shop may add wording of its own, never replace the sentence above.
     * This plugin drops items from the withdrawal form on the strength of the
     * acknowledgement in it, so a shop that reworded the box down to "I agree to
     * immediate delivery" would be taking away a right nobody gave up: point
     * (ii) would be missing and the exclusion would not exist. Translating the
     * sentence is the translator's job, through the text domain, not a per-shop
     * free-text field.
     */
    public function consentText(): string
    {
        $intro = trim((string) ($this->settings()['digital_consent_intro'] ?? ''));

        return $intro !== ''
            ? $intro . ' ' . self::statutoryConsentText()
            : self::statutoryConsentText();
    }

    /* --------------------------------------------------------------------- */
    /* Blocks checkout                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Expose whether the cart holds a downloadable item, so the Blocks field can
     * hide itself on carts that do not.
     *
     * The document object a field rule is evaluated against has no downloadable
     * flag of its own, and needs_shipping is not a substitute: a downloadable
     * product that is not virtual still needs shipping, and so does any mixed
     * cart. cart.extensions is the only key that can carry this truthfully.
     */
    private function registerCartExtension(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data') || ! class_exists(CartSchema::class)) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => CartSchema::IDENTIFIER,
            'namespace'       => self::EXTENSION_NAMESPACE,
            'data_callback'   => [$this, 'cartExtensionData'],
            'schema_callback' => [$this, 'cartExtensionSchema'],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /** @return array<string, bool> */
    public function cartExtensionData(): array
    {
        return ['has_digital_items' => $this->cartHasDigitalItems()];
    }

    /** @return array<string, array<string, mixed>> */
    public function cartExtensionSchema(): array
    {
        return [
            'has_digital_items' => [
                'description' => __('Whether the cart contains at least one downloadable product.', 'plogins-withdraw'),
                'type'        => 'boolean',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
        ];
    }

    /**
     * Register the checkbox as an additional checkout field.
     *
     * Gated on the rule-schema class: on a WooCommerce too old to evaluate
     * hidden rules the checkbox would appear on every Blocks checkout, physical
     * carts included, which is the exact noise the rules exist to prevent.
     * Capturing nothing there is the better failure.
     */
    private function registerBlocksField(): void
    {
        if (! function_exists('woocommerce_register_additional_checkout_field') || ! class_exists(Validation::class)) {
            return;
        }

        $text = $this->consentText();

        woocommerce_register_additional_checkout_field([
            'id'       => self::FIELD_ID,
            // A PHP-registered field cannot be placed against the Place order
            // button; 'order' is the closest slot without shipping a JS build.
            'location' => 'order',
            'type'     => 'checkbox',
            'label'    => $text,
            // WooCommerce would otherwise append "(optional)" to a statutory
            // sentence, which reads as if the acknowledgement were optional.
            'optionalLabel' => $text,
            'required'      => false,
            // We print our own confirmation line, so that classic and Blocks
            // orders read identically. WooCommerce refuses to display its own
            // copy for classic orders anyway (it checks created_via).
            'show_in_order_confirmation' => false,
            'hidden'                     => $this->hiddenRules(),
        ]);
    }

    /**
     * Hide the field unless the cart is observed to hold a downloadable item.
     *
     * `required` at every level on purpose: if the extension data is missing the
     * rule fails to match and the field stays visible. A missing key must not be
     * read as "no digital items", which would silently kill the feature.
     *
     * @return array<string, mixed>
     */
    private function hiddenRules(): array
    {
        return [
            'cart' => [
                'required'   => ['extensions'],
                'properties' => [
                    'extensions' => [
                        'required'   => [self::EXTENSION_NAMESPACE],
                        'properties' => [
                            self::EXTENSION_NAMESPACE => [
                                'required'   => ['has_digital_items'],
                                'properties' => [
                                    'has_digital_items' => ['const' => false],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Store API capture. The value arrives schema-validated as a boolean.
     *
     * WooCommerce also persists its own copy at
     * `_wc_other/plogins-withdraw/digital-consent`. That copy is ignored: it is
     * unreadable for classic orders, so relying on it would make the two
     * checkouts behave differently.
     *
     * @param mixed $order
     * @param mixed $request
     */
    public function captureBlocks($order, $request): void
    {
        if (! $this->enabled() || ! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request) {
            return;
        }

        $fields = $request['additional_fields'] ?? [];
        $given  = is_array($fields) && ! empty($fields[self::FIELD_ID]);

        $this->recordConsent($order, $given);
    }

    /* --------------------------------------------------------------------- */
    /* Classic checkout                                                      */
    /* --------------------------------------------------------------------- */

    public function renderClassicField(): void
    {
        if (! $this->enabled() || ! $this->cartHasDigitalItems()) {
            return;
        }

        $this->template('checkout-consent.php', ['text' => $this->consentText()]);
    }

    /**
     * @param mixed $order
     * @param mixed $data
     */
    public function captureClassic($order, $data): void
    {
        if (! $this->enabled() || ! $order instanceof \WC_Order) {
            return;
        }

        // WC_Checkout::process_checkout() verifies the `woocommerce-process_checkout`
        // nonce before it creates the order, so by the time this hook fires the
        // request is already authenticated. Reading $_POST here is safe for that
        // reason, not because the value is harmless.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $raw   = isset($_POST['withdraw_digital_consent']) ? sanitize_text_field(wp_unslash($_POST['withdraw_digital_consent'])) : '';
        $given = $raw === '1';

        $this->recordConsent($order, $given);
    }

    /* --------------------------------------------------------------------- */
    /* Recording                                                             */
    /* --------------------------------------------------------------------- */

    /**
     * Write the consent record, or nothing.
     *
     * The server decides what the order contains, never the request. A crafted
     * POST on a physical-goods order produces no record at all, which is the
     * only reason the checkbox is worth anything as evidence.
     */
    private function recordConsent(\WC_Order $order, bool $given): void
    {
        $products = $this->downloadableProductIds($order);
        if ($products === []) {
            return;
        }

        $order->update_meta_data(self::META_STATE, $given ? 'yes' : 'no');
        $order->update_meta_data(self::META_TIME, current_time('mysql', true));
        // Verbatim: the wording is a setting and can be edited tomorrow, and
        // proof of what somebody agreed to is worthless if it renders today's
        // text.
        $order->update_meta_data(self::META_TEXT, $this->consentText());
        // Product ids, not order-item ids: on the classic checkout the order has
        // not been saved yet, so item ids do not exist. Storing which products
        // were downloadable AT PURCHASE also stops a product later flipped to
        // downloadable from retroactively removing a right the customer had.
        $order->update_meta_data(self::META_PRODUCTS, $products);

        // No save() here: both checkout paths persist the order afterwards, and
        // on the classic path it has no id yet. WooCommerce's own additional
        // fields are stored the same way.
    }

    /**
     * The consent record as stored, with the three states kept distinct.
     *
     * @return array{state:string, time:string, text:string, products:list<int>}
     */
    public function consentRecord(\WC_Order $order): array
    {
        $state = (string) $order->get_meta(self::META_STATE);
        $raw   = $order->get_meta(self::META_PRODUCTS);

        $products = [];
        if (is_array($raw)) {
            foreach ($raw as $id) {
                $products[] = (int) $id;
            }
        }

        return [
            'state'    => in_array($state, ['yes', 'no'], true) ? $state : '',
            'time'     => (string) $order->get_meta(self::META_TIME),
            'text'     => (string) $order->get_meta(self::META_TEXT),
            'products' => $products,
        ];
    }

    /* --------------------------------------------------------------------- */
    /* Confirmation back to the customer                                     */
    /* --------------------------------------------------------------------- */

    /** @param mixed $order */
    public function renderOrderNotice($order): void
    {
        if (! $this->enabled() || ! $order instanceof \WC_Order) {
            return;
        }

        $record = $this->consentRecord($order);
        if ($record['state'] !== 'yes') {
            return;
        }

        $this->template('consent-notice.php', [
            'text'  => $record['text'] !== '' ? $record['text'] : $this->consentText(),
            'given' => $this->siteTime($record['time']),
            'plain' => false,
        ]);
    }

    /**
     * @param mixed $order
     * @param mixed $sentToAdmin
     * @param mixed $plainText
     */
    public function renderEmailNotice($order, $sentToAdmin = false, $plainText = false): void
    {
        if (! $this->enabled() || ! $order instanceof \WC_Order) {
            return;
        }

        $record = $this->consentRecord($order);
        if ($record['state'] !== 'yes') {
            return;
        }

        $this->template('consent-notice.php', [
            'text'  => $record['text'] !== '' ? $record['text'] : $this->consentText(),
            'given' => $this->siteTime($record['time']),
            'plain' => (bool) $plainText,
        ]);
    }

    /** The stored GMT timestamp, formatted in the site's own timezone. */
    public function siteTime(string $gmt): string
    {
        if ($gmt === '') {
            return '';
        }

        $stamp = strtotime($gmt . ' UTC');
        if ($stamp === false) {
            return '';
        }

        // wp_date(), not date_i18n(): the stored moment is GMT and wp_date is
        // the one that converts into the site timezone rather than assuming the
        // timestamp was already offset.
        return (string) wp_date(get_option('date_format') . ' ' . get_option('time_format'), $stamp);
    }

    /* --------------------------------------------------------------------- */
    /* Has supply begun                                                      */
    /* --------------------------------------------------------------------- */

    /**
     * Whether WooCommerce has actually served this item's files.
     *
     * Read from the download permissions, so there is no SQL of our own and
     * nothing to prepare. Its blind spot is stated rather than hidden: a
     * downloadable product with no files attached, or one delivered by mail or
     * through a licence portal, logs no download and therefore always reads as
     * NOT begun, leaving the item withdrawable. Erring towards the consumer is
     * the only safe direction, wrongly denying a statutory right is the
     * expensive mistake. Shops that deliver outside WooCommerce can tell the
     * truth through the filter below.
     */
    public function supplyBegun(\WC_Order $order, \WC_Order_Item_Product $item): bool
    {
        $productId = $this->itemProductId($item);
        $begun     = $productId > 0 && ($this->downloadCounts($order)[$productId] ?? 0) > 0;

        /**
         * Filters whether supply of a digital item has begun.
         *
         * @param bool                   $begun Whether WooCommerce logged a download.
         * @param \WC_Order              $order The order the item belongs to.
         * @param \WC_Order_Item_Product $item  The line item.
         */
        return (bool) apply_filters('withdraw/digital_supply_begun', $begun, $order, $item);
    }

    /**
     * Item ids the Art. 16(m) exclusion actually covers right now.
     *
     * Consent alone excludes nothing, so all four conditions have to hold: the
     * feature is on, the customer consented, the product was downloadable when
     * they bought it and still is, and supply has begun.
     *
     * @return list<int>
     */
    public function excludedItemIds(\WC_Order $order): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $record = $this->consentRecord($order);
        if ($record['state'] !== 'yes' || $record['products'] === []) {
            return [];
        }

        $excluded = [];
        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $productId = $this->itemProductId($item);
            if ($productId < 1 || ! in_array($productId, $record['products'], true)) {
                continue;
            }

            $product = $item->get_product();
            if (! $product instanceof \WC_Product || ! $product->is_downloadable()) {
                continue;
            }

            if ($this->supplyBegun($order, $item)) {
                $excluded[] = (int) $itemId;
            }
        }

        return $excluded;
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                               */
    /* --------------------------------------------------------------------- */

    private function cartHasDigitalItems(): bool
    {
        if (! function_exists('WC') || ! WC()->cart instanceof \WC_Cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $line) {
            $product = is_array($line) ? ($line['data'] ?? null) : null;
            if ($product instanceof \WC_Product && $product->is_downloadable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Products in the order that are downloadable, as ids.
     *
     * @return list<int>
     */
    public function downloadableProductIds(\WC_Order $order): array
    {
        $ids = [];
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            if (! $product instanceof \WC_Product || ! $product->is_downloadable()) {
                continue;
            }

            $id = (int) $product->get_id();
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The id download permissions are recorded against.
     *
     * wc_downloadable_file_permission() stores $item->get_product()->get_id(),
     * which is the VARIATION id for a variable product, so a variation must not
     * be reduced to its parent here or the counts never match.
     */
    private function itemProductId(\WC_Order_Item_Product $item): int
    {
        $product = $item->get_product();

        return $product instanceof \WC_Product ? (int) $product->get_id() : 0;
    }

    /**
     * Download counts for one order, keyed by product id, read once.
     *
     * @return array<int, int>
     */
    private function downloadCounts(\WC_Order $order): array
    {
        $orderId = (int) $order->get_id();
        if (isset($this->downloads[$orderId])) {
            return $this->downloads[$orderId];
        }

        $counts = [];
        // WC_Data_Store declares none of the per-store extras itself, they
        // arrive through __call, so the lookup goes through a callable rather
        // than pretending the method is declared. load() is still used, so a
        // shop that swapped its download data store keeps its own.
        $fetch = [\WC_Data_Store::load('customer-download'), 'get_downloads'];
        /** @var mixed $permissions */
        $permissions = is_callable($fetch) ? $fetch(['order_id' => $orderId]) : [];

        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                if (! $permission instanceof \WC_Customer_Download) {
                    continue;
                }
                $productId = (int) $permission->get_product_id();
                $count     = (int) $permission->get_download_count();
                // One product can carry several files, so several permission
                // rows. Any one of them served is supply begun.
                $counts[$productId] = max($counts[$productId] ?? 0, $count);
            }
        }

        return $this->downloads[$orderId] = $counts;
    }

    /** @param array<string, mixed> $vars */
    private function template(string $file, array $vars): void
    {
        $path = WITHDRAW_DIR . 'templates/' . $file;
        if (! is_readable($path)) {
            return;
        }
        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template vars.
        require $path;
    }
}
