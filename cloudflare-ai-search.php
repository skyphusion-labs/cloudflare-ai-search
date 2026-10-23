<?php
/**
 * Plugin Name: Skyphusion AI for Cloudflare
 * Plugin URI: https://github.com/skyphusion-labs/skyphusion-ai
 * Description: Workers AI, AI Gateway, and AI Search for WordPress and WooCommerce. Connect models from Settings, then Connectors, and search from Settings, then Skyphusion AI.
 * Version: 0.3.3
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Conrad Rockenhaus (skyphusion)
 * Author URI: https://skyphusion.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: skyphusion-ai
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLOUDFLARE_AI_SEARCH_VERSION', '0.3.3');
define('CLOUDFLARE_AI_SEARCH_FILE', __FILE__);

require_once __DIR__ . '/includes/endpoint.php';
require_once __DIR__ . '/includes/routes.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/stock.php';
require_once __DIR__ . '/includes/frontend.php';
require_once __DIR__ . '/includes/cli.php';
require_once __DIR__ . '/includes/provider.php';

function cloudflare_ai_search_activate()
{
    if (get_option('cloudflare_ai_search_settings', null) === null) {
        add_option('cloudflare_ai_search_settings', cloudflare_ai_search_defaults());
    }
}
register_activation_hook(__FILE__, 'cloudflare_ai_search_activate');
