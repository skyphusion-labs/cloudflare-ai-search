<?php
/**
 * Stock text checks. Loaded by tests/endpoint-test.php, which defines
 * ABSPATH and holds expect(). No WordPress, no WooCommerce, no network.
 *
 * This file does not define ABSPATH and does not weaken the direct-access
 * guard in includes/stock.php. It reads the constant the harness already set.
 *
 * ORDER MATTERS IN THIS FILE. Part one runs with no WordPress translation
 * functions defined, so the label set falls back to English and the expected
 * strings are literal. Part two defines __(), _n() and apply_filters() to
 * prove the translated and filtered seams are really reached. Those
 * definitions are global and permanent for the rest of the process, so
 * nothing from part one may be moved below them.
 */

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

require_once dirname(__DIR__) . '/includes/stock.php';

/* ---------- part one: English fallback, no WordPress ---------- */

$simple = cloudflare_ai_search_stock_html(array(array(
    'attributes' => '',
    'sku' => 'SHIRT-1',
    'price' => '45.00 USD',
    'stock_status' => 'instock',
    'managing_stock' => true,
    'quantity' => 4,
)));
expect(
    'simple in stock',
    $simple,
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>SKU SHIRT-1. 45.00 USD. In stock. 4 available.</li></ul></section>'
);
expect('section class', strpos($simple, 'cloudflare-ai-search-stock') !== false, true);

expect(
    'out of stock without quantity',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => '',
        'price' => '',
        'stock_status' => 'outofstock',
        'managing_stock' => false,
        'quantity' => 9,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>Out of stock.</li></ul></section>'
);

expect(
    'on backorder',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => '',
        'price' => '12.00 USD',
        'stock_status' => 'onbackorder',
        'managing_stock' => true,
        'quantity' => 2,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>12.00 USD. On backorder. 2 available.</li></ul></section>'
);

/*
 * A managed product goes negative once backorders are taken against it.
 * The status has to survive and the count has to be dropped: "-3 available."
 * is not a fact worth putting in an index.
 */
expect(
    'negative quantity drops the count',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => 'BACK-1',
        'price' => '',
        'stock_status' => 'onbackorder',
        'managing_stock' => true,
        'quantity' => -3,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>SKU BACK-1. On backorder.</li></ul></section>'
);
expect(
    'zero quantity still prints',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => '',
        'price' => '',
        'stock_status' => 'outofstock',
        'managing_stock' => true,
        'quantity' => 0,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>Out of stock. 0 available.</li></ul></section>'
);

$variable = cloudflare_ai_search_stock_html(array(
    array(
        'attributes' => 'Color Blue, Size Large',
        'sku' => 'HOODIE-BL-L',
        'price' => '45.00 USD',
        'stock_status' => 'instock',
        'managing_stock' => true,
        'quantity' => 3,
    ),
    array(
        'attributes' => 'Color Blue, Size Small',
        'sku' => 'HOODIE-BL-S',
        'price' => '45.00 USD',
        'stock_status' => 'outofstock',
        'managing_stock' => true,
        'quantity' => 0,
    ),
));
expect(
    'variable rows',
    $variable,
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul>'
        . '<li>Color Blue, Size Large. SKU HOODIE-BL-L. 45.00 USD. In stock. 3 available.</li>'
        . '<li>Color Blue, Size Small. SKU HOODIE-BL-S. 45.00 USD. Out of stock. 0 available.</li>'
        . '</ul></section>'
);
expect('out of stock variation present', strpos($variable, 'HOODIE-BL-S') !== false && strpos($variable, 'Out of stock.') !== false, true);

expect('empty input', cloudflare_ai_search_stock_html(array()), '');
expect('blank row', cloudflare_ai_search_stock_html(array(array(
    'attributes' => '',
    'sku' => '   ',
    'price' => '',
    'stock_status' => 'mystery',
    'managing_stock' => true,
    'quantity' => '4',
))), '');

expect(
    'unknown status omitted',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => 'PLAIN',
        'price' => '',
        'stock_status' => 'mystery',
        'managing_stock' => false,
        'quantity' => null,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul><li>SKU PLAIN.</li></ul></section>'
);

/*
 * cloudflare_ai_search_stock_line() takes the label set as an argument so the
 * html builder loads it once per section instead of once per row. Called
 * without one it has to load its own, so that path is exercised here.
 */
expect(
    'line loads its own labels when none are passed',
    cloudflare_ai_search_stock_line(array(
        'attributes' => 'Color Red',
        'sku' => 'LINE-1',
        'price' => '9.00 USD',
        'stock_status' => 'instock',
        'managing_stock' => true,
        'quantity' => 2,
    )),
    'Color Red. SKU LINE-1. 9.00 USD. In stock. 2 available.'
);
expect('line returns empty for a non array', cloudflare_ai_search_stock_line('nope'), '');

