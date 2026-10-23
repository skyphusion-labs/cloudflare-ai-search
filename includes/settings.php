<?php
/**
 * Settings screen. Settings, then Skyphusion AI. The page slug stays cloudflare-ai-search.
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
        'account_id' => '',
        'gateway_id' => '',
        'lead_search' => false,
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
    $settings['lead_search'] = (bool) $settings['lead_search'];
    $settings['account_id'] = cloudflare_ai_provider_account_id($settings['account_id']);
    $settings['gateway_id'] = cloudflare_ai_provider_gateway_id($settings['gateway_id']);
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
        __('Connection', 'skyphusion-ai'),
        'cloudflare_ai_search_section_connection',
        'cloudflare-ai-search'
    );
    add_settings_field(
        'endpoint',
        __('AI Search address', 'skyphusion-ai'),
        'cloudflare_ai_search_field_endpoint',
        'cloudflare-ai-search',
        'connection'
    );

    add_settings_section(
        'workers',
        __('Workers AI and AI Gateway', 'skyphusion-ai'),
        'cloudflare_ai_search_section_workers',
        'cloudflare-ai-search'
    );
    add_settings_field('account_id', __('Account ID', 'skyphusion-ai'), 'cloudflare_ai_search_field_account_id', 'cloudflare-ai-search', 'workers');
    add_settings_field('gateway_id', __('AI Gateway', 'skyphusion-ai'), 'cloudflare_ai_search_field_gateway_id', 'cloudflare-ai-search', 'workers');

    add_settings_section(
        'display',
        __('What visitors see', 'skyphusion-ai'),
        'cloudflare_ai_search_section_display',
        'cloudflare-ai-search'
    );
    add_settings_field('search_bar', __('Search box', 'skyphusion-ai'), 'cloudflare_ai_search_field_search_bar', 'cloudflare-ai-search', 'display');
    add_settings_field('modal', __('Keyboard search', 'skyphusion-ai'), 'cloudflare_ai_search_field_modal', 'cloudflare-ai-search', 'display');
    add_settings_field('chat', __('Chat button', 'skyphusion-ai'), 'cloudflare_ai_search_field_chat', 'cloudflare-ai-search', 'display');
    add_settings_field('placement', __('Where the search box goes', 'skyphusion-ai'), 'cloudflare_ai_search_field_placement', 'cloudflare-ai-search', 'display');
    add_settings_field('hide_checkout', __('Cart and checkout', 'skyphusion-ai'), 'cloudflare_ai_search_field_hide_checkout', 'cloudflare-ai-search', 'display');
    add_settings_field('lead_search', __('Search results', 'skyphusion-ai'), 'cloudflare_ai_search_field_lead_search', 'cloudflare-ai-search', 'display');

    add_settings_section(
        'appearance',
        __('Look', 'skyphusion-ai'),
        'cloudflare_ai_search_section_appearance',
        'cloudflare-ai-search'
    );
    add_settings_field('placeholder', __('Placeholder', 'skyphusion-ai'), 'cloudflare_ai_search_field_placeholder', 'cloudflare-ai-search', 'appearance');
    add_settings_field('color', __('Accent color', 'skyphusion-ai'), 'cloudflare_ai_search_field_color', 'cloudflare-ai-search', 'appearance');
    add_settings_field('theme', __('Light or dark', 'skyphusion-ai'), 'cloudflare_ai_search_field_theme', 'cloudflare-ai-search', 'appearance');
    add_settings_field('max_results', __('How many results', 'skyphusion-ai'), 'cloudflare_ai_search_field_max_results', 'cloudflare-ai-search', 'appearance');
    add_settings_field('hide_branding', __('Cloudflare name', 'skyphusion-ai'), 'cloudflare_ai_search_field_hide_branding', 'cloudflare-ai-search', 'appearance');
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
                // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- names the required host. It does not load a file.
                __('That address has to be an https link ending in search.ai.cloudflare.com. The previous address was kept.', 'skyphusion-ai')
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

    $clean['lead_search'] = !empty($input['lead_search']);

    $typed_account = isset($input['account_id']) ? trim((string) $input['account_id']) : '';
    if ($typed_account === '') {
        $clean['account_id'] = '';
    } else {
        $account = cloudflare_ai_provider_account_id($typed_account);
        if ($account === '') {
            if (function_exists('add_settings_error')) {
                add_settings_error(
                    'cloudflare_ai_search',
                    'account_id',
                    __('Account ID has to be 32 hex characters. The previous account ID was kept.', 'skyphusion-ai')
                );
            }
            $clean['account_id'] = $old['account_id'];
        } else {
            $clean['account_id'] = $account;
        }
    }

    $typed_gateway = isset($input['gateway_id']) ? trim((string) $input['gateway_id']) : '';
    if ($typed_gateway === '') {
        $clean['gateway_id'] = '';
    } else {
        $gateway = cloudflare_ai_provider_gateway_id($typed_gateway);
        if ($gateway === '') {
            if (function_exists('add_settings_error')) {
                add_settings_error(
                    'cloudflare_ai_search',
                    'gateway_id',
                    __('Gateway ID has to be a slug of letters, numbers, hyphens, or underscores. The previous gateway ID was kept.', 'skyphusion-ai')
                );
            }
            $clean['gateway_id'] = $old['gateway_id'];
        } else {
            $clean['gateway_id'] = $gateway;
        }
    }

    return $clean;
}

function cloudflare_ai_search_section_connection()
{
    echo '<p>' . esc_html__('Paste the public AI Search address. The search box does not send an API token.', 'skyphusion-ai') . '</p>';
    echo '<ol>';
    echo '<li>' . esc_html__('In the Cloudflare dashboard, open AI Search and select the instance for this site.', 'skyphusion-ai') . '</li>';
    echo '<li>' . esc_html__('Open Settings, then Public endpoint, and turn the public endpoint on.', 'skyphusion-ai') . '</li>';
    echo '<li>' . wp_kses(__('Copy the address. It looks like <code>https://something.search.ai.cloudflare.com/</code>.', 'skyphusion-ai'), array('code' => array())) . '</li>'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- names the required host. It does not load a file.
    echo '</ol>';
}

function cloudflare_ai_search_section_workers()
{
    $connectors = admin_url('options-connectors.php');
    echo '<p>' . sprintf(
        /* translators: %s is the URL of the WordPress Connectors settings screen. */
        wp_kses(
            __('WordPress and WooCommerce call Workers AI through the AI Client. The provider id is <code>cloudflare</code>. The plugin reads the API token from the <code>CLOUDFLARE_API_KEY</code> environment variable first. It then tries a PHP constant of the same name. It falls back to the token saved in <a href="%s">Settings, then Connectors</a>. A token in the environment or in a constant overrides the saved one. This screen does not store the token.', 'skyphusion-ai'),
            array('code' => array(), 'a' => array('href' => array()))
        ),
        esc_url($connectors)
    ) . '</p>';
    echo '<p>' . wp_kses(__('Leave the gateway blank to call Workers AI directly. Set a gateway id to send the same calls through AI Gateway. A model call does not replace another provider that is already configured. Call <code>using_provider(\'cloudflare\')</code> when the caller wants this one.', 'skyphusion-ai'), array('code' => array())) . '</p>';
}

