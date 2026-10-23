<?php
/**
 * Stock text checks. Loaded by tests/endpoint-test.php.
 * No WordPress and no WooCommerce. No network.
 */

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

require_once dirname(__DIR__) . '/includes/stock.php';

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
