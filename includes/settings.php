<?php
/**
 * Settings screen. Settings → AI Search.
 */

if (!defined('ABSPATH')) {
    exit;
}

function cloudflare_ai_search_defaults()
{
    return array(
        'endpoint' => '',
        'placement' => 'top',
        'search_bar' => true,
        'modal' => true,
        'chat' => false,
        'hide_checkout' => true,
        'placeholder' => 'Search',
        'color' => '#1d2327',
        'theme' => 'auto',
        'max_results' => 8,
        'hide_branding' => false,
    );
}

function cloudflare_ai_search_settings()
{
    $stored = get_option('cloudflare_ai_search_settings', array());
    if (!is_array($stored)) {
        $stored = array();
    }

    $settings = array_merge(cloudflare_ai_search_defaults(), $stored);
    $settings['endpoint'] = cloudflare_ai_search_normalize_endpoint($settings['endpoint']);
    $settings['search_bar'] = (bool) $settings['search_bar'];
    $settings['modal'] = (bool) $settings['modal'];
    $settings['chat'] = (bool) $settings['chat'];
    $settings['hide_checkout'] = (bool) $settings['hide_checkout'];
    $settings['hide_branding'] = (bool) $settings['hide_branding'];
    $settings['max_results'] = (int) $settings['max_results'];
    if ($settings['placement'] !== 'shortcode') {
        $settings['placement'] = 'top';
    }
    if (!in_array($settings['theme'], array('auto', 'light', 'dark'), true)) {
        $settings['theme'] = 'auto';
    }
    if ($settings['max_results'] < 1) {
        $settings['max_results'] = 1;
    }
    if ($settings['max_results'] > 20) {
        $settings['max_results'] = 20;
    }

    return $settings;
}

function cloudflare_ai_search_register_settings()
{
    register_setting(
        'cloudflare_ai_search',
        'cloudflare_ai_search_settings',
        array(
            'type' => 'array',
            'sanitize_callback' => 'cloudflare_ai_search_sanitize',
            'default' => cloudflare_ai_search_defaults(),
        )
    );

    add_settings_section(
        'connection',
        'Connection',
        'cloudflare_ai_search_section_connection',
        'cloudflare-ai-search'
    );
    add_settings_field(
        'endpoint',
        'Cloudflare address',
        'cloudflare_ai_search_field_endpoint',
        'cloudflare-ai-search',
        'connection'
    );

    add_settings_section(
        'display',
        'What visitors see',
        'cloudflare_ai_search_section_display',
        'cloudflare-ai-search'
    );
    add_settings_field('search_bar', 'Search box', 'cloudflare_ai_search_field_search_bar', 'cloudflare-ai-search', 'display');
    add_settings_field('modal', 'Keyboard search', 'cloudflare_ai_search_field_modal', 'cloudflare-ai-search', 'display');
    add_settings_field('chat', 'Chat button', 'cloudflare_ai_search_field_chat', 'cloudflare-ai-search', 'display');
    add_settings_field('placement', 'Where the search box goes', 'cloudflare_ai_search_field_placement', 'cloudflare-ai-search', 'display');
    add_settings_field('hide_checkout', 'Cart and checkout', 'cloudflare_ai_search_field_hide_checkout', 'cloudflare-ai-search', 'display');

    add_settings_section(
        'appearance',
        'Look',
        'cloudflare_ai_search_section_appearance',
        'cloudflare-ai-search'
    );
    add_settings_field('placeholder', 'Placeholder', 'cloudflare_ai_search_field_placeholder', 'cloudflare-ai-search', 'appearance');
    add_settings_field('color', 'Accent color', 'cloudflare_ai_search_field_color', 'cloudflare-ai-search', 'appearance');
    add_settings_field('theme', 'Light or dark', 'cloudflare_ai_search_field_theme', 'cloudflare-ai-search', 'appearance');
    add_settings_field('max_results', 'How many results', 'cloudflare_ai_search_field_max_results', 'cloudflare-ai-search', 'appearance');
    add_settings_field('hide_branding', 'Cloudflare name', 'cloudflare_ai_search_field_hide_branding', 'cloudflare-ai-search', 'appearance');
}
add_action('admin_init', 'cloudflare_ai_search_register_settings');

