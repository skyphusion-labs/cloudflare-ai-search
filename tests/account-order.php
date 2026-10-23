<?php
/**
 * Environment wins over the PHP constant. Run by tests/provider-test.php.
 *
 * ABSPATH is defined here so includes/routes.php can keep its direct-access
 * guard. This is not a WordPress bootstrap. Do not remove the define.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require $argv[1];

define('CLOUDFLARE_ACCOUNT_ID', 'cccccccccccccccccccccccccccccccc');
putenv('CLOUDFLARE_ACCOUNT_ID=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$credentials = cloudflare_ai_provider_resolve_credentials('plain-token', '');
echo $credentials['account_id'];
