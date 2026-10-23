<?php
/**
 * Server-rendered variation and stock text.
 *
 * The HTML builder does not call WordPress or WooCommerce. The product
 * reader runs only when a hook calls it. tests/stock-test.php defines
 * ABSPATH and loads this file without WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Escape one interpolated value. Tests define esc_html when WordPress is absent.
 *
 * @param mixed $value
 * @return string
 */
function cloudflare_ai_search_stock_escape($value)
{
    $text = (string) $value;
    if (function_exists('esc_html')) {
        return esc_html($text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * End a fragment with a period when it does not already end a sentence.
 *
 * @param string $text
 * @return string
 */
function cloudflare_ai_search_stock_sentence($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $last = substr($text, -1);
    if ($last === '.' || $last === '!' || $last === '?') {
        return $text;
    }
    return $text . '.';
}

/**
 * Plain text for one stock row. Empty when the row has nothing to say.
 *
 * @param mixed $row
 * @return string Escaped text, with no markup of its own.
 */
function cloudflare_ai_search_stock_line($row)
{
    if (!is_array($row)) {
        return '';
    }

    $parts = array();

    $attributes = isset($row['attributes']) ? trim((string) $row['attributes']) : '';
    if ($attributes !== '') {
        $parts[] = cloudflare_ai_search_stock_escape(cloudflare_ai_search_stock_sentence($attributes));
    }

    $sku = isset($row['sku']) ? trim((string) $row['sku']) : '';
    if ($sku !== '') {
        $parts[] = 'SKU ' . cloudflare_ai_search_stock_escape($sku) . '.';
    }

    $price = isset($row['price']) ? trim((string) $row['price']) : '';
    if ($price !== '') {
        $parts[] = cloudflare_ai_search_stock_escape(cloudflare_ai_search_stock_sentence($price));
    }

    $status = isset($row['stock_status']) ? (string) $row['stock_status'] : '';
    if ($status === 'instock') {
        $parts[] = 'In stock.';
    } elseif ($status === 'outofstock') {
        $parts[] = 'Out of stock.';
    } elseif ($status === 'onbackorder') {
        $parts[] = 'On backorder.';
    }

    $managing = isset($row['managing_stock']) && $row['managing_stock'] === true;
    $quantity = array_key_exists('quantity', $row) ? $row['quantity'] : null;
    if ($managing && is_int($quantity)) {
        $parts[] = cloudflare_ai_search_stock_escape((string) $quantity) . ' available.';
    }

    return implode(' ', $parts);
}

/**
 * Visible stock section. An empty list returns an empty string, not an empty section.
 *
 * @param mixed $rows
 * @return string
 */
function cloudflare_ai_search_stock_html($rows)
{
    if (!is_array($rows) || $rows === array()) {
        return '';
    }

    $items = '';
    foreach ($rows as $row) {
        $line = cloudflare_ai_search_stock_line($row);
        if ($line === '') {
            continue;
        }
        $items .= '<li>' . $line . '</li>';
    }
    if ($items === '') {
        return '';
    }

    return '<section class="cloudflare-ai-search-stock"><h2>Stock</h2><ul>' . $items . '</ul></section>';
}

/**
 * Strip tags from a variation attribute summary.
 *
 * @param mixed $value
 * @return string
 */
function cloudflare_ai_search_stock_plain($value)
{
    $text = (string) $value;
    if (function_exists('wp_strip_all_tags')) {
        $text = wp_strip_all_tags($text);
    } else {
        $text = strip_tags($text);
    }
    $text = preg_replace('/\s+/u', ' ', $text);
    if (!is_string($text)) {
        return '';
    }
    return trim($text);
}

/**
 * Human readable attributes from the variation summary. Empty for other product types.
 *
 * @param object $product
 * @return string
 */
function cloudflare_ai_search_stock_attribute_text($product)
{
    if (!is_object($product)) {
        return '';
    }
    $summary = '';
    if (method_exists($product, 'get_attribute_summary')) {
        $summary = $product->get_attribute_summary();
    } elseif (function_exists('wc_get_formatted_variation')) {
        $summary = wc_get_formatted_variation($product, true, true, false);
    }
    return cloudflare_ai_search_stock_plain($summary);
}

/**
 * Numeric price plus the store currency code. Not get_price_html().
 *
 * @param object $product
 * @return string
 */
function cloudflare_ai_search_stock_price_text($product)
{
    if (!is_object($product) || !method_exists($product, 'get_price')) {
        return '';
    }
    $amount = $product->get_price();
    if ($amount === '' || $amount === null) {
        return '';
    }
    $amount = trim((string) $amount);
    if ($amount === '') {
        return '';
    }
    $currency = '';
    if (function_exists('get_woocommerce_currency')) {
        $currency = trim((string) get_woocommerce_currency());
    }
    if ($currency === '') {
        return $amount;
    }
    return $amount . ' ' . $currency;
}

/**
 * Whole-number stock quantity, or null when the product does not manage stock.
 *
 * @param object $product
 * @param bool   $managing
 * @return int|null
 */
function cloudflare_ai_search_stock_quantity($product, $managing)
{
    if (!$managing || !is_object($product) || !method_exists($product, 'get_stock_quantity')) {
        return null;
    }
    $raw = $product->get_stock_quantity();
    if (is_int($raw)) {
        return $raw;
    }
    if (is_numeric($raw) && (string) (int) $raw === trim((string) $raw)) {
        return (int) $raw;
    }
    return null;
}

/**
 * One renderer row from a product or variation. No WooCommerce object leaves this function.
 *
 * @param object $product
 * @param bool   $include_attributes
 * @return array
 */
function cloudflare_ai_search_stock_row_from_product($product, $include_attributes)
{
    $managing = false;
    if (is_object($product) && method_exists($product, 'managing_stock')) {
        $managing = $product->managing_stock() ? true : false;
    }
    $status = '';
    if (is_object($product) && method_exists($product, 'get_stock_status')) {
        $status = (string) $product->get_stock_status();
    }
    $sku = '';
    if (is_object($product) && method_exists($product, 'get_sku')) {
        $sku = trim((string) $product->get_sku());
    }

    return array(
        'attributes' => $include_attributes ? cloudflare_ai_search_stock_attribute_text($product) : '',
        'sku' => $sku,
        'price' => cloudflare_ai_search_stock_price_text($product),
        'stock_status' => $status,
        'managing_stock' => $managing,
        'quantity' => cloudflare_ai_search_stock_quantity($product, $managing),
    );
}

/**
 * Read variation rows without get_available_variations().
 *
 * That method drops hidden and out-of-stock variations when the catalog
 * hides them. The index has to see those rows. get_children() returns
 * every child variation, including out of stock.
 *
 * @param object $product
 * @return array
 */
function cloudflare_ai_search_stock_variation_rows($product)
{
    if (!is_object($product) || !method_exists($product, 'get_children') || !function_exists('wc_get_product')) {
        return array();
    }
    $children = $product->get_children();
    if (!is_array($children)) {
        return array();
    }

    $rows = array();
    foreach ($children as $child_id) {
        $child = wc_get_product($child_id);
        if (!is_object($child) || !method_exists($child, 'is_type') || !$child->is_type('variation')) {
            continue;
        }
        $rows[] = cloudflare_ai_search_stock_row_from_product($child, true);
    }
    return $rows;
}

/**
 * Rows for the current single product. Empty off the product page, and when WooCommerce is absent.
 *
 * @return array
 */
function cloudflare_ai_search_stock_rows()
{
    if (function_exists('is_admin') && is_admin()) {
        return array();
    }
    if (function_exists('is_feed') && is_feed()) {
        return array();
    }
    if (function_exists('is_embed') && is_embed()) {
        return array();
    }
    if (!function_exists('is_product') || !is_product()) {
        return array();
    }
    if (!function_exists('wc_get_product') || !function_exists('get_the_ID')) {
        return array();
    }

    $product = wc_get_product(get_the_ID());
    if (!is_object($product) || !method_exists($product, 'is_type')) {
        return array();
    }
    if ($product->is_type('variable')) {
        return cloudflare_ai_search_stock_variation_rows($product);
    }
    if ($product->is_type('simple')) {
        return array(cloudflare_ai_search_stock_row_from_product($product, false));
    }
    return array();
}

/**
 * Markup for this request, printed at most once.
 *
 * @return string
 */
function cloudflare_ai_search_stock_markup()
{
    static $done = false;
    if ($done) {
        return '';
    }
    $html = cloudflare_ai_search_stock_html(cloudflare_ai_search_stock_rows());
    if ($html === '') {
        return '';
    }
    $done = true;
    return $html;
}

/**
 * Tags the stock section is allowed to print.
 *
 * @return array
 */
function cloudflare_ai_search_stock_allowed_html()
{
    return array(
        'section' => array(
            'class' => true,
        ),
        'h2' => array(),
        'ul' => array(),
        'li' => array(),
    );
}
