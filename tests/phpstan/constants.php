<?php

declare(strict_types=1);

// Constants defined in the plugin bootstrap, declared here so PHPStan resolves
// them when analysing src/ and config/ in isolation.

namespace Withdraw;

const VERSION = '0.1.0';

if (! defined('WITHDRAW_FILE')) {
    define('WITHDRAW_FILE', __FILE__);
}
if (! defined('WITHDRAW_DIR')) {
    define('WITHDRAW_DIR', __DIR__ . '/');
}
if (! defined('WITHDRAW_URL')) {
    define('WITHDRAW_URL', 'https://example.com/wp-content/plugins/withdraw/');
}
