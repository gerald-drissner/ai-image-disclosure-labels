=== AI Image Disclosure & Labels ===
Contributors: geralddrissner
Donate link: https://www.paypal.com/paypalme/drissner
Tags: ai image, ai generated content, image disclosure, content transparency, eu ai act
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds responsive AI image disclosure labels and compact symbols to selected Gutenberg and featured images.

== Description ==

AI Image Disclosure & Labels adds a visible AI disclosure label to images — but only when an editor explicitly enables it. The plugin never marks images automatically, and existing content remains untouched.

**Why label AI images?**

Article 50 of the EU AI Act includes transparency obligations for providers and deployers of certain AI systems, including visible disclosure of deep fakes and certain other AI-generated content. Relevant Article 50 obligations apply from August 2, 2026. This plugin helps site owners place a clear, visible disclosure directly on selected images.

**Features**

* Per-image switch for Gutenberg Image blocks.
* Separate per-post/per-page switch for the featured image.
* Optional custom text for each marked image.
* Global editable default text.
* Four positions and three design presets, plus detailed design controls with live preview.
* Responsive behavior based on the actually rendered image width: hide the label on tiny thumbnails, show a compact AI symbol on medium images, and the full text label on large images.
* Three built-in SVG symbols (AI Monogram, Sparkle, Chip) plus a custom PNG/SVG symbol from the Media Library.
* Symbol size in pixels or as a percentage of the rendered image width.
* Optional disclosure popover for compact symbols: hover or keyboard focus on desktop, tap-to-toggle on touch devices.
* Theme integration: works with the WordPress default themes and common featured-image markup out of the box; unusual themes can be supported by adding CSS selectors in the settings — no code required. Developers can additionally use the `gdaiidl_featured_selectors` and `gdaiidl_post_types` filters.
* Compatible with common page caches: labels are rendered server-side where possible, and supported page caches are cleared automatically when settings or labels change.
* Clean uninstall: removes all options and post metadata when the plugin is deleted.

**Important legal note**

This plugin provides a *visible* disclosure label on images. It does not embed machine-readable markings or metadata (such as C2PA/content credentials), which Article 50(2) of the EU AI Act requires from *providers* of generative AI systems. Whether and how specific content must be labeled is a legal question that depends on your role, your content and your jurisdiction. This plugin is a tool, not legal advice.

== Installation ==

1. Upload the ZIP through Plugins > Add Plugin > Upload Plugin, or install it from the plugin directory.
2. Activate AI Image Disclosure & Labels.
3. Open Settings > AI Image Labels and choose the text, position and design.
4. In the block editor, select an Image block and open "AI image label".
5. For a featured image, use "AI label for featured image" in the document settings sidebar.

== Screenshots ==

1. Settings overview with EU AI Act information, default label text, layout presets, cache detection and live previews.
2. Gutenberg Image block settings with the per-image label toggle and optional custom text.
3. Full text disclosure label displayed on a large frontend image.
4. Compact symbol-only disclosure displayed on a medium-sized frontend image.
5. Accessible disclosure popover opened from the compact symbol.

== Frequently Asked Questions ==

= Does the plugin mark images automatically? =

No. The plugin never marks images automatically. Existing posts, pages and images remain unchanged unless an editor explicitly enables a label.

= Does this make my site compliant with the EU AI Act? =

The plugin supports the visible disclosure of AI-generated images. Legal compliance depends on your specific situation; please consult qualified legal counsel. The plugin does not add machine-readable content markings.

= My theme does not show the label on the featured image. What can I do? =

Open Settings > AI Image Labels > Theme integration and add the CSS selector of your theme's featured image (one per line), for example `.hero-media img.wp-post-image`. Developers can also use the `gdaiidl_featured_selectors` filter.

= Can visitors open the text behind a compact symbol? =

Yes. Enable the optional compact-symbol disclosure in Settings. On desktop, the text appears on hover or keyboard focus. On touch devices, visitors can tap the symbol to open it and tap again or elsewhere to close it.

= How does the automatic badge color work? =

When enabled, the badge uses the average color of each labeled image, computed once on the server and cached in the image's metadata. The text color switches between dark and light automatically so the disclosure stays readable. If the color cannot be determined (for example on external images), the fixed colors from the settings are used.

= Does the font option load anything from Google? =

No. All font choices are system font stacks or your own custom stack, resolved locally on the visitor's device. Nothing is ever requested from Google Fonts or any other external server.

= Which caching plugins are supported? =

The plugin automatically clears WP Rocket (including its Cloudflare add-on), LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround Optimizer, Cache Enabler, Breeze, Nginx Helper and Hummingbird whenever you save the settings or change a label. Other systems can be connected through the gdaiidl_purge_caches action.

= Can I use my own symbol? =

Yes. Choose "Custom symbol" and select a PNG or SVG file from the Media Library. SVG files can only be used if your installation safely allows SVG uploads; the plugin does not enable SVG uploads globally.

== Support ==

For support, visit [drissner.media/kontakt](https://drissner.media/kontakt). If the plugin is useful to you, you can support its development through [PayPal](https://www.paypal.com/paypalme/drissner).

== Changelog ==

= 2.0.3 =
* Added Donate and Support links to the WordPress Plugins screen and the plugin settings page.
* Added the current plugin version and developer credit to the settings page.
* Added the official WordPress.org donation link and explicit support information.

= 2.0.2 =
* Fixed the WP Rocket Delay JavaScript exclusion by deriving the frontend script path from the actual installed plugin directory.
* Replaced remaining internal script and style handles, the settings-page slug and Gutenberg registration identifiers with the `gdaiidl` prefix.
* Retained serialized block attribute names and established CSS classes for backward compatibility with existing posts and custom styling.

= 2.0.1 =
* Renamed all plugin-owned global identifiers to the unique `gdaiidl` prefix requested during the WordPress.org review.
* Updated option names, metadata keys, custom hooks, REST namespace, error codes, PHP constants, the main class and JavaScript configuration globals.
* Added a one-time compatibility migration for settings and metadata created by earlier GitHub releases.
* Kept documented third-party cache purge hooks unchanged because those names belong to the respective cache plugins.

= 2.0 =
* First public major release for WordPress.org and GitHub.
* Fixed automatic badge colors for featured images rendered on home, archive and index pages.
* Consolidated responsive text and symbol modes, accessible compact-symbol popovers, automatic badge colors, custom symbols, theme integration and cache integrations.
* Refined the EU AI Act wording to describe Article 50 support without implying automatic legal compliance.
* Added public project and author URLs.

For earlier development releases, see [CHANGELOG.md](https://github.com/gerald-drissner/ai-image-disclosure-labels/blob/main/CHANGELOG.md).
