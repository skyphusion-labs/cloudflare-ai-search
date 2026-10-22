# Cloudflare Workers AI, Gateway, and Search

WordPress plugin for [Workers AI](https://developers.cloudflare.com/workers-ai/), [AI Gateway](https://developers.cloudflare.com/ai-gateway/), and [AI Search](https://developers.cloudflare.com/ai-search/).

Visitors get a search box, a Ctrl+K or Command+K search window, and an optional chat button. The person running the site connects search from **Settings, then Cloudflare AI**. The same settings can be changed with WP-CLI.

WordPress and WooCommerce call Workers AI through the WordPress AI Client. The provider id is `cloudflare`. Text, vision, JSON, tools, images, and speech are on that provider. Embeddings and the public AI Search endpoint are callable from plugin code. The plugin does not take over another provider that is already configured. A caller that wants Cloudflare uses `using_provider('cloudflare')`.

The search box does not send an API token. Workers AI uses the token from **Settings, then Connectors**, or from the `CLOUDFLARE_API_KEY` environment variable or constant. This plugin does not store the token.

GPL-2.0-or-later. Requires WordPress 6.4 and PHP 8.0. The AI Client provider registers on WordPress 7.0 and later, when the text and image generation interfaces are present. Search and the embeddings helper still run without those interfaces.

## Connect a site

1. In the Cloudflare dashboard, create an AI Search instance that crawls the site, and turn on the public endpoint.
2. In WordPress, open **Settings, then Cloudflare AI**.
3. Paste the address (`https://something.search.ai.cloudflare.com/`) and save.
4. Choose the search box, the keyboard search, and the chat button. Save again.
5. Press **Check connection**.

The same screen links to Workers AI, AI Gateway, and AI Search in the dashboard and in the docs. Paste the account id (32 hex characters) there. Leave the gateway blank to call `api.cloudflare.com` directly, or set a gateway slug to send model calls through `gateway.ai.cloudflare.com`. Then open **Settings, then Connectors** and save the Workers AI API token for the Cloudflare provider. You can also put the account id in front of the token as `account-id:api-token`. Only the token half is sent. `CLOUDFLARE_ACCOUNT_ID` is accepted from the environment, then from a PHP constant, when the setting is empty.

Put the search box in one spot with the shortcode `[cloudflare_ai_search]`, and set placement to "Only where I place the shortcode".

Search results stay as WordPress or WooCommerce left them until **Show an AI Search answer above WordPress and WooCommerce search results** is on. That switch is off by default. A failed request is not shown to visitors. The cart and checkout stay quiet when that option is on.

If the box does not appear, the site's content security policy has to allow `https://*.search.ai.cloudflare.com` for scripts and connections.

## WordPress and WooCommerce

```php
wp_ai_client_prompt( 'Write a product summary.' )->using_provider( 'cloudflare' );

$vectors = cloudflare_ai_provider_embeddings( array( 'linen shirt' ) );

$answer = cloudflare_ai_search_query( 'shipping times' );
```

`cloudflare_ai_provider_embeddings()` returns a list of vectors, or a `WP_Error` whose message does not include the token. The model is `@cf/baai/bge-large-en-v1.5`. `cloudflare_ai_search_query()` posts to the public `{endpoint}/search` address and returns `answer` and `sources`, or a `WP_Error`. It does not send a token.

WooCommerce code that calls `wp_ai_client_prompt()` can use the same provider. The shop already shows the search box when the public endpoint is set.

The default text model is `@cf/meta/llama-3.3-70b-instruct-fp8-fast`. A prompt that includes an image uses `@cf/mistralai/mistral-small-3.1-24b-instruct` first. `@cf/meta/llama-3.2-11b-vision-instruct` needs a one-time `{"prompt":"agree"}` request on that model before it will answer, and it does not take tools or JSON. The default image model is `@cf/black-forest-labs/flux-1-schnell` and it takes a JSON prompt. FLUX.2 dev and FLUX.2 klein 4B are also listed; their docs say multipart, and this plugin still sends a JSON prompt, so only those two fail if Cloudflare rejects it. Speech is `@cf/deepgram/aura-1` (MP3). Voices: angus, asteria, arcas, orion, orpheus, athena, luna, zeus, perseus, helios, hera, stella.

## Command line

`wp cloudflare-ai-search` writes the same option the dashboard saves. A later save in **Settings, then Cloudflare AI** shows those values.

```bash
wp cloudflare-ai-search status
wp cloudflare-ai-search set --endpoint=https://abc.search.ai.cloudflare.com/ --color=#1B3A32 --chat=0
wp cloudflare-ai-search set --account-id=0123456789abcdef0123456789abcdef --gateway-id=store --lead-search=0
```

Flags: `--endpoint`, `--search-bar`, `--modal`, `--chat`, `--placement` (`top` or `shortcode`), `--hide-checkout`, `--placeholder`, `--color`, `--theme` (`auto`, `light`, or `dark`), `--max-results`, `--hide-branding`, `--account-id`, `--gateway-id`, `--lead-search`. Use `0` or `1` for the on and off flags. Omitted flags stay as they are. `--color` is read from the raw arguments because WP-CLI reserves that flag.

The chat button answers in a sentence and uses more of the Cloudflare allowance. The search box only lists pages. Leave `--chat=0` unless that cost is accepted.

## External services

- AI Search. The browser loads the snippet from the public address you paste. The site sends a question to `{endpoint}/search` when you check the connection, when code calls `cloudflare_ai_search_query()`, and when the search-results switch is on. No API token is sent.
- Workers AI and AI Gateway. Prompts, images, speech text, and embedding texts go to `api.cloudflare.com`, or to `gateway.ai.cloudflare.com` when a gateway id is set. The request uses the Connectors API token as a bearer. Account ids and gateway ids are not secrets. The token is the Connectors option `connectors_ai_cloudflare_api_key`, or `CLOUDFLARE_API_KEY` from the environment and then the PHP constant.

## Develop

```bash
php tests/endpoint-test.php
```

The test does not need WordPress. It checks the AI Search address, the Workers AI and AI Gateway URLs, the credential split, and the response parsers. It uses stand-in account ids and tokens, never a real secret.