function cloudflare_ai_search_section_display()
{
    echo '<p>' . esc_html__('Choose what shows up for visitors. Save, then look at the front of the site.', 'skyphusion-ai') . '</p>';
}

function cloudflare_ai_search_section_appearance()
{
    echo '<p>' . esc_html__('These match the search box to the rest of the site.', 'skyphusion-ai') . '</p>';
}

function cloudflare_ai_search_field_endpoint()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="url" class="regular-text code" name="cloudflare_ai_search_settings[endpoint]" value="%s" placeholder="https://example.search.ai.cloudflare.com/" />', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- names the required host. It does not load a file.
        esc_attr($settings['endpoint'])
    );
    echo '<p class="description">' . esc_html__('Leave this blank to hide search until you are ready.', 'skyphusion-ai') . '</p>';
}

function cloudflare_ai_search_field_search_bar()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[search_bar]" value="1" ' . checked($settings['search_bar'], true, false) . ' /> Show a search box</label>';
    echo '<p class="description">' . esc_html__('Lists matching pages as someone types.', 'skyphusion-ai') . '</p>';
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
    echo '<p class="description">' . esc_html__('The chat button answers in a sentence and uses more of the Cloudflare allowance. The search box only lists pages.', 'skyphusion-ai') . '</p>';
}

