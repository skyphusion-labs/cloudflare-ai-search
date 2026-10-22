<?php
/**
 * Environment wins over the PHP constant. Run by tests/provider-test.php.
 */

require $argv[1];

define('CLOUDFLARE_ACCOUNT_ID', 'cccccccccccccccccccccccccccccccc');
putenv('CLOUDFLARE_ACCOUNT_ID=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$credentials = cloudflare_ai_provider_resolve_credentials('plain-token', '');
echo $credentials['account_id'];