function cloudflare_ai_search_sanitize($input)
{
    $old = cloudflare_ai_search_settings();
    $clean = cloudflare_ai_search_defaults();
    if (!is_array($input)) {
        return $old;
    }

    $typed = isset($input['endpoint']) ? trim((string) $input['endpoint']) : '';
    $endpoint = cloudflare_ai_search_normalize_endpoint($typed);
    if ($typed !== '' && $endpoint === '') {
        if (function_exists('add_settings_error')) {
            add_settings_error(
                'cloudflare_ai_search',
                'endpoint',
                'That address has to be an https link ending in search.ai.cloudflare.com. The previous address was kept.'
            );
        }
        $endpoint = $old['endpoint'];
    }
    $clean['endpoint'] = $endpoint;

    $clean['search_bar'] = !empty($input['search_bar']);
    $clean['modal'] = !empty($input['modal']);
    $clean['chat'] = !empty($input['chat']);
    $clean['hide_checkout'] = !empty($input['hide_checkout']);
    $clean['hide_branding'] = !empty($input['hide_branding']);
    $clean['placement'] = (isset($input['placement']) && $input['placement'] === 'shortcode') ? 'shortcode' : 'top';

    $theme = isset($input['theme']) ? (string) $input['theme'] : 'auto';
    $clean['theme'] = in_array($theme, array('auto', 'light', 'dark'), true) ? $theme : 'auto';

    $placeholder = isset($input['placeholder']) ? sanitize_text_field((string) $input['placeholder']) : '';
    if ($placeholder === '') {
        $placeholder = 'Search';
    }
    if (function_exists('mb_substr')) {
        $placeholder = mb_substr($placeholder, 0, 80);
    } else {
        $placeholder = substr($placeholder, 0, 80);
    }
    $clean['placeholder'] = $placeholder;

    $color = sanitize_hex_color(isset($input['color']) ? (string) $input['color'] : '');
    $clean['color'] = $color ? $color : $old['color'];

    $max = isset($input['max_results']) ? (int) $input['max_results'] : 8;
    if ($max < 1) {
        $max = 1;
    }
    if ($max > 20) {
        $max = 20;
    }
    $clean['max_results'] = $max;

    return $clean;
}

function cloudflare_ai_search_section_connection()
{
    echo '<p>Paste the public address from Cloudflare. This site does not need an API token.</p>';
    echo '<ol>';
    echo '<li>In the Cloudflare dashboard, open AI Search and select the instance for this site.</li>';
    echo '<li>Open Settings, then Public endpoint, and turn the public endpoint on.</li>';
    echo '<li>Copy the address. It looks like <code>https://something.search.ai.cloudflare.com/</code>.</li>';
    echo '</ol>';
}

function cloudflare_ai_search_section_display()
{
    echo '<p>Choose what shows up for visitors. Save, then look at the front of the site.</p>';
}

function cloudflare_ai_search_section_appearance()
{
    echo '<p>These match the search box to the rest of the site.</p>';
}

function cloudflare_ai_search_field_endpoint()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="url" class="regular-text code" name="cloudflare_ai_search_settings[endpoint]" value="%s" placeholder="https://example.search.ai.cloudflare.com/" />',
        esc_attr($settings['endpoint'])
    );
    echo '<p class="description">Leave this blank to hide search until you are ready.</p>';
}

function cloudflare_ai_search_field_search_bar()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[search_bar]" value="1" ' . checked($settings['search_bar'], true, false) . ' /> Show a search box</label>';
    echo '<p class="description">Lists matching pages as someone types.</p>';
}

function cloudflare_ai_search_field_modal()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[modal]" value="1" ' . checked($settings['modal'], true, false) . ' /> Open search when someone presses Ctrl+K or Command+K</label>';
}

function cloudflare_ai_search_field_chat()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[chat]" value="1" ' . checked($settings['chat'], true, false) . ' /> Show a chat button in the corner</label>';
    echo '<p class="description">The chat button answers in a sentence and uses more of the Cloudflare allowance. The search box only lists pages.</p>';
}

function cloudflare_ai_search_field_placement()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="radio" name="cloudflare_ai_search_settings[placement]" value="top" ' . checked($settings['placement'], 'top', false) . ' /> Top of every page</label><br />';
    echo '<label><input type="radio" name="cloudflare_ai_search_settings[placement]" value="shortcode" ' . checked($settings['placement'], 'shortcode', false) . ' /> Only where I place the shortcode</label>';
    echo '<p class="description">Shortcode: <code>[cloudflare_ai_search]</code></p>';
}

function cloudflare_ai_search_field_hide_checkout()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[hide_checkout]" value="1" ' . checked($settings['hide_checkout'], true, false) . ' /> Hide the search box and the chat button on the cart and checkout</label>';
}

function cloudflare_ai_search_field_placeholder()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="text" class="regular-text" name="cloudflare_ai_search_settings[placeholder]" value="%s" maxlength="80" />',
        esc_attr($settings['placeholder'])
    );
}

function cloudflare_ai_search_field_color()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="text" class="cloudflare-ai-search-color" name="cloudflare_ai_search_settings[color]" value="%s" />',
        esc_attr($settings['color'])
    );
}

function cloudflare_ai_search_field_theme()
{
    $settings = cloudflare_ai_search_settings();
    echo '<select name="cloudflare_ai_search_settings[theme]">';
    $choices = array(
        'auto' => 'Match the visitor\'s device',
        'light' => 'Always light',
        'dark' => 'Always dark',
    );
    foreach ($choices as $value => $label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($value),
            selected($settings['theme'], $value, false),
            esc_html($label)
        );
    }
    echo '</select>';
}

