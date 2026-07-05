<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

use Withdraw\Admin\RequestsAdmin;
use Withdraw\Admin\Settings;
use Withdraw\Frontend\MyAccount;
use Withdraw\Service\WithdrawalService;

/**
 * Services whose registerHooks() runs during boot. Each implements
 * Withdraw\Contract\HasHooks.
 *
 * @return array<class-string>
 */
return is_admin()
    ? [
        WithdrawalService::class, // shortcode + POST handling also work in admin-preview contexts
        Settings::class,
        RequestsAdmin::class,
    ]
    : [
        WithdrawalService::class,
        MyAccount::class,
    ];
