<?php
/**
 * wp cloudflare-ai-search. Same settings as Settings, then Skyphusion AI.
 */

if (!defined('ABSPATH')) {
    exit;
}

function cloudflare_ai_search_cli_truthy($value)
{
    $value = strtolower(trim((string) $value));
    return in_array($value, array('1', 'true', 'yes', 'on'), true);
}

class Cloudflare_AI_Search_CLI
{
    /**
     * Print the saved settings.
     *
     * ## EXAMPLES
     *
     *     wp cloudflare-ai-search status
     *
     * @param array $args
     * @param array $assoc
     */
    public function status($args, $assoc)
    {
        $settings = cloudflare_ai_search_settings();
        foreach ($settings as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            WP_CLI::log($key . '=' . $value);
        }
    }

    /**
     * Change settings. Omitted flags stay as they are.
     *
     * ## OPTIONS
     *
     * [--endpoint=<url>]
     * : Public address, https://something.search.ai.cloudflare.com/
     *
     * [--search-bar=<0|1>]
     * : Show the search box.
     *
     * [--modal=<0|1>]
     * : Open search on Ctrl+K or Command+K.
     *
     * [--chat=<0|1>]
     * : Show the chat button. This uses more of the Cloudflare allowance.
     *
     * [--placement=<top|shortcode>]
     * : top puts the box on every page. shortcode waits for [cloudflare_ai_search].
     *
     * [--hide-checkout=<0|1>]
     * : Hide the box and the chat button on the cart and checkout.
     *
     * [--placeholder=<text>]
     * : Text shown in the empty search box.
     *
     * [--color=<hex>]
     * : Accent color, such as #1B3A32. Passed through even though WP-CLI reserves a global color flag.
     *
     * [--theme=<auto|light|dark>]
     * : auto, light, or dark.
     *
     * [--max-results=<n>]
     * : From 1 to 20.
     *
     * [--hide-branding=<0|1>]
     * : Hide the Cloudflare name on the search box.
     *
     * [--account-id=<id>]
     * : Cloudflare account ID, 32 hex characters. Not the API token.
     *
     * [--gateway-id=<slug>]
     * : Optional AI Gateway slug. Blank calls Workers AI directly.
     *
     * [--lead-search=<0|1>]
     * : Print an AI Search answer above WordPress and WooCommerce search results.
     *
     * ## EXAMPLES
     *
     *     wp cloudflare-ai-search set --endpoint=https://abc.search.ai.cloudflare.com/ --color=#1B3A32 --chat=0
     *     wp cloudflare-ai-search set --account-id=0123456789abcdef0123456789abcdef --gateway-id=store --lead-search=0
     *
     * @param array $args
     * @param array $assoc
     */
    public function set($args, $assoc)
    {
        $current = cloudflare_ai_search_settings();
        $input = array(
            'endpoint' => $current['endpoint'],
            'search_bar' => $current['search_bar'] ? '1' : '',
            'modal' => $current['modal'] ? '1' : '',
            'chat' => $current['chat'] ? '1' : '',
            'hide_checkout' => $current['hide_checkout'] ? '1' : '',
            'hide_branding' => $current['hide_branding'] ? '1' : '',
            'placement' => $current['placement'],
            'placeholder' => $current['placeholder'],
            'color' => $current['color'],
            'theme' => $current['theme'],
            'max_results' => (string) $current['max_results'],
            'account_id' => $current['account_id'],
            'gateway_id' => $current['gateway_id'],
            'lead_search' => $current['lead_search'] ? '1' : '',
        );

        $map = array(
            'endpoint' => 'endpoint',
            'search-bar' => 'search_bar',
            'modal' => 'modal',
            'chat' => 'chat',
            'placement' => 'placement',
            'hide-checkout' => 'hide_checkout',
            'placeholder' => 'placeholder',
            'color' => 'color',
            'theme' => 'theme',
            'max-results' => 'max_results',
            'hide-branding' => 'hide_branding',
            'account-id' => 'account_id',
            'gateway-id' => 'gateway_id',
            'lead-search' => 'lead_search',
        );
        $bools = array('search_bar', 'modal', 'chat', 'hide_checkout', 'hide_branding', 'lead_search');

        // WP-CLI treats --color as its own global flag and drops --color=<hex>
        // before this command sees it. The raw argv still has the value.
        if (!array_key_exists('color', $assoc) && isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- array_keys only supplies indexes. Each value is unslashed and sanitized below.
            foreach (array_keys($_SERVER['argv']) as $index) {
                if (!isset($_SERVER['argv'][$index])) {
                    continue;
                }
                $arg = sanitize_text_field(wp_unslash($_SERVER['argv'][$index]));
                if (strncmp($arg, '--color=', 8) === 0) {
                    $assoc['color'] = substr($arg, 8);
                    break;
                }
            }
        }

        foreach ($map as $flag => $key) {
            if (!array_key_exists($flag, $assoc)) {
                continue;
            }
            if (in_array($key, $bools, true)) {
                $input[$key] = cloudflare_ai_search_cli_truthy($assoc[$flag]) ? '1' : '';
            } else {
                $input[$key] = $assoc[$flag];
            }
        }

        if (array_key_exists('endpoint', $assoc)) {
            $typed = trim((string) $assoc['endpoint']);
            if ($typed !== '' && cloudflare_ai_search_normalize_endpoint($typed) === '') {
                WP_CLI::error(__('That address has to be an https link ending in search.ai.cloudflare.com.', 'skyphusion-ai')); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- names the required host. It does not load a file.
            }
        }
        if (array_key_exists('color', $assoc)) {
            // FAILS CLOSED. This used to read
            // `array_key_exists(...) && function_exists('sanitize_hex_color')`,
            // which skipped the whole check when the validator was missing and
            // let the command report "Saved." having changed nothing. A
            // validation that disappears with its validator permits exactly the
            // case nobody tested, so absence is an error rather than a pass.
            if (!function_exists('sanitize_hex_color')) {
                WP_CLI::error(__('Cannot validate the color: sanitize_hex_color() is unavailable.', 'skyphusion-ai'));
            }
            if (!sanitize_hex_color((string) $assoc['color'])) {
                WP_CLI::error(__('Color has to be a hex color such as #1B3A32.', 'skyphusion-ai'));
            }
        }
        if (array_key_exists('account-id', $assoc)) {
            $typed = trim((string) $assoc['account-id']);
            if ($typed !== '' && cloudflare_ai_provider_account_id($typed) === '') {
                WP_CLI::error(__('Account ID has to be 32 hex characters.', 'skyphusion-ai'));
            }
        }
        if (array_key_exists('gateway-id', $assoc)) {
            $typed = trim((string) $assoc['gateway-id']);
            if ($typed !== '' && cloudflare_ai_provider_gateway_id($typed) === '') {
                WP_CLI::error(__('Gateway ID has to be a slug of letters, numbers, hyphens, or underscores.', 'skyphusion-ai'));
            }
        }

        $clean = cloudflare_ai_search_sanitize($input);
        update_option('cloudflare_ai_search_settings', $clean);
        WP_CLI::success(__('Saved. Settings, then Skyphusion AI, shows the same values.', 'skyphusion-ai'));
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cloudflare-ai-search', 'Cloudflare_AI_Search_CLI');
}
