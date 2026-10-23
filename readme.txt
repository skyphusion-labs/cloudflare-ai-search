=== Skyphusion AI for Cloudflare ===
Contributors: skyphusion
Tags: search, cloudflare, ai, woocommerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Workers AI, AI Gateway, and AI Search for WordPress and WooCommerce.

== Description ==

Connect a public AI Search endpoint from Settings, then Skyphusion AI. The same settings are available with `wp cloudflare-ai-search`. Visitors can use a search box, open search with Ctrl+K or Command+K, and optionally ask a chat button.

WordPress and WooCommerce call Workers AI through the AI Client. The provider id is `cloudflare`. Open Settings, then Connectors, and save the API token there. This plugin does not store the token. Set the account id on the Skyphusion AI screen, and an optional AI Gateway slug when calls should go through the gateway.

The screen links to Workers AI, AI Gateway, and AI Search.

An optional switch prints an AI Search answer above WordPress and WooCommerce search results. It is off until you turn it on. Errors stay off the page.

Shortcode: `[cloudflare_ai_search]`

Example:

`wp_ai_client_prompt( 'Write a product summary.' )->using_provider( 'cloudflare' );`

`cloudflare_ai_provider_embeddings()` and `cloudflare_ai_search_query()` are available to other plugins. The search helper posts to the public chat/completions path and does not send a token.

== External services ==

AI Search: the browser loads the snippet from the public address you paste. Checking the connection posts to /search. The search helper and the optional search-results answer post to /chat/completions. No API token is sent.

Workers AI and AI Gateway: prompts, images, speech text, and embedding texts are sent to api.cloudflare.com, or to gateway.ai.cloudflare.com when a gateway id is set. The Connectors API token is sent as a bearer. The account id and gateway id are not secrets.

== Installation ==

1. Upload the plugin and activate it.
2. In Cloudflare, turn on the public endpoint for your AI Search instance.
3. In WordPress, open Settings, then Skyphusion AI, and paste that address.
4. Paste the account id. Open Settings, then Connectors, and save the Workers AI API token for Cloudflare.

== Frequently Asked Questions ==

= Where do I get the address? =

Cloudflare dashboard, AI Search, your instance, Settings, Public endpoint. Copy the address that ends in search.ai.cloudflare.com. The plugin screen links there.

= Where does the API token go? =

Settings, then Connectors. The environment variable or constant `CLOUDFLARE_API_KEY` is used first. You can prefix the token with the account id as `account-id:api-token`. This plugin's own option never stores the token.

= Does the chat button cost extra? =

The search box lists pages. The chat button answers in a sentence and uses the Workers AI allowance. You can leave the chat button off.

= Does this replace another AI provider? =

No. A caller that wants Cloudflare uses `using_provider('cloudflare')`.

== Changelog ==

= 0.2.1 =
* The plugin name is Skyphusion AI for Cloudflare. The author is Conrad Rockenhaus (skyphusion).
* On a block theme, the search-results answer is inserted in the results block so it stays inside the document.
* Direct requests for the endpoint and route files exit before they define anything.

= 0.2.0 =
* Register a WordPress AI Client provider for Workers AI text, vision, images, and speech, through AI Gateway when a gateway id is set.
* Link Workers AI, AI Gateway, AI Search, and Connectors from the settings screen.
* Add embeddings and public AI Search helpers for WordPress and WooCommerce.
* Optional AI Search answer above search results, off by default. The helper asks /chat/completions and reads chunks as sources. Search feeds and embeds do not print that answer.

= 0.1.0 =
* Settings screen, WP-CLI command, search box, keyboard search, and optional chat button.
