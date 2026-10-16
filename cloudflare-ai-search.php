<?php
/**
 * Plugin Name: Cloudflare AI Search
 * Plugin URI: https://github.com/skyphusion-labs/cloudflare-ai-search
 * Description: Search the site in ordinary language with Cloudflare AI Search. Connect it from Settings, then AI Search.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Skyphusion
 * Author URI: https://skyphusion.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloudflare-ai-search
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLOUDFLARE_AI_SEARCH_VERSION', '0.1.0');
define('CLOUDFLARE_AI_SEARCH_FILE', __FILE__);

require_once __DIR__ . '/includes/endpoint.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/frontend.php';
require_once __DIR__ . '/includes/cli.php';

function cloudflare_ai_search_activate()
{
    if (get_option('cloudflare_ai_search_settings', null) === null) {
        add_option('cloudflare_ai_search_settings', cloudflare_ai_search_defaults());
    }
}
register_activation_hook(__FILE__, 'cloudflare_ai_search_activate');
