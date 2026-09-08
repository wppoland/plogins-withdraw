<?php

declare(strict_types=1);

namespace Withdraw;

defined('ABSPATH') || exit;

use Withdraw\Admin\OrderConsentPanel;
use Withdraw\Admin\RequestsAdmin;
use Withdraw\Admin\Settings;
use Withdraw\Frontend\MyAccount;
use Withdraw\Frontend\WithdrawLink;
use Withdraw\Service\AccessLink;
use Withdraw\Service\DigitalConsentService;
use Withdraw\Service\RequestRepository;
use Withdraw\Service\WithdrawalService;

/**
 * Bind services into the container. Migrator is resolved directly by Plugin.
 *
 * @return callable(Container): void
 */
return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());
    $c->singleton(RequestRepository::class, static fn (): RequestRepository => new RequestRepository());
    // Registers no hooks of its own, so it is not listed in config/hooks.php.
    $c->singleton(AccessLink::class, static fn (): AccessLink => new AccessLink());

    $c->singleton(DigitalConsentService::class, static fn (): DigitalConsentService => new DigitalConsentService());

    $c->singleton(WithdrawalService::class, static fn (Container $c): WithdrawalService => new WithdrawalService(
        $c->get(RequestRepository::class),
        $c->get(AccessLink::class),
        $c->get(DigitalConsentService::class),
    ));
    $c->singleton(MyAccount::class, static fn (): MyAccount => new MyAccount());
    $c->singleton(WithdrawLink::class, static fn (): WithdrawLink => new WithdrawLink());
    $c->singleton(\Withdraw\Service\WithdrawPrivacyService::class, static fn (Container $c): \Withdraw\Service\WithdrawPrivacyService => new \Withdraw\Service\WithdrawPrivacyService(
        $c->get(RequestRepository::class),
    ));

    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
        $c->singleton(RequestsAdmin::class, static fn (Container $c): RequestsAdmin => new RequestsAdmin(
            $c->get(RequestRepository::class),
        ));
        $c->singleton(OrderConsentPanel::class, static fn (Container $c): OrderConsentPanel => new OrderConsentPanel(
            $c->get(DigitalConsentService::class),
        ));
    }
};
