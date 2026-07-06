=== AI Tools ===
Contributors: astappiev
Tags: alt text, accessibility, image seo, wcag, ai
Requires at least: 4.6
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 0.0.1
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Automatically generate WCAG-friendly image alt text with AI (OpenAI & Anthropic) to boost accessibility compliance (ADA/EAA) and image SEO.

== Description ==
AI Tools automatically writes clear, descriptive alt text for every image on your WordPress site — improving **accessibility compliance** (WCAG 2.2, ADA, Section 508, and the European Accessibility Act) and **image SEO** at the same time. It uses leading vision AI from OpenAI and Anthropic with your own API key, so generation is transparent and at-cost with no per-image fees or vendor lock-in. You choose the provider and model — the plugin ships with a fast, low-cost default for each.

Alt text is required for accessible, legally compliant websites, and it helps search engines understand your images. But writing it by hand across an entire media library rarely happens — so this plugin does it for you, in bulk or automatically on upload.

It produces concise, **WCAG-aligned** descriptions (no "image of…" filler, sensible length) and can fold in the **page context** and your **SEO focus keyphrase** for sharper, more relevant alt text. It pairs perfectly with accessibility audit tools that flag missing alt text — this is the plugin that fills the gaps.

**Key Features:**
- **Accessibility & compliance**: WCAG-aligned output to support ADA, EAA, and Section 508 requirements
- **SEO keyphrase integration**: automatically weaves in focus keyphrases from Yoast SEO, Rank Math, and SEOPress (without keyword stuffing)
- **Page-context aware**: uses the page/post the image belongs to for more relevant descriptions
- **Multi-Provider Support**: choose between OpenAI and Anthropic — your own API key, no lock-in
- **Cost-Effective by default**: ships with a fast, low-cost vision model for each provider — and you can switch to any model your provider offers
- **Bulk Processing**: generate alt text for your whole library at once, or automatically on upload
- **Custom Prompts**: tailor the AI prompt to your brand and needs
- **Multi-Language Support**: generate alt text in many languages
- **Testing Feature**: preview prompts before applying them to images
- **WP-CLI Support**: configure providers and bulk-generate from the command line
- **Developer-friendly**: extensible via action/filter hooks for custom integrations and add-ons

**WP-CLI:**
The plugin registers a `wp ai-alt-text` command suite, making it easy to automate alt text generation across one or many sites.

    # Configure a provider and API key
    wp ai-alt-text activate --provider=openai --key=sk-xxxxxxxx

    # Bulk-generate alt text for all images missing it
    wp ai-alt-text generate

    # Regenerate alt text for specific attachments
    wp ai-alt-text generate --ids=12,34,56 --force

    # Preview what would be processed without calling the API
    wp ai-alt-text generate --limit=20 --dry-run

    # Show current configuration and coverage
    wp ai-alt-text status


**New in Latest Version:**
- Optional managed-credit mode — generate alt text with no API key needed (free tier included)
- WCAG-aligned output, SEO focus-keyphrase integration (Yoast / Rank Math / SEOPress), and page-context awareness
- Optionally set the image Title, Caption, and Description from the generated alt text
- Future-proof model handling (no hard-coded model versions; configurable defaults)

Important: This plugin uses external AI services (BYOK) to generate alt text.

== Installation ==

1. Upload the plugin files to the /wp-content/plugins/wp-ai-tools directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Obtain an API key from your preferred provider:
   - **OpenAI**: Visit https://openai.com and sign up to get your API key
   - **Anthropic**: Visit https://console.anthropic.com and sign up to get your API key
4. Navigate to the 'Alt Text Generator' admin page in your WordPress dashboard.
5. Select your preferred AI provider and configure it with your API key.
6. Customize your prompt and language settings as needed.

== Frequently Asked Questions ==

= Does this plugin require an API key? =
Yes, you need an API key from your preferred AI provider to use the plugin.

= What AI providers are supported? =
You bring your own API key for **OpenAI**, **Google** or **Anthropic**, and you can use any vision-capable model your provider offers. The plugin ships with a sensible, low-cost default for each provider and lets you change the model anytime in settings — so you're never locked to a specific model if providers add or retire one.

= Can I switch between different AI providers? =
Yes, you can easily switch between OpenAI and Anthropic providers in the plugin settings. Each provider has its own API key configuration.

= How does this plugin use the AI APIs? =
The plugin sends images to your selected AI provider's API, which then returns the generated alt text. This process requires an active internet connection and the transmission of data to the AI provider's servers.

= Can I generate alt text for multiple images at once? =
Yes, it supports bulk processing of images for an efficient workflow.

= Can I use a custom prompt? =
Yes, you can customize the prompt used to generate alt text in the plugin settings. You can also test your prompts before applying them to images.

= Which provider is more cost-effective? =
Both providers offer fast, low-cost vision models, and the plugin defaults to a cost-efficient model for each. You can switch models anytime in settings to balance cost and quality.

= Can I use the plugin from the command line (WP-CLI)? =
Yes. The plugin registers a `wp ai-alt-text` command suite with three subcommands:
- `wp ai-alt-text activate --provider=<openai|anthropic> --key=<api-key>` configures the provider and API key. Add `--skip-validation` to save without a live API check. (Each provider uses a fixed default model.)
- `wp ai-alt-text generate` bulk-generates alt text. Useful flags: `--limit=<n>`, `--provider=<provider>`, `--force` (regenerate existing), `--ids=12,34` (specific attachments), `--dry-run` (preview only), and `--yes` (skip confirmation).
- `wp ai-alt-text status` shows the active provider, which keys are configured, the prompt/language, and alt text coverage counts.

= Is my data secure? =
The plugin only sends image data and prompts to the selected AI provider for processing. Please review the privacy policies of OpenAI and/or Anthropic for details on how they handle data.

== Changelog ==

= 0.0.1 =
- Initial release

== Upgrade Notice ==

= 2.2.0 =
Adds WP-CLI support: configure providers and bulk-generate alt text from the command line (wp ai-alt-text activate|generate|status).

== External Service Usage Disclosure ==

This plugin uses external AI services to generate alt text. Data (images and their metadata) is sent to your selected AI provider for processing.

**Supported Services:**
- **OpenAI**: For more information, please review the [OpenAI Terms of Use](https://openai.com/terms/) and [Privacy Policy](https://openai.com/privacy/)
- **Anthropic**: For more information, please review the [Anthropic Terms of Service](https://www.anthropic.com/terms) and [Privacy Policy](https://www.anthropic.com/privacy)

You can choose which service to use and are only required to agree to the terms of the service you select. The plugin does not store your images or generated alt text on our servers.

== Support ==

For support, feature requests, or bug reports, please contact us through the WordPress plugin support forum.
