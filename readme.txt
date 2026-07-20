=== AI Image Disclosure & Labels ===
Contributors: geralddrissner
Tags: ai, image, label, badge, transparency
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds responsive AI disclosure labels and compact symbols to selected Gutenberg images and featured images.

== Description ==

AI Image Disclosure & Labels adds a visible AI disclosure label to images — but only when an editor explicitly enables it. The plugin never marks images automatically, and existing content remains untouched.

**Why label AI images?**

Article 50 of the EU AI Act includes transparency obligations for providers and deployers of certain AI systems, including visible disclosure of deep fakes and certain other AI-generated content. These rules apply from August 2, 2026. This plugin helps site owners place a clear, visible disclosure directly on selected images.

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

== Changelog ==

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
* Consolidates responsive text and symbol modes, accessible compact-symbol popovers, automatic badge colors, custom symbols, theme integration and cache integrations.
* Refined the EU AI Act wording to describe Article 50 support without implying automatic legal compliance.
* Added public project and author URLs.

= 1.7.3 =
* Added narrowly scoped PHPCS annotations for five documented third-party cache purge hooks.
* Fixed Plugin Check `PrefixAllGlobals.NonPrefixedHooknameFound` warnings without renaming or breaking the external cache integrations.

= 1.7.2 =
* Aligned the plugin directory slug, main plugin filename and translation text domain with `ai-image-disclosure-labels`.
* Fixed all Plugin Check `WordPress.WP.I18n.TextDomainMismatch` errors in PHP and JavaScript translations.
* Preserved the existing internal option names, metadata keys, REST namespace and hook prefixes so upgrades retain all settings and marked images.
* Prevented clicks on auto-color fallback fields while keeping their values submitted and stored.

= 1.7.1 =
* Fixed cache clearing after global settings changes: all supported cache integrations now run, not only WP Rocket.
* Fixed the frontend font selector being overridden by a later inherit declaration, and applied the selected font to the block-editor preview.
* Fixed automatic-color mode potentially resetting custom fallback colors because visually disabled color inputs were omitted from the form submission.
* Replaced the approximate brightness threshold with proper sRGB relative-luminance and contrast-ratio calculations for dark versus light text.
* Automatic-color cache data is now invalidated when WordPress regenerates or updates attachment metadata.
* Corrected the detached badge-HTML docblock.
* Replaced the native tooltip button with a keyboard-accessible span trigger, avoiding invalid button-inside-link markup when a theme wraps the complete featured image output in a link.
* Improved cache-system detection for Nginx Helper and Hummingbird, without claiming direct Cloudflare purging when only the standalone Cloudflare plugin is present.

= 1.7.0 =
* New: automatic badge color. The badge can take the average color of each image so it blends in; text switches between dark and light automatically for readability (WCAG relative luminance). Computed once per image on the server and cached; a canvas-based fallback covers JavaScript-created labels and servers without the GD extension. Fixed colors remain the fallback whenever a color cannot be determined.
* New: font selection for the badge — inherit from theme (default), system sans-serif, serif, monospace, or a custom font stack. All options resolve locally on the visitor's device; no font is ever loaded from Google Fonts or any external server (GDPR-friendly, zero loading time).
* New: broad cache support. Saving settings or changing a label now clears WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround Optimizer, Cache Enabler, Breeze, Nginx Helper and Hummingbird automatically; a gdaiidl_purge_caches action lets developers hook in other systems. The settings screen shows which cache was detected.
* New: EU AI Act information panel on the settings screen summarising the Article 50 disclosure obligation that applies from August 2, 2026.
* Refined settings screen: clearer section rhythm, focus states, disabled states for fixed colors while automatic color is active, and a responsive single-column layout on narrow screens.
* Added the missing translators comments for translatable strings with placeholders.
* Uninstall now also removes the cached per-image color metadata.

= 1.6.0 =
* Added an optional disclosure popover for compact AI symbols.
* Desktop users can reveal it by hovering or focusing the symbol.
* Touch users can tap to open or close it; tapping elsewhere or pressing Escape closes open popovers.
* Added accessible button semantics and aria-expanded state for interactive symbols.
* Added an interactive popover preview to the settings page.

= 1.5.3 =
* Replaced the broken static responsive-class hiding introduced in the previous 1.5.2 package.
* Keeps the initial hidden state and both reveal paths together in the dynamic page CSS, preventing cache or stylesheet-order mismatches from hiding every label.
* Container-query frames reveal the correct state before first paint; non-container theme wrappers reveal only after JavaScript measurement.
* Removed the obsolete `gd-ai-label-responsive` markup and duplicate visibility rules from the static stylesheet.

