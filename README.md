# AI Image & Video Disclosure Labels

<p align="center">
  <img src=".wordpress-org/icon-256x256.png" width="160" height="160" alt="AI Image & Video Disclosure Labels icon">
</p>

A WordPress plugin for visible AI image and video disclosures, attachment-level Media Library classification, optional machine-readable provenance metadata, and optional AI-assisted image analysis.

By default, the plugin does not label existing content automatically. Editors can mark individual image/video uses manually, classify reusable attachments in the Media Library, and optionally enable automatic visible disclosures for positively AI-classified attachments.

## 3.0.1 release hardening

3.0.1 is a maintenance release produced after running the official WordPress Plugin Check against 3.0.0. It makes the AJAX security boundary easier for static analysis to verify, replaces direct temporary-file deletion with WordPress APIs, tightens prepared SQL shapes, completes translator annotations, and keeps GitHub-only release documents out of the production package.

The optional direct OpenAI, Gemini, Anthropic and Cloudflare adapters remain intentional service integrations. WordPress 7 AI Client / Connectors is also supported. External service use is opt-in and documented in the WordPress.org `readme.txt`.

## Highlights in 3.0.x

- Independent top-level switches for **Images** and **Videos**; use either medium alone or both together.
- Media Library statuses: **AI-generated**, **AI-modified**, **No AI used**, or **Unclassified**.
- Reusable attachment classification inherited by Gutenberg Image and Video blocks and featured-image controls unless a per-use override is set.
- Video disclosures render immediately below the video rather than over the playback surface or native controls.
- Videos can inherit the image badge design or use a separate video design with independent alignment, colors, border, typography and padding; the settings page includes both image and video previews.
- Recommended choices are labelled directly in the settings UI, with the actual recommended value emphasized in explanatory text; automatic image-derived badge color is the fresh-install default.
- Separate editable default texts for AI-generated and AI-modified disclosures.
- Optional automatic visible labels for positively AI-classified Media Library attachments; off by default.
- Optional Schema.org `digitalSourceType` output using standardized IPTC categories, as `ImageObject` and `VideoObject` records.
- Experimental AI-assisted analysis with background bulk jobs, model discovery, compatibility testing and cost safeguards.
- WordPress 7.0+ **AI Client / Connectors** support, so configured provider credentials can be reused without storing a duplicate key in this plugin.
- Direct OpenAI, Gemini, Anthropic and Cloudflare Workers AI modes remain available, plus administrator-supplied OpenAI-compatible HTTPS and custom HTTPS endpoints.
- No hard-coded model IDs or provider price table.
- AI visual analysis is advisory and never automatically declares an image “No AI used”. Automated analysis remains image-only in the 3.0.x series; video classification is manual.
- Visual auto-classification uses an adjustable confidence threshold with a 95% recommended default. A separate custom-endpoint-only path is reserved for actual technical provenance/watermark verification.
- Automatic AI actions are presented as a clear optional group, with the confidence threshold visually attached to automatic classification rather than shown as a second nested settings card.

## Display features

- Per-image controls for Gutenberg Image blocks.
- Per-video controls for Gutenberg Video blocks, with the disclosure in normal flow below the video.
- Separate featured-image controls.
- Full text labels on large images and compact symbols on smaller images.
- Optional hover, keyboard-focus and tap-to-toggle disclosure popover.
- Three built-in SVG symbols plus custom PNG/SVG symbols from the Media Library.
- Automatic image-derived badge colors with contrast-aware text.
- Three image design presets and detailed visual controls, plus optional independent video badge styling.
- Location-specific icon-only and hidden rules using CSS selectors.
- Featured-image handling across homepages, archives, query blocks, cards and post loops.
- Theme integration and cache-clearing integrations for common WordPress caching plugins.
- Accessible keyboard and screen-reader behavior.

## Requirements

- WordPress 6.7 or later
- PHP 7.4 or later
- WordPress 7.0+ only for the optional AI Client / Connectors integration

## AI analysis and privacy

AI-assisted analysis is optional and disabled by default. When requested, the plugin sends a temporary resized copy of the selected image plus a short classification prompt from the WordPress server to the configured provider. The original Media Library file is not modified.

When **WordPress AI Client** mode is used, provider credentials stay in **Settings → Connectors** and are not copied into this plugin. Direct-provider modes can instead store their own server-side API credential or use `wp-config.php` constants. No AI-provider request is made merely because the plugin is installed, and public frontend page views do not call the analysis provider.

For custom/OpenAI-compatible integrations, endpoint URLs must use HTTPS. Remote AI responses and model catalogues are size-bounded, and a custom endpoint can trigger automatic technical-provenance application only by returning the literal JSON boolean `verified_provenance=true`. Ordinary vision-model confidence is never treated as verified provenance.

## Installation

1. Install the plugin ZIP through **Plugins → Add Plugin → Upload Plugin**, or install it from WordPress.org.
2. Activate **AI Image & Video Disclosure Labels**.
3. Open **Settings → AI Image & Video Labels**.
4. Classify reusable images or videos in **Media → Library**, or configure an Image block, Video block, or featured image directly in the editor.

## EU AI Act note

Article 50 of Regulation (EU) 2024/1689 includes transparency duties for providers and deployers of certain AI systems. The relevant transparency rules apply from 2 August 2026.

This plugin helps add visible disclosures and can optionally publish a self-declared Schema.org `digitalSourceType` value. It does not determine whether particular content must be labelled, modify media files, create or verify C2PA Content Credentials, prove origin or authenticity, or replace provider-supplied machine-readable markings. It is not legal advice.

## Development

The WordPress.org plugin slug and translation domain are:

```text
ai-image-disclosure-labels
```

Plugin-owned PHP globals, option names, metadata keys, custom hooks, REST routes, JavaScript globals, enqueue handles, settings slugs and editor registration identifiers use the unique `gdaiidl` prefix. Existing serialized block attributes and established `gd-ai-*` CSS selectors remain compatible with saved content.

Run the official Plugin Check workflow before every WordPress.org release.

## Support and contributions

Support: https://drissner.media/kontakt  
Donate: https://www.paypal.com/paypalme/drissner

Bug reports and focused pull requests are welcome through GitHub.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Maintainer

Gerald Drißner  
https://drissner.media/
