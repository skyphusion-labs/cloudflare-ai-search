<?php
/**
 * Public endpoint checks. This file does not load WordPress, so the tests can run it alone.
 */

if (!defined('CLOUDFLARE_AI_SEARCH_SNIPPET')) {
    define('CLOUDFLARE_AI_SEARCH_SNIPPET', '0.0.25');
}

/**
 * Return the canonical https origin, or an empty string when the address is not a Cloudflare AI Search public endpoint.
 */
function cloudflare_ai_search_normalize_endpoint($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    if (!isset($parts['scheme'], $parts['host']) || strtolower((string) $parts['scheme']) !== 'https') {
        return '';
    }

    $path = isset($parts['path']) ? (string) $parts['path'] : '';
    if ($path !== '' && $path !== '/') {
        return '';
    }

    $host = strtolower((string) $parts['host']);
    $label = '(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)';
    $pattern = '/^(?:ns-)?' . $label . '\.search\.ai\.cloudflare\.com$/';
    if (!preg_match($pattern, $host)) {
        return '';
    }

    return 'https://' . $host;
}

/**
 * Script URL for the pinned snippet build. Empty when the endpoint is not usable.
 */
function cloudflare_ai_search_snippet_src($url)
{
    $base = cloudflare_ai_search_normalize_endpoint($url);
    if ($base === '') {
        return '';
    }

    return $base . '/assets/v' . CLOUDFLARE_AI_SEARCH_SNIPPET . '/search-snippet.es.js';
}
