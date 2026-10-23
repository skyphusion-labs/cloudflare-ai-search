# Skyphusion AI for Cloudflare

WordPress plugin for [Workers AI](https://developers.cloudflare.com/workers-ai/), [AI Gateway](https://developers.cloudflare.com/ai-gateway/), and [AI Search](https://developers.cloudflare.com/ai-search/).

Visitors get a search box. They can also open the keyboard search with Ctrl+K or Command+K. You can turn on a chat button as well. You connect search from **Settings, then Skyphusion AI**. You can set the same options with WP-CLI.

WordPress and WooCommerce call Workers AI through the WordPress AI Client. The provider id is `cloudflare`. That provider covers text, vision, JSON, tools, images, and speech. Plugin code can also call embeddings and the public AI Search endpoint. This plugin does not take over another provider that is already set up. A caller that wants Cloudflare uses `using_provider('cloudflare')`.

The search box does not send an API token. Workers AI reads the `CLOUDFLARE_API_KEY` environment variable first. It then tries a PHP constant of the same name. It falls back to the token from **Settings, then Connectors**. This plugin does not store the token.

The license is GPL-2.0-or-later. The plugin needs WordPress 6.4 and PHP 8.0. The AI Client provider registers on WordPress 7.0 and later. It needs the text and image generation interfaces to be present. Search and the embeddings helper still run without those interfaces.

## Connect a site

1. In the Cloudflare dashboard, create an AI Search instance that crawls the site. Turn on the public endpoint.
2. In WordPress, open **Settings, then Skyphusion AI**.
3. Paste the endpoint address (`https://something.search.ai.cloudflare.com/`) and save.
4. Choose the search box, the keyboard search, and the chat button. Save again.
5. Press **Check connection**.

The same screen links to Workers AI, AI Gateway, and AI Search. It links to both the dashboard and the docs. Paste the account id there. The account id is 32 hex characters.

Leave the gateway blank to call `api.cloudflare.com` directly. Set a gateway id to send model calls through `gateway.ai.cloudflare.com` instead.

Then open **Settings, then Connectors**. Save the Workers AI API token for the Cloudflare provider. You can also put the account id in front of the token as `account-id:api-token`. Only the token half is sent. The plugin accepts `CLOUDFLARE_ACCOUNT_ID` from the environment, then from a PHP constant, when the setting is empty.

Put the search box in one spot with the shortcode `[cloudflare_ai_search]`. Then set placement to "Only where I place the shortcode".

Search results stay as WordPress or WooCommerce left them by default. They change once you turn on **Show an AI Search answer above WordPress and WooCommerce search results**. That switch is off by default. Visitors never see a failed request. The cart and checkout stay quiet when that option is on.

If the box does not appear, check the site's content security policy. It has to allow `https://*.search.ai.cloudflare.com` for scripts and connections.

## WordPress and WooCommerce

```php
wp_ai_client_prompt( 'Write a product summary.' )->using_provider( 'cloudflare' );

$vectors = cloudflare_ai_provider_embeddings( array( 'linen shirt' ) );

$answer = cloudflare_ai_search_query( 'shipping times' );
```

`cloudflare_ai_provider_embeddings()` returns a list of vectors, or a `WP_Error`. That error message never includes the token. The model is `@cf/baai/bge-large-en-v1.5`.

`cloudflare_ai_search_query()` posts to the public `{endpoint}/chat/completions` address. It returns `answer` and `sources`, or a `WP_Error`. It does not send a token. `{endpoint}/search` returns pages and no sentence. The connection check uses that path.

WooCommerce code that calls `wp_ai_client_prompt()` can use the same provider. The shop already shows the search box when the public endpoint is set.

A single product page also prints its stock as text. Each variation names its attributes, its SKU, its price, and its stock. A simple product names its stock. The text sits in the page itself. A crawler that does not run JavaScript can index it.

Out-of-stock and hidden variations are included, because the index has to see them. The plugin reads the rows with `get_children()` rather than `get_available_variations()`. That second method drops those rows when the catalog hides them.

The plugin prints the section inside `main`, once per request. On a block theme it sits next to the add to cart form. On a classic theme it goes on `woocommerce_single_product_summary`. The section does not turn on browser rendering, image indexing, OCR, or query rewriting.

The plugin reads at most 100 variations per page, because each row costs one product load. Raise the cap on a store that needs every row indexed:

```php
add_filter( 'cloudflare_ai_search_stock_max_variations', fn() => 400 );
```

The visible strings go through `__()` and `_n()` on the `skyphusion-ai` text domain. Those strings are `Stock`, `In stock.`, `Out of stock.`, `On backorder.`, `SKU %s.` and the unit count. The `cloudflare-ai-search-stock` class name does not, because a stylesheet and a crawler match on it.

The default text model is `@cf/meta/llama-3.3-70b-instruct-fp8-fast`. A prompt that includes an image uses `@cf/mistralai/mistral-small-3.1-24b-instruct` first. `@cf/meta/llama-3.2-11b-vision-instruct` needs a one-time `{"prompt":"agree"}` request on that model before it will answer. It does not take tools or JSON.

The default image model is `@cf/black-forest-labs/flux-1-schnell`, and it takes a JSON prompt. FLUX.2 dev and FLUX.2 klein 4B are also listed. Their docs say multipart. This plugin still sends a JSON prompt. So only those two fail if Cloudflare rejects it.

Speech is `@cf/deepgram/aura-1` (MP3). Voices: angus, asteria, arcas, orion, orpheus, athena, luna, zeus, perseus, helios, hera, stella.

## Command line

`wp cloudflare-ai-search` writes the same option the dashboard saves. A later save in **Settings, then Skyphusion AI** shows those values.

```bash
wp cloudflare-ai-search status
wp cloudflare-ai-search set --endpoint=https://abc.search.ai.cloudflare.com/ --color=#1B3A32 --chat=0
wp cloudflare-ai-search set --account-id=0123456789abcdef0123456789abcdef --gateway-id=store --lead-search=0
```

Flags:

- `--endpoint`, `--placeholder`, `--color`, `--max-results`
- `--search-bar`, `--modal`, `--chat`, `--hide-checkout`, `--hide-branding`, `--lead-search`
- `--placement`, which takes `top` or `shortcode`
- `--theme`, which takes `auto`, `light`, or `dark`
- `--account-id`, `--gateway-id`

Use `0` or `1` for the on and off flags. Omitted flags stay as they are. The plugin reads `--color` from the raw arguments, because WP-CLI reserves that flag.

The search box only lists pages. The chat button answers in a sentence. It uses more of the Cloudflare allowance. Leave `--chat=0` unless that cost is accepted.

## External services

- AI Search. The browser loads the snippet from the public endpoint address you paste. The site sends a question to `{endpoint}/search` when you check the connection. `cloudflare_ai_search_query()` and the search-results switch send the question to `{endpoint}/chat/completions`. No API token is sent. Search feeds and embeds do not print the answer.
- Workers AI and AI Gateway. Prompts, images, speech text, and embedding texts go to `api.cloudflare.com`. They go to `gateway.ai.cloudflare.com` instead when a gateway id is set. The request carries the Connectors API token as a bearer token. Account ids and gateway ids are not secrets. The plugin reads that token from `CLOUDFLARE_API_KEY` in the environment first. It then tries a PHP constant of the same name. The Connectors option `connectors_ai_cloudflare_api_key` is the fallback.

## Directory

The display name is Skyphusion AI for Cloudflare. Submit it under the permalink `skyphusion-ai`. That permalink is permanent, and it must not begin with `cloudflare`. The text domain is `skyphusion-ai`, the same as that permalink.

The GitHub repository name, the settings page slug, and the option key stay `cloudflare-ai-search`. That way an existing install keeps its settings. `Contributors` in readme.txt stays the wordpress.org username `skyphusion`.

## Develop

```bash
php tests/endpoint-test.php
```

The test does not need WordPress. It defines ABSPATH before it loads the endpoint and route files, so their direct-access guards stay in place. It checks the AI Search address, the Workers AI and AI Gateway URLs, the credential split, and the response parsers. It uses stand-in account ids and tokens, never a real secret.

The test also covers the stock text builder. It checks the row wording. It checks that an out-of-stock variation survives. It checks that a negative quantity prints its status without a count. It checks that a translated label really goes through `__()`. It also checks that neither product data nor a translation file can open a tag. `tests/stock-test.php` reads the ABSPATH the harness already defined, and does not set its own.

## Distribution

A distributed build carries the runtime only. That is `cloudflare-ai-search.php`, `includes/`, `assets/`, `readme.txt`, `README.md`, `LICENSE` and `uninstall.php`. The test files, the workflow and the dotfiles stay out of it.

`tests/endpoint-test.php` and `tests/account-order.php` define `ABSPATH` themselves, so the harness can load `includes/` directly. That is the opposite of what a shipped file should do. `tests/provider-test.php` also calls `shell_exec()`. None of that belongs in an archive a site owner installs.

Two files hold that list, because two mechanisms build archives here. Neither one reads the other's file.

- `.distignore` is read by `wp dist-archive` and by the WordPress.org deploy action.
- `.gitattributes` (`export-ignore`) is what GitHub applies to the "Source code" zip and tarball. GitHub generates those for every tag and release. They are published automatically and cannot be removed. Without `export-ignore` they carry the test files, whatever the attached asset contains.

Keep the two lists in step. To check what a build would contain:

```bash
git archive --format=zip --prefix=cloudflare-ai-search/ HEAD | tar -tf - 2>/dev/null || \
  git archive --format=tar --prefix=cloudflare-ai-search/ HEAD | tar -tf -
```