$nasty = cloudflare_ai_search_stock_html(array(array(
    'attributes' => 'Color <script>alert(1)</script>',
    'sku' => '<script>',
    'price' => '1 <b>USD</b>',
    'stock_status' => 'instock',
    'managing_stock' => false,
    'quantity' => null,
)));
expect('script open absent', strpos($nasty, '<script') === false, true);
expect('script close absent', strpos($nasty, '</script') === false, true);
expect('attribute script escaped', strpos($nasty, '&lt;script&gt;') !== false, true);
expect('sku script escaped', strpos($nasty, 'SKU &lt;script&gt;.') !== false, true);

/* The cap has to answer with its default before apply_filters exists. */
expect('variation cap default without wordpress', cloudflare_ai_search_stock_max_variations(), 100);

/* ---------- part two: translated and filtered seams ---------- */
/*
 * Everything below defines WordPress stand-ins. They are global and cannot
 * be undefined, so no English expectation may follow them.
 */

$GLOBALS['cfai_stock_test_strings'] = array(
    'Stock' => 'Lager',
    'SKU %s.' => 'Artikelnummer %s.',
    'In stock.' => 'Auf Lager.',
    'Out of stock.' => 'Nicht auf Lager.',
);
$GLOBALS['cfai_stock_test_plural'] = 'Verfuegbar: %d.';
$GLOBALS['cfai_stock_test_filters'] = array();

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
        if ($count === 1) {
            return $single;
        }
        return $GLOBALS['cfai_stock_test_plural'];
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        $filters = $GLOBALS['cfai_stock_test_filters'];
        return array_key_exists($hook, $filters) ? $filters[$hook] : $value;
    }
}

/*
 * The point of this one is that __() is actually on the path. If the labels
 * were hardcoded, or if __() were handed a variable the extractor cannot
 * see but the runtime still resolved, this would stay English and fail.
 */
expect(
    'labels come through translation',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => 'DE-1',
        'price' => '',
        'stock_status' => 'instock',
        'managing_stock' => true,
        'quantity' => 6,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Lager</h2><ul><li>Artikelnummer DE-1. Auf Lager. Verfuegbar: 6.</li></ul></section>'
);
expect(
    'singular plural form selected',
    cloudflare_ai_search_stock_html(array(array(
        'attributes' => '',
        'sku' => '',
        'price' => '',
        'stock_status' => 'instock',
        'managing_stock' => true,
        'quantity' => 1,
    ))),
    '<section class="cloudflare-ai-search-stock"><h2>Lager</h2><ul><li>Auf Lager. 1 available.</li></ul></section>'
);
expect('class name is not translated', strpos(cloudflare_ai_search_stock_html(array(array(
    'attributes' => '',
    'sku' => 'DE-2',
    'price' => '',
    'stock_status' => 'outofstock',
    'managing_stock' => false,
    'quantity' => null,
))), 'class="cloudflare-ai-search-stock"') !== false, true);

/* A translation file is content, not code. It must not be able to add markup. */
$GLOBALS['cfai_stock_test_strings']['Out of stock.'] = '<script>alert(1)</script>';
$GLOBALS['cfai_stock_test_strings']['Stock'] = '<b>Stock</b>';
$GLOBALS['cfai_stock_test_strings']['SKU %s.'] = '<i>SKU %s.</i>';
$hostile = cloudflare_ai_search_stock_html(array(array(
    'attributes' => '',
    'sku' => 'HOSTILE',
    'price' => '',
    'stock_status' => 'outofstock',
    'managing_stock' => false,
    'quantity' => null,
)));
expect('hostile translation cannot open a tag', strpos($hostile, '<script') === false, true);
expect('hostile translation is escaped', strpos($hostile, '&lt;script&gt;') !== false, true);
expect('hostile heading cannot open a tag', strpos($hostile, '<b>') === false, true);
/* The SKU label carries a placeholder, so it is escaped before sprintf. */
expect('hostile sku label cannot open a tag', strpos($hostile, '<i>') === false, true);
expect('hostile sku label keeps its value', strpos($hostile, 'SKU HOSTILE.') !== false, true);

/* The cap reads the filter, and refuses a value it cannot use. */
$GLOBALS['cfai_stock_test_filters']['cloudflare_ai_search_stock_max_variations'] = 5;
expect('variation cap honours the filter', cloudflare_ai_search_stock_max_variations(), 5);
$GLOBALS['cfai_stock_test_filters']['cloudflare_ai_search_stock_max_variations'] = '7';
expect('variation cap accepts a numeric string', cloudflare_ai_search_stock_max_variations(), 7);
$GLOBALS['cfai_stock_test_filters']['cloudflare_ai_search_stock_max_variations'] = 0;
expect('variation cap rejects zero', cloudflare_ai_search_stock_max_variations(), 100);
$GLOBALS['cfai_stock_test_filters']['cloudflare_ai_search_stock_max_variations'] = 'all';
expect('variation cap rejects a non number', cloudflare_ai_search_stock_max_variations(), 100);
/*
 * A boolean is the case only is_numeric() catches. Someone wiring
 * __return_true to this filter by accident casts to 1, which the lower
 * bound below would wave through as a cap of one variation per page.
 */
$GLOBALS['cfai_stock_test_filters']['cloudflare_ai_search_stock_max_variations'] = true;
expect('variation cap rejects a boolean', cloudflare_ai_search_stock_max_variations(), 100);