function cloudflare_ai_search_field_placement()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="radio" name="cloudflare_ai_search_settings[placement]" value="top" ' . checked($settings['placement'], 'top', false) . ' /> Top of every page</label><br />';
    echo '<label><input type="radio" name="cloudflare_ai_search_settings[placement]" value="shortcode" ' . checked($settings['placement'], 'shortcode', false) . ' /> Only where I place the shortcode</label>';
    echo '<p class="description">' . wp_kses(__('Shortcode: <code>[cloudflare_ai_search]</code>', 'skyphusion-ai'), array('code' => array())) . '</p>';
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

function cloudflare_ai_search_field_account_id()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="text" class="regular-text code" name="cloudflare_ai_search_settings[account_id]" value="%s" maxlength="32" spellcheck="false" autocomplete="off" />',
        esc_attr($settings['account_id'])
    );
    echo '<p class="description">' . wp_kses(__('32 hex characters from the Cloudflare dashboard. You can also set <code>CLOUDFLARE_ACCOUNT_ID</code>, or put the account id in front of the token on Connectors as <code>account-id:api-token</code>. Only the token half is sent.', 'skyphusion-ai'), array('code' => array())) . '</p>';
}

function cloudflare_ai_search_field_gateway_id()
{
    $settings = cloudflare_ai_search_settings();
    printf(
        '<input type="text" class="regular-text code" name="cloudflare_ai_search_settings[gateway_id]" value="%s" maxlength="64" spellcheck="false" autocomplete="off" />',
        esc_attr($settings['gateway_id'])
    );
    echo '<p class="description">' . wp_kses(__('Optional. A slug such as <code>store</code>. Blank sends Workers AI calls to api.cloudflare.com.', 'skyphusion-ai'), array('code' => array())) . '</p>';
}

function cloudflare_ai_search_field_lead_search()
{
    $settings = cloudflare_ai_search_settings();
    echo '<label><input type="checkbox" name="cloudflare_ai_search_settings[lead_search]" value="1" ' . checked($settings['lead_search'], true, false) . ' /> Show an AI Search answer above WordPress and WooCommerce search results</label>';
    echo '<p class="description">' . esc_html__('Off until you turn it on. A failed request stays off the page. The cart and checkout stay quiet when that option is on.', 'skyphusion-ai') . '</p>';
}

function cloudflare_ai_search_add_menu()
{
    add_options_page(
        'Skyphusion AI for Cloudflare',
        'Skyphusion AI',
        'manage_options',
        'cloudflare-ai-search',
        'cloudflare_ai_search_render_page'
    );
}
add_action('admin_menu', 'cloudflare_ai_search_add_menu');

function cloudflare_ai_search_action_links($links)
{
    $url = admin_url('options-general.php?page=cloudflare-ai-search');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'skyphusion-ai') . '</a>');
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
    echo '<div class="notice notice-warning"><p>' . sprintf(
        /* translators: %s is the URL of this plugin's settings screen. */
        wp_kses(
            __('Skyphusion AI is not connected yet. <a href="%s">Add the AI Search address</a>.', 'skyphusion-ai'),
            array('a' => array('href' => array()))
        ),
        esc_url($url)
    ) . '</p></div>';
}
add_action('admin_notices', 'cloudflare_ai_search_admin_notice');

function cloudflare_ai_search_render_product_links()
{
    $settings = cloudflare_ai_search_settings();
    $links = cloudflare_ai_provider_links($settings['account_id']);
    $products = array(
        array('Workers AI', $links['workers_ai'], $links['workers_ai_docs']),
        array('AI Gateway', $links['gateway'], $links['gateway_docs']),
        array('AI Search', $links['search'], $links['search_docs']),
    );
    echo '<ul>';
    foreach ($products as $product) {
        echo '<li><a href="' . esc_url($product[1]) . '" target="_blank" rel="noopener noreferrer">' . esc_html($product[0]) . '</a>';
        echo ' (<a href="' . esc_url($product[2]) . '" target="_blank" rel="noopener noreferrer">docs</a>)</li>';
    }
    echo '</ul>';
    echo '<p>Gateway calls to Workers AI are documented at <a href="' . esc_url($links['gateway_workers_docs']) . '" target="_blank" rel="noopener noreferrer">AI Gateway for Workers AI</a>.</p>';
}

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
    echo '<h1>Skyphusion AI for Cloudflare</h1>';
    echo '<p>Workers AI, AI Gateway, and AI Search. Visitors can still search this site in their own words. WordPress and WooCommerce can call the same Cloudflare account for text, images, speech, and embeddings.</p>';
    cloudflare_ai_search_render_product_links();

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
