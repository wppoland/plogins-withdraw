<?php
/**
 * Elementor widget: Withdrawal Form.
 *
 * A thin wrapper around the [withdraw_form] shortcode so the EU
 * right-of-withdrawal form can be placed with the Elementor editor. Kept
 * deliberately minimal (renders the shortcode) so a future migration to
 * Elementor v4 atomic widgets is localized to this class. Loaded only from the
 * `elementor/widgets/register` hook, so the `\Elementor\Widget_Base` base class
 * is guaranteed to exist here.
 *
 * @package Withdraw\Elementor
 */

declare(strict_types=1);

namespace Withdraw\Elementor;

defined('ABSPATH') || exit;

use Elementor\Widget_Base;

/**
 * Withdrawal Form Elementor widget.
 */
final class WithdrawFormWidget extends Widget_Base
{
    /**
     * Widget machine name.
     */
    public function get_name(): string
    {
        return 'withdraw_form';
    }

    /**
     * Widget label shown in the editor.
     */
    public function get_title(): string
    {
        return esc_html__('Withdrawal Form', 'plogins-withdraw');
    }

    /**
     * Editor panel icon.
     */
    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    /**
     * Editor panel categories.
     *
     * @return string[]
     */
    public function get_categories(): array
    {
        return ['woocommerce-elements', 'general'];
    }

    /**
     * Search keywords in the editor.
     *
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['withdrawal', 'withdraw', 'refund', 'return', 'form', 'woocommerce'];
    }

    /**
     * Register the editor controls.
     */
    protected function register_controls(): void
    {
        $this->start_controls_section(
            'content',
            ['label' => esc_html__('Withdrawal form', 'plogins-withdraw')]
        );

        $this->add_control(
            'info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw'  => esc_html__('Renders the EU right-of-withdrawal form. Customers can look up an order and submit a full or partial withdrawal request.', 'plogins-withdraw'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget on the front end and in the editor preview.
     */
    protected function render(): void
    {
        echo do_shortcode('[withdraw_form]');
    }
}
