<?php

declare(strict_types=1);

namespace Withdraw;

use Withdraw\Contract\HasHooks;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    private Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
        (require WITHDRAW_DIR . 'config/services.php')($this->container);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Version-gated, per-site: self-heals schema on WordPress Multisite where
        // activation runs only on the main site (each site seeds on first boot).
        $this->container->get(Migrator::class)->maybeMigrate();

        $this->loadTextDomain();

        /** @var array<class-string<HasHooks>> $hooks */
        $hooks = require WITHDRAW_DIR . 'config/hooks.php';
        foreach ($hooks as $id) {
            $service = $this->container->get($id);
            if ($service instanceof HasHooks) {
                $service->registerHooks();
            }
        }

        /**
         * Fires after Plogins Withdraw has fully booted. PRO add-ons hook here.
         *
         * @param Plugin $plugin The booted plugin instance.
         */
        do_action('withdraw/booted', $this);
    }

    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'plogins-withdraw',
            false,
            dirname(plugin_basename(WITHDRAW_FILE)) . '/languages',
        );
    }
}
