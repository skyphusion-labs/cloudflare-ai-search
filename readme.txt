=== Cloudflare AI Search ===
Contributors: skyphusion
Tags: search, cloudflare, ai
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search the site in ordinary language with Cloudflare AI Search.

== Description ==

Connect a Cloudflare AI Search public endpoint from Settings, then AI Search. The same settings are available with `wp cloudflare-ai-search`. Visitors can use a search box, open search with Ctrl+K or Command+K, and optionally ask a chat button.

The plugin does not store an API token. It only talks to the public address you paste in.

Shortcode: `[cloudflare_ai_search]`

== Installation ==

1. Upload the plugin and activate it.
2. In Cloudflare, turn on the public endpoint for your AI Search instance.
3. In WordPress, open Settings, then AI Search, and paste that address.

== Frequently Asked Questions ==

= Where do I get the address? =

Cloudflare dashboard, AI Search, your instance, Settings, Public endpoint. Copy the address that ends in search.ai.cloudflare.com.

= Does the chat button cost extra? =

The search box lists pages. The chat button answers in a sentence and uses the Cloudflare AI allowance. You can leave the chat button off.

== Changelog ==

= 0.1.0 =
* Settings screen, WP-CLI command, search box, keyboard search, and optional chat button.