= 1.5.2 =
* Fixed a brief flash of the full text label on small images inside theme wrappers that are not CSS query containers. Labels now start hidden while width thresholds are active: container-query frames reveal them before first paint, and JavaScript-measured frames reveal them only after measurement, already in their correct form (text, symbol, or hidden).
* Cleaned up a stray doc comment introduced in 1.5.1.

= 1.5.1 =
* Refined the WordPress.org package and settings copy.
* Translated remaining visible PHP strings properly.
* Added cache purging after featured-image label updates to help WP Rocket and Cloudflare APO users.

= 1.5.0 =
* Prepared for the WordPress.org plugin directory.
* All interface strings are now in English and translation-ready (i18n via the ai-image-disclosure-labels text domain, including editor and admin scripts).
* New "Theme integration" setting: add custom CSS selectors for featured images, one per line, without writing code.
* Icon markup in the settings screen is now escaped through wp_kses with a strict allowlist.
* The generic featured-image fallback no longer forces display or width changes onto unknown theme wrappers; those are measured by JavaScript instead.
* The DOM observer for dynamically inserted content is now batched to avoid overhead with page builders and infinite scroll.
* The symbol-size input now renders the correct min/max limits for the selected unit.
* Added uninstall.php: deleting the plugin removes all options and post metadata, including on multisite.
* Added the License URI to the plugin header and readme.

= 1.4.0 =
* Fixed the text-to-symbol transition so container-query capable browsers no longer depend on delayed JavaScript.
* Removed the initial text-mode class that could keep the SVG hidden after the text disappeared.
* Added live symbol preview in the settings.
* Added three built-in SVG choices: AI Monogram, Sparkle and Chip.
* Added a custom PNG/SVG selector through the WordPress Media Library.
* Added configurable symbol sizing in pixels or as a percentage of the rendered image width.
* Fixed size re-evaluation when minimum image width is 0 but a text threshold is set.
* Fixed the admin preset-state flag on early returns.
* Added per-request settings caching and corrected option autoload behavior.
* Clears WP Rocket's domain cache after settings changes and excludes the frontend fallback from Delay JavaScript Execution.
* Added filters for supported post types and featured-image fallback selectors.
* Revalidates CSS values at output time.

= 1.3.0 =
* Added configurable icon-only mode for medium-size images, with minimum width thresholds and selectable AI icons.

= 1.2.0 =
* Added a configurable minimum rendered image width for labels.
* Default threshold is 180px; 0 disables the size filter.
* Uses the actual on-page image width, not attachment dimensions or image-size names.
* Automatically rechecks labels when responsive layouts resize images.
* Prevents badges from covering tiny archive and sidebar thumbnails.

= 1.1.1 =
* Removed per-badge inline styling and all !important declarations.
* Preserved deterministic server-side preset resolution.
* Moved configurable visual values to namespaced CSS custom properties.
* Enqueued frontend styles late to avoid normal theme-order conflicts.

= 1.1.0 =
* Rebuilt preset handling so Subtle, Light and Pill are deterministic server-side presets.
* Added explicit Custom mode for manually edited design values.
* Added preset classes to rendered labels and the featured-image JavaScript fallback.
* Fixed the Light preset permanently: white background, dark text and fine dark border.

= 1.0.5 =
* Fixed the Light preset retaining dark colors when its radio button was already selected.
* Clicking any preset card now always reapplies all of that preset's design values.
* Automatically repairs the exact broken Light-preset state created by version 1.0.4 without changing custom designs.

= 1.0.4 =
* Fixed the light badge background and protected badge styling against theme overrides.
* Added compact 9px versions of all three presets.
* Safely migrates untouched legacy presets while preserving customized designs.

= 1.0.3 =
* Featured-image settings now use a dedicated authenticated REST endpoint.
* Prevents WordPress editor meta state from overwriting the toggle during post updates.
* Adds explicit loading, saving, saved and error states in the editor panel.

= 1.0.2 =
* Fixed featured-image toggle persistence in the block editor.
* Uses the WordPress core-data entity API for saving featured-image settings.

= 1.0.1 =
* Added a theme-independent featured-image fallback for themes that bypass post_thumbnail_html.

= 1.0.0 =
* Initial release.
