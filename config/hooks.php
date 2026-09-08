<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

use Withdraw\Admin\OrderConsentPanel;
use Withdraw\Admin\RequestsAdmin;
use Withdraw\Admin\Settings;
use Withdraw\Frontend\MyAccount;
use Withdraw\Frontend\WithdrawLink;
use Withdraw\Service\DigitalConsentService;
use Withdraw\Service\EmailService;
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
        // In BOTH branches on purpose. The declaration is submitted on the
        // storefront, which is not is_admin(), and the status change posts to
        // admin-post.php, which is. A service registered in one branch would
        // leave the other half of the emails with nothing listening.
        EmailService::class,
        \Withdraw\Service\WithdrawPrivacyService::class,
        // Also in wp-admin: the checkout block editor asks for the registered
        // additional fields, so a field registered only on the front end would
        // be missing from the editor's own view of the checkout.
        DigitalConsentService::class,
        OrderConsentPanel::class,
        Settings::class,
        RequestsAdmin::class,
        WithdrawLink::class,
    ]
    : [
        WithdrawalService::class,
        EmailService::class,
        \Withdraw\Service\WithdrawPrivacyService::class,
        // Store API requests are not is_admin(), so the Blocks capture lives
        // in this branch as well.
        DigitalConsentService::class,
        MyAccount::class,
        WithdrawLink::class,
    ];
