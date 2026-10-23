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

/**
 * Translation stand-ins, defined BEFORE the sources are loaded.
 *
 * `includes/routes.php` carries `__()` calls since issue #15, and this
 * harness is not a WordPress bootstrap. Without these, a code path reaching a
 * translated string fatals here while working perfectly in production, which
 * is the worst direction for a harness to be wrong in.
 *
 * THESE ARE THE SAME STAND-INS `stock-test.php` USED TO DEFINE, moved up here
 * so they exist before any source is required, and reading the SAME globals
 * so that file still drives them. Defining a second, simpler passthrough here
 * would have won on `function_exists` and silently turned three of its
 * translation assertions into tests of an English passthrough. It did exactly
 * that on the first attempt.
 */
$GLOBALS['cfai_stock_test_strings'] = array();
$GLOBALS['cfai_stock_test_plural'] = null;

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        $strings = $GLOBALS['cfai_stock_test_strings'];
        return isset($strings[$text]) ? $strings[$text] : $text;
    }
}
if (!function_exists('_n')) {
    function _n($single, $plural, $count, $domain = 'default')
    {
        if ((int) $count === 1) {
            return $single;
        }
        return $GLOBALS['cfai_stock_test_plural'] !== null
            ? $GLOBALS['cfai_stock_test_plural']
            : $plural;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return htmlspecialchars((string) __($text, $domain), ENT_QUOTES, 'UTF-8');
    }
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
// LAST on purpose: it defines WordPress stand-ins at global scope.
require __DIR__ . '/i18n-test.php';
require __DIR__ . '/lead-guard-test.php';

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}

fwrite(STDOUT, "all passed\n");
