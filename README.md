# Cloudflare AI Search

WordPress plugin that adds [Cloudflare AI Search](https://developers.cloudflare.com/ai-search/) to a site.

Visitors get a search box, a Ctrl+K or Command+K search window, and an optional chat button. The person running the site connects it from **Settings → AI Search**. The same settings can be changed with WP-CLI. The site does not need an API token.

GPL-2.0-or-later.

## Connect a site

1. In the Cloudflare dashboard, create an AI Search instance that crawls the site, and turn on the public endpoint.
2. In WordPress, open **Settings → AI Search**.
3. Paste the address (`https://something.search.ai.cloudflare.com/`) and save.
4. Choose the search box, the keyboard search, and the chat button. Save again.
5. Press **Check connection**.

Put the search box in one spot with the shortcode `[cloudflare_ai_search]`, and set placement to "Only where I place the shortcode".

If the box does not appear, the site's content security policy has to allow `https://*.search.ai.cloudflare.com` for scripts and connections.

## Command line

`wp cloudflare-ai-search` writes the same option the dashboard saves. A later save in **Settings → AI Search** shows those values.

```bash
wp cloudflare-ai-search status
wp cloudflare-ai-search set --endpoint=https://abc.search.ai.cloudflare.com/ --color=#1B3A32 --chat=0
```

Flags: `--endpoint`, `--search-bar`, `--modal`, `--chat`, `--placement` (`top` or `shortcode`), `--hide-checkout`, `--placeholder`, `--color`, `--theme` (`auto`, `light`, or `dark`), `--max-results`, `--hide-branding`. Use `0` or `1` for the on and off flags. Omitted flags stay as they are.

The chat button answers in a sentence and uses more of the Cloudflare allowance. The search box only lists pages. Leave `--chat=0` unless that cost is accepted.

## Develop

```bash
php tests/endpoint-test.php
```

The test does not need WordPress. It checks that only a Cloudflare AI Search public address is accepted.
