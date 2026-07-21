=== AI Image Disclosure & Labels ===
Contributors: geralddrissner
Donate link: https://www.paypal.com/paypalme/drissner
Tags: ai image, ai generated content, image disclosure, content transparency, eu ai act
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds visible AI image labels plus optional machine-readable source information to selected WordPress images.

== Description ==

AI Image Disclosure & Labels adds a visible AI disclosure label to images — but only when an editor explicitly enables it. The plugin never marks images automatically, and existing content remains untouched.

**Why label AI images?**

Article 50 of the EU AI Act distinguishes between providers and deployers of AI systems. Providers of certain generative AI systems must add machine-readable marks to generated or manipulated outputs. Separate disclosure duties apply to deployers in specified cases, including deepfakes and certain public-interest text. The relevant rules apply from August 2, 2026. This plugin helps site owners add a visible disclosure and an optional publisher-supplied structured-data declaration to selected images.

**Features**

* Per-image switch for Gutenberg Image blocks.
* Separate per-post/per-page switch for the featured image.
* Optional custom text for each marked image.
* Global editable default text.
* Optional machine-readable Schema.org `digitalSourceType` declarations using standardized IPTC source categories.
* Global default and per-image source type: created using generative AI or edited using generative AI.
* Four positions and three design presets, plus detailed design controls with live preview.
* Responsive behavior based on the actually rendered image width: hide the label on tiny thumbnails, show a compact AI symbol on medium images, and the full text label on large images.
* Three built-in SVG symbols (AI Monogram, Sparkle, Chip) plus a custom PNG/SVG symbol from the Media Library.
* Symbol size in pixels or as a percentage of the rendered image width.
* Optional disclosure popover for compact symbols: hover or keyboard focus on desktop, tap-to-toggle on touch devices.
* Theme integration: works with the WordPress default themes and common featured-image markup out of the box; unusual themes can be supported by adding CSS selectors in the settings — no code required. Developers can additionally use the `gdaiidl_featured_selectors` and `gdaiidl_post_types` filters.
* Compatible with common page caches: labels are rendered server-side where possible, and supported page caches are cleared automatically when settings or labels change.
* Clean uninstall: removes all options and post metadata when the plugin is deleted.

**Important legal note**

This plugin can provide both a visible disclosure label and an optional publisher-supplied Schema.org declaration in the page source. The structured data uses standardized IPTC Digital Source Type categories, but it does not modify the image file, create or verify C2PA Content Credentials, prove origin or authenticity, or replace machine-readable markings supplied by the provider of a generative AI system. Whether and how specific content must be labeled depends on your role, your content and the applicable law. This plugin is a tool, not legal advice.

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

The plugin supports visible disclosure and can optionally add a publisher-supplied Schema.org `digitalSourceType` declaration to the page source. This does not create verified provenance or C2PA Content Credentials and does not replace provider-supplied machine-readable marking. Legal compliance depends on your specific situation; please consult qualified legal counsel.

= What does the machine-readable option add? =

When enabled, the plugin outputs a Schema.org `ImageObject` declaration in the page source for each marked image. It is not visually displayed. The `digitalSourceType` value uses IPTC terminology for either content created using generative AI or content edited using generative AI. The declaration is supplied by the publisher and is not cryptographically verified.

= Does the machine-readable option create C2PA Content Credentials? =

No. It does not modify the image file, create or verify Content Credentials, or prove origin or authenticity. Existing provider-supplied credentials are not replaced.

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

= 2.0.4 =
* Added optional machine-readable Schema.org `digitalSourceType` declarations for marked images.
* Deduplicated responsive, cropped and Cloudflare-delivered renditions of the same WordPress attachment in the JSON-LD graph.
* Improved automatic image-derived colors for JavaScript-created featured-image labels, including cross-origin CDN delivery.
* Added standardized source categories for images created or edited using generative AI, with global defaults and per-image overrides.
* Updated the EU AI Act notice and legal explanations to distinguish provider marking duties from deployer disclosure duties.
* Clarified that the structured data is publisher-supplied and does not create or verify C2PA Content Credentials.

= 2.0.3 =
* Added Donate and Support links to the WordPress Plugins screen and the plugin settings page.
* Added the current plugin version and developer credit to the settings page.
* Added the official WordPress.org donation link and explicit support information.

= 2.0.2 =
* Fixed the WP Rocket Delay JavaScript exclusion by deriving the frontend script path from the actual installed plugin directory.
* Replaced remaining internal script and style handles, the settings-page slug and Gutenberg registration identifiers with the `gdaiidl` prefix.
* Retained serialized block attribute names and established CSS classes for backward compatibility with existing posts and custom styling.

For earlier development releases, see [CHANGELOG.md](https://github.com/gerald-drissner/ai-image-disclosure-labels/blob/main/CHANGELOG.md).
