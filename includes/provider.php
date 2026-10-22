<?php
/**
 * Register the Cloudflare provider with the WordPress AI Client, and expose
 * Workers AI embeddings plus the public AI Search endpoint to WordPress and WooCommerce.
 */

if (!defined('ABSPATH')) {
    exit;
}

function cloudflare_ai_provider_boot()
{
    if (!class_exists('WordPress\\AiClient\\AiClient')) {
        return;
    }
    $text = 'WordPress\\AiClient\\Providers\\Models\\TextGeneration\\Contracts\\TextGenerationModelInterface';
    $image = 'WordPress\\AiClient\\Providers\\Models\\ImageGeneration\\Contracts\\ImageGenerationModelInterface';
    if (!interface_exists($text) || !interface_exists($image)) {
        return;
    }
    require_once __DIR__ . '/provider-models.php';
    $speech = 'WordPress\\AiClient\\Providers\\Models\\SpeechGeneration\\Contracts\\SpeechGenerationModelInterface';
    if (interface_exists($speech)) {
        require_once __DIR__ . '/provider-speech.php';
    }
    add_action('init', 'cloudflare_ai_provider_register', 5);
}
add_action('plugins_loaded', 'cloudflare_ai_provider_boot');

function cloudflare_ai_provider_register()
{
    if (!class_exists('Cloudflare_AI_Provider') || !class_exists('WordPress\\AiClient\\AiClient')) {
        return;
    }
    $registry = WordPress\AiClient\AiClient::defaultRegistry();
    if ($registry->hasProvider('cloudflare') || $registry->hasProvider('Cloudflare_AI_Provider')) {
        return;
    }
    $registry->registerProvider('Cloudflare_AI_Provider');
}

/**
 * Embed texts with Workers AI. WordPress and WooCommerce code can call this directly.
 *
 * The token is the Connectors key: environment, then constant, then the saved option.
 * On failure the return is a WP_Error and the message does not include the token.
 *
 * @param array<int, string> $texts
 * @return array<int, array<int, float>>|WP_Error
 */
function cloudflare_ai_provider_embeddings($texts)
{
    if (!is_array($texts)) {
        return new WP_Error('cloudflare_ai_embeddings', 'Embeddings need a list of texts.');
    }
    $settings = cloudflare_ai_search_settings();
    $key = cloudflare_ai_provider_connector_key();
    $credentials = cloudflare_ai_provider_resolve_credentials($key, $settings['account_id']);
    $url = cloudflare_ai_provider_run_url(
        $credentials['account_id'],
        $settings['gateway_id'],
        cloudflare_ai_provider_embeddings_model()
    );
    $body = cloudflare_ai_provider_embeddings_body($texts);
    if ($url === '' || $credentials['token'] === '' || !$body['text']) {
        return new WP_Error('cloudflare_ai_embeddings', 'Add the Cloudflare account ID and API token before embedding text.');
    }

    $response = wp_remote_post($url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $credentials['token'],
        ),
        'body' => wp_json_encode($body),
    ));
    if (is_wp_error($response)) {
        return new WP_Error('cloudflare_ai_embeddings', 'The site could not reach Cloudflare.');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status < 200 || $status >= 300) {
        return new WP_Error('cloudflare_ai_embeddings', cloudflare_ai_provider_error_message($status, $decoded));
    }
    return cloudflare_ai_provider_read_embeddings($decoded);
}

/**
 * Ask the public AI Search chat endpoint. No API token.
 * The connection check stays on /search, which returns pages and no sentence.
 *
 * @param string $question
 * @return array{answer: string, sources: array<int, string>}|WP_Error
 */
function cloudflare_ai_search_query($question)
{
    $question = trim((string) $question);
    $endpoint = cloudflare_ai_search_settings()['endpoint'];
    if ($question === '' || $endpoint === '') {
        return new WP_Error('cloudflare_ai_search', 'Add the AI Search address before searching.');
    }
    $response = wp_remote_post($endpoint . '/chat/completions', array(
        'timeout' => 20,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => wp_json_encode(array(
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $question,
                ),
            ),
        )),
    ));
    if (is_wp_error($response)) {
        return new WP_Error('cloudflare_ai_search', 'The site could not reach Cloudflare.');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status !== 200) {
        return new WP_Error('cloudflare_ai_search', 'Cloudflare answered HTTP ' . $status . '.');
    }
    return cloudflare_ai_search_read_answer($decoded);
}

function cloudflare_ai_provider_connector_key()
{
    $env = getenv('CLOUDFLARE_API_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    if (defined('CLOUDFLARE_API_KEY')) {
        $value = constant('CLOUDFLARE_API_KEY');
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }
    $stored = get_option('connectors_ai_cloudflare_api_key', '');
    if (!is_string($stored)) {
        return '';
    }
    return $stored;
}
