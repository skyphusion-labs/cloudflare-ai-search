<?php
/**
 * Run: php tests/endpoint-test.php
 *
 * ABSPATH is defined here so includes/endpoint.php and includes/routes.php
 * can keep the direct-access guard. This is not a WordPress bootstrap.
 * Do not remove the define. Without it those files exit before the checks run.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require dirname(__DIR__) . '/includes/endpoint.php';
require dirname(__DIR__) . '/includes/routes.php';

$failures = 0;

function shown($value)
{
    if (is_string($value)) {
        return $value;
    }
    $json = json_encode($value);
    return $json === false ? gettype($value) : $json;
}

function expect($label, $actual, $expected)
{
    global $failures;
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}\n  actual: " . shown($actual) . "\n  expected: " . shown($expected) . "\n");
        $failures++;
        return;
    }
    fwrite(STDOUT, "ok {$label}\n");
}

$good = 'https://abc123.search.ai.cloudflare.com/';
expect('lowercase host', cloudflare_ai_search_normalize_endpoint($good), 'https://abc123.search.ai.cloudflare.com');
expect('uppercase host', cloudflare_ai_search_normalize_endpoint('https://ABC123.search.ai.cloudflare.com'), 'https://abc123.search.ai.cloudflare.com');
expect('namespace host', cloudflare_ai_search_normalize_endpoint('https://ns-abc123.search.ai.cloudflare.com'), 'https://ns-abc123.search.ai.cloudflare.com');
expect('trailing space', cloudflare_ai_search_normalize_endpoint('  https://abc123.search.ai.cloudflare.com  '), 'https://abc123.search.ai.cloudflare.com');
expect('empty', cloudflare_ai_search_normalize_endpoint(''), '');
expect('http rejected', cloudflare_ai_search_normalize_endpoint('http://abc123.search.ai.cloudflare.com'), '');
expect('other host rejected', cloudflare_ai_search_normalize_endpoint('https://example.com'), '');
expect('path rejected', cloudflare_ai_search_normalize_endpoint('https://abc123.search.ai.cloudflare.com/search'), '');
expect('query rejected', cloudflare_ai_search_normalize_endpoint('https://abc123.search.ai.cloudflare.com/?q=1'), '');
expect('userinfo rejected', cloudflare_ai_search_normalize_endpoint('https://user:pass@abc123.search.ai.cloudflare.com'), '');
expect('snippet src', cloudflare_ai_search_snippet_src($good), 'https://abc123.search.ai.cloudflare.com/assets/v0.0.25/search-snippet.es.js');
expect('snippet src rejected', cloudflare_ai_search_snippet_src('https://example.com'), '');

require __DIR__ . '/provider-test.php';
require __DIR__ . '/stock-test.php';

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}

fwrite(STDOUT, "all passed\n");
