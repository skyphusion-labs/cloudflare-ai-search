<?php
/**
 * wp cloudflare-ai-search. Same settings as Settings, then AI Search.
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
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
     * ## EXAMPLES
     *
     *     wp cloudflare-ai-search set --endpoint=https://abc.search.ai.cloudflare.com/ --color=#1B3A32 --chat=0
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
        );
        $bools = array('search_bar', 'modal', 'chat', 'hide_checkout', 'hide_branding');

        // WP-CLI treats --color as its own global flag and drops --color=<hex>
        // before this command sees it. The raw argv still has the value.
        if (!array_key_exists('color', $assoc)) {
            $argv = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : array();
            foreach ($argv as $arg) {
                $arg = (string) $arg;
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
                WP_CLI::error('That address has to be an https link ending in search.ai.cloudflare.com.');
            }
        }
        if (array_key_exists('color', $assoc) && function_exists('sanitize_hex_color')) {
            if (!sanitize_hex_color((string) $assoc['color'])) {
                WP_CLI::error('Color has to be a hex color such as #1B3A32.');
            }
        }

        $clean = cloudflare_ai_search_sanitize($input);
        update_option('cloudflare_ai_search_settings', $clean);
        WP_CLI::success('Saved. Settings, then AI Search, shows the same values.');
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cloudflare-ai-search', 'Cloudflare_AI_Search_CLI');
}
