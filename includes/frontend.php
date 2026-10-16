<?php
/**
 * Storefront markup.
 */

if (!defined('ABSPATH')) {
    exit;
}

function cloudflare_ai_search_ready()
{
    if (is_admin()) {
        return false;
    }
    $settings = cloudflare_ai_search_settings();
    if ($settings['endpoint'] === '') {
        return false;
    }
    return $settings['search_bar'] || $settings['modal'] || $settings['chat'];
}

function cloudflare_ai_search_enqueue()
{
    if (!cloudflare_ai_search_ready()) {
        return;
    }
    $settings = cloudflare_ai_search_settings();
    $src = cloudflare_ai_search_snippet_src($settings['endpoint']);
    if ($src === '') {
        return;
    }

    wp_enqueue_style(
        'cloudflare-ai-search',
        plugins_url('assets/search.css', CLOUDFLARE_AI_SEARCH_FILE),
        array(),
        CLOUDFLARE_AI_SEARCH_VERSION
    );
    wp_enqueue_script(
        'cloudflare-ai-search',
        $src,
        array(),
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'cloudflare_ai_search_enqueue');

function cloudflare_ai_search_script_tag($tag, $handle, $src)
{
    if ($handle !== 'cloudflare-ai-search') {
        return $tag;
    }
    return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
}
add_filter('script_loader_tag', 'cloudflare_ai_search_script_tag', 10, 3);

function cloudflare_ai_search_quiet_page()
{
    $settings = cloudflare_ai_search_settings();
    if (!$settings['hide_checkout']) {
        return false;
    }
    if (function_exists('is_cart') && is_cart()) {
        return true;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }
    return false;
}

function cloudflare_ai_search_component($tag, $settings)
{
    $allowed = array('search-bar-snippet', 'search-modal-snippet', 'chat-bubble-snippet');
    if (!in_array($tag, $allowed, true)) {
        return '';
    }

    $style = '--search-snippet-primary-color:' . $settings['color'];
    return sprintf(
        '<%1$s api-url="%2$s" placeholder="%3$s" max-results="%4$d" theme="%5$s" hide-branding="%6$s" style="%7$s"></%1$s>',
        $tag,
        esc_url($settings['endpoint']),
        esc_attr($settings['placeholder']),
        (int) $settings['max_results'],
        esc_attr($settings['theme']),
        $settings['hide_branding'] ? 'true' : 'false',
        esc_attr($style)
    );
}

function cloudflare_ai_search_render_bar()
{
    if (!cloudflare_ai_search_ready() || cloudflare_ai_search_quiet_page()) {
        return;
    }
    $settings = cloudflare_ai_search_settings();
    if (!$settings['search_bar'] || $settings['placement'] !== 'top') {
        return;
    }
    echo '<div class="cloudflare-ai-search-bar">';
    echo cloudflare_ai_search_component('search-bar-snippet', $settings);
    echo '</div>';
}
add_action('wp_body_open', 'cloudflare_ai_search_render_bar', 5);

function cloudflare_ai_search_render_footer()
{
    if (!cloudflare_ai_search_ready()) {
        return;
    }
    $settings = cloudflare_ai_search_settings();
    if ($settings['modal']) {
        echo cloudflare_ai_search_component('search-modal-snippet', $settings);
    }
    if ($settings['chat'] && !cloudflare_ai_search_quiet_page()) {
        echo cloudflare_ai_search_component('chat-bubble-snippet', $settings);
    }
}
add_action('wp_footer', 'cloudflare_ai_search_render_footer', 30);

function cloudflare_ai_search_shortcode()
{
    if (is_admin() && !wp_doing_ajax()) {
        return '';
    }
    $settings = cloudflare_ai_search_settings();
    if ($settings['endpoint'] === '' || !$settings['search_bar']) {
        return '';
    }
    return '<div class="cloudflare-ai-search-bar">' . cloudflare_ai_search_component('search-bar-snippet', $settings) . '</div>';
}
add_shortcode('cloudflare_ai_search', 'cloudflare_ai_search_shortcode');