function cloudflare_ai_search_field_max_results()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="number" min="1" max="20" name="cloudflare_ai_search_settings[max_results]" value="%d" />',
        (int) $settings['max_results']
    );
}

function cloudflare_ai_search_field_hide_branding()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[hide_branding]" value="1" ' . checked($settings['hide_branding'], true, false) . ' /> Hide the Cloudflare name on the search box</label>';
}

function cloudflare_ai_search_add_menu()
{
    add_options_page(
        'AI Search',
        'AI Search',
        'manage_options',
        'cloudflare-ai-search',
        'cloudflare_ai_search_render_page'
    );
}
add_action('admin_menu', 'cloudflare_ai_search_add_menu');

function cloudflare_ai_search_action_links($links)
{
    $url = admin_url('options-general.php?page=cloudflare-ai-search');
    array_unshift($links, '<a href="' . esc_url($url) . '">Settings</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(CLOUDFLARE_AI_SEARCH_FILE), 'cloudflare_ai_search_action_links');

function cloudflare_ai_search_admin_assets($hook)
{
    if ($hook !== 'settings_page_cloudflare-ai-search') {
        return;
    }
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script(
        'wp-color-picker',
        "jQuery(function($){ $('.cloudflare-ai-search-color').wpColorPicker(); });"
    );
}
add_action('admin_enqueue_scripts', 'cloudflare_ai_search_admin_assets');

function cloudflare_ai_search_admin_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = cloudflare_ai_search_settings();
    if ($settings['endpoint'] !== '') {
        return;
    }
    if (!$settings['search_bar'] && !$settings['modal'] && !$settings['chat']) {
        return;
    }
    $url = admin_url('options-general.php?page=cloudflare-ai-search');
    echo '<div class="notice notice-warning"><p>AI Search is not connected yet. <a href="' . esc_url($url) . '">Add the Cloudflare address</a>.</p></div>';
}
add_action('admin_notices', 'cloudflare_ai_search_admin_notice');

function cloudflare_ai_search_render_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $user_id = get_current_user_id();
    $check_key = 'cloudflare_ai_search_check_' . $user_id;
    $check = get_transient($check_key);
    if (is_array($check)) {
        delete_transient($check_key);
    }

    echo '<div class="wrap">';
    echo '<h1>AI Search</h1>';
    echo '<p>Visitors can search this site in their own words. Cloudflare reads the public pages and answers from those pages.</p>';

    if (is_array($check)) {
        $class = !empty($check['ok']) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html((string) $check['message']) . '</p></div>';
    }
    settings_errors('cloudflare_ai_search');

    echo '<form method="post" action="options.php">';
    settings_fields('cloudflare_ai_search');
    do_settings_sections('cloudflare-ai-search');
    submit_button('Save changes');
    echo '</form>';

    echo '<hr />';
    echo '<h2>Check the connection</h2>';
    echo '<p>Save first. This asks Cloudflare one question and tells you whether the address answers. An empty answer still counts as connected while Cloudflare is still reading the site.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('cloudflare_ai_search_test');
    echo '<input type="hidden" name="action" value="cloudflare_ai_search_test" />';
    submit_button('Check connection', 'secondary', 'submit', false);
    echo '</form>';
    echo '</div>';
}

function cloudflare_ai_search_handle_test()
{
    if (!current_user_can('manage_options')) {
        wp_die('You cannot check this connection.');
    }
    check_admin_referer('cloudflare_ai_search_test');

    $settings = cloudflare_ai_search_settings();
    $endpoint = $settings['endpoint'];
    $message = 'Add a Cloudflare address and save it before checking the connection.';
    $ok = false;

    if ($endpoint !== '') {
        $response = wp_remote_post(
            $endpoint . '/search',
            array(
                'timeout' => 20,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'What is this site?',
                        ),
                    ),
                )),
            )
        );
        if (is_wp_error($response)) {
            $message = 'The site could not reach Cloudflare. Try again in a minute.';
        } else {
            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status === 200) {
                $ok = true;
                $message = 'Connected. The search service answered. If a search on the site is empty, Cloudflare may still be reading the pages.';
            } else {
                $message = 'Cloudflare answered HTTP ' . $status . '. Confirm the public endpoint is turned on, then paste the address again.';
            }
        }
    }

    set_transient(
        'cloudflare_ai_search_check_' . get_current_user_id(),
        array(
            'ok' => $ok,
            'message' => $message,
        ),
        60
    );

    wp_safe_redirect(admin_url('options-general.php?page=cloudflare-ai-search'));
    exit;
}
add_action('admin_post_cloudflare_ai_search_test', 'cloudflare_ai_search_handle_test');
