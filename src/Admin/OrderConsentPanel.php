<?php

declare(strict_types=1);

namespace Withdraw\Admin;

use Withdraw\Contract\HasHooks;
use Withdraw\Service\DigitalConsentService;

defined('ABSPATH') || exit;

/**
 * The Article 16(m) record on the order screen.
 *
 * A tick on its own does not exclude anything, so this panel does not stop at
 * "consent given". It also shows whether supply has actually begun for each
 * covered product, which is what decides whether the exclusion currently bites.
 * A merchant refusing a withdrawal needs to see that before refusing it.
 */
final class OrderConsentPanel implements HasHooks
{
    public function __construct(private readonly DigitalConsentService $consent)
    {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'render']);
    }

    /** @param mixed $order */
    public function render($order): void
    {
        if (! $this->consent->enabled() || ! $order instanceof \WC_Order) {
            return;
        }

        if (! current_user_can('edit_shop_orders')) {
            return;
        }

        // Only orders this could ever apply to. Printing "Not offered" on every
        // physical order would bury the cases that matter, but an order that DID
        // hold digital items and carries no record is worth saying out loud: it
        // predates the setting, or the checkout never showed the box.
        $record = $this->consent->consentRecord($order);
        if ($record['state'] === '' && $this->consent->downloadableProductIds($order) === []) {
            return;
        }

        $label = match ($record['state']) {
            'yes'   => __('Given', 'plogins-withdraw'),
            'no'    => __('Declined', 'plogins-withdraw'),
            default => __('Not offered', 'plogins-withdraw'),
        };
        $when = $this->consent->siteTime($record['time']);
        ?>
        <div class="withdraw-order-consent">
            <h3><?php echo esc_html__('Right of withdrawal: digital content', 'plogins-withdraw'); ?></h3>
            <p>
                <strong><?php echo esc_html__('Consent:', 'plogins-withdraw'); ?></strong>
                <?php echo esc_html($label); ?>
                <?php if ($when !== '') : ?>
                    &middot; <?php echo esc_html($when); ?>
                <?php endif; ?>
            </p>

            <?php if ($record['text'] !== '') : ?>
                <p class="description"><?php echo esc_html($record['text']); ?></p>
            <?php endif; ?>

            <?php
            $rows = $this->rows($order, $record['products']);
            if ($rows !== []) :
                ?>
                <ul class="withdraw-order-consent__items">
                    <?php foreach ($rows as $row) : ?>
                        <li>
                            <?php echo esc_html($row['name']); ?>:
                            <?php
                            echo esc_html(
                                $row['begun']
                                    ? __('downloaded', 'plogins-withdraw')
                                    : __('not downloaded yet', 'plogins-withdraw'),
                            );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="description">
                    <?php echo esc_html__('The exclusion only applies to items whose supply has begun. WooCommerce cannot see downloads it did not serve itself, so files delivered by email or through an external portal always read as not downloaded.', 'plogins-withdraw'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * The covered products, with whether supply has begun for each.
     *
     * @param list<int> $products
     * @return list<array{name:string, begun:bool}>
     */
    private function rows(\WC_Order $order, array $products): array
    {
        $rows = [];
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            $id      = $product instanceof \WC_Product ? (int) $product->get_id() : 0;

            // The stored list is what was downloadable at purchase time. Fall
            // back to what is downloadable now for orders placed before the
            // record existed, so the panel still says something useful.
            $covered = $products === []
                ? ($product instanceof \WC_Product && $product->is_downloadable())
                : in_array($id, $products, true);

            if (! $covered) {
                continue;
            }

            $rows[] = [
                'name'  => (string) $item->get_name(),
                'begun' => $this->consent->supplyBegun($order, $item),
            ];
        }

        return $rows;
    }
}
