<?php

declare(strict_types=1);

namespace Withdraw;

defined('ABSPATH') || exit;

use Withdraw\Admin\RequestsAdmin;
use Withdraw\Admin\Settings;
use Withdraw\Frontend\MyAccount;
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

    $c->singleton(WithdrawalService::class, static fn (Container $c): WithdrawalService => new WithdrawalService(
        $c->get(RequestRepository::class),
    ));
    $c->singleton(MyAccount::class, static fn (): MyAccount => new MyAccount());

    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
        $c->singleton(RequestsAdmin::class, static fn (Container $c): RequestsAdmin => new RequestsAdmin(
            $c->get(RequestRepository::class),
        ));
    }
};
