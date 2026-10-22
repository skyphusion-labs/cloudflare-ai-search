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

/**
 * Answer text and source snippets from a public AI Search /search response.
 *
 * @param mixed $decoded
 * @return array{answer: string, sources: array<int, string>}
 */
function cloudflare_ai_search_read_answer($decoded)
{
    $answer = '';
    $sources = array();
    if (!is_array($decoded)) {
        return array('answer' => '', 'sources' => array());
    }

    if (isset($decoded['response']) && is_string($decoded['response'])) {
        $answer = $decoded['response'];
    } elseif (isset($decoded['result']['response']) && is_string($decoded['result']['response'])) {
        $answer = $decoded['result']['response'];
    } elseif (isset($decoded['choices'][0]['message']['content']) && is_string($decoded['choices'][0]['message']['content'])) {
        $answer = $decoded['choices'][0]['message']['content'];
    }

    $rows = array();
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $rows = $decoded['data'];
    } elseif (isset($decoded['result']['data']) && is_array($decoded['result']['data'])) {
        $rows = $decoded['result']['data'];
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = '';
        if (isset($row['content']) && is_string($row['content'])) {
            $text = $row['content'];
        } elseif (isset($row['text']) && is_string($row['text'])) {
            $text = $row['text'];
        }
        $text = trim($text);
        if ($text !== '') {
            $sources[] = $text;
        }
    }

    return array(
        'answer' => $answer,
        'sources' => $sources,
    );
}
