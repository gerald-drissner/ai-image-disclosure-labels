# Changelog

## 3.0.1

- Release-hardening update after the official WordPress Plugin Check run on 3.0.0.
- Consolidated AI-admin AJAX input handling so nonce verification, capability checks and sanitization are explicit at one request boundary.
- Replaced temporary-file `unlink()` calls with `wp_delete_file()`.
- Reworked Media Library MIME filtering to use fixed prepared query shapes and tightened preparation of the background AI job queries.
- Added targeted PHPCS rationale for the narrow admin-only direct database reads used by cursor-based bulk analysis.
- Added the missing translator comments and ordered translation placeholders.
- Removed remote documentation links from PHP admin markup while retaining complete external-service disclosure and policy links in `readme.txt`.
- Added/updated `.distignore` so GitHub-only release notes, audits and development files cannot enter the WordPress.org package.
- Updated Plugin Check CI to ignore only the `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration` advisory. Direct provider adapters are intentional optional service integrations; WordPress AI Client / Connectors is also supported.


## 3.0.0

> Major update since the previously published 2.1.4 release. The unpublished 2.2.x-2.5.x development builds are folded into 3.0.0.

- Renamed the public-facing plugin title to **AI Image & Video Disclosure Labels** while retaining the existing WordPress.org slug and text domain for compatibility.
- Added independent top-level enable switches for images and videos; disabled media types retain stored classifications but no longer expose normal disclosure controls/output.
- Added optional video-specific wording and a separate video badge design with left/center/right alignment, colors, opacity, border, radius, typography, padding and a dedicated live preview.
- Fixed the separate-video-design switch so its controls reveal immediately, removed redundant inherited/active status copy, and added file-aware cache busting across plugin CSS/JavaScript assets to prevent stale files during same-version test builds.
- Marked genuinely recommended choices directly in the UI and bolded the recommended value in explanatory text (for example **on**, **off**, **95%** or **1024 px**); automatic image-derived badge color is now the fresh-install default while existing saved choices are preserved.
- Polished the optional automatic-actions layout: upload analysis and automatic classification now use a consistent card hierarchy, with the confidence threshold visually grouped beneath automatic classification instead of appearing inside a nested/double-bordered box.
- Added attachment-level Media Library classification with simple publisher-facing statuses: AI-generated, AI-modified, no AI used, or unclassified, while preserving more precise edited/enhanced provenance where known.
- Extended the same publisher-facing classification to uploaded videos and Gutenberg Video blocks. Video disclosures are placed immediately below the playback surface so they do not obscure controls.
- Added optional machine-readable `VideoObject` `digitalSourceType` records alongside existing `ImageObject` output; automated AI analysis intentionally remains image-only in 3.0.0.
- Added Media Library list columns, filters and bulk actions, plus inheritance into Gutenberg Image and Video blocks and featured-image controls unless an explicit per-use override is set.
- Added separate editable default visible texts for AI-generated and AI-modified media; no automatic public label is shown for no-AI or unclassified states.
- Added optional automatic visible labels for positively AI-classified Media Library attachments; disabled by default.
- Added experimental AI-assisted image analysis through WordPress 7.0+ AI Client / configured Connectors, direct OpenAI, Google Gemini, Anthropic Claude and Cloudflare Workers AI adapters, OpenAI-compatible HTTPS APIs, and custom HTTPS endpoints.
- Added dynamic model discovery, alphabetical/searchable catalogues, manual model or policy entry, a compatibility test with a tiny built-in image, and a reset control. Model IDs are not hard-coded, ranked, renamed or silently substituted.
- Added background analysis jobs for selected images, all unclassified images or the entire Media Library, with progress, cancellation, small batches, image-count limits and known-cost safety limits.
- Added provider-independent cost/usage tracking without a bundled price table. Provider-reported cost, token usage, machine-readable pricing, manual rates and observed averages are supported; unknown costs remain explicitly unknown.
- Kept visual AI analysis advisory: it never automatically classifies an image as “No AI used”, and high-confidence auto-application is opt-in and limited to previously unclassified images. The auto-apply threshold is administrator-adjustable with a 95% recommended default and is explicitly described as model self-confidence rather than forensic probability.
- Added a custom-endpoint-only technical provenance path for real watermark/provenance verification without treating ordinary vision-model confidence as cryptographic proof.
- Analysis sends a temporary resized copy by default, never modifies the Media Library original, keeps credentials server-side, uses WordPress safe HTTP requests, and makes no AI-provider calls on public frontend page views.
- Hardened release input/output boundaries: visible disclosure text is capped server-side at 80 characters; custom and OpenAI-compatible endpoints must use HTTPS; technical provenance is accepted only from a literal JSON boolean `verified_provenance=true`; model catalogues are bounded; and remote AI/model responses have explicit size limits.
- Hardened local image processing with bounded file-size/read checks for badge-color sampling and temporary AI-analysis images, with cleanup retained on error paths.
- Improved conditional frontend loading by checking for relevant Image/Video blocks before parsing block trees, reducing unnecessary work on posts that cannot contain a disclosure.
- Reviewed privileged analysis actions for capability/nonce protection, kept API credentials server-side in non-autoloaded settings or `wp-config.php`, and confirmed there are no public unauthenticated AI-analysis AJAX handlers.
- Reorganized the settings page into Essential settings, Design and previews, Advanced options, Security/privacy/performance, and Experimental AI analysis.
- Improved Media Library controls, touch-first compact-symbol behavior, REST/save race handling, cache-purge efficiency, responsive admin styling, and WordPress 7.1 editor compatibility.
- Final polish from the second independent JS/CSS review: added a defensive admin field-wrapper null guard, debounced settings-preview resize updates, and explicit Windows High Contrast / forced-colors support for disclosure badges and previews.

## 2.1.4
- Added WordPress 7.1 compatibility for the always-iframed post editor by loading image-preview styles inside the editor canvas.
- No changes to saved block markup, frontend disclosure output, settings, or existing metadata.
## 2.1.3
- Added a recommended performance option that loads frontend CSS and JavaScript only on pages containing disclosure labels.
- Added automatic late-loading safeguards for disclosures rendered by Query blocks, post loops, themes and page builders.
- Added an explicit defer loading strategy for the frontend script.

## 2.1.2

- Finalized the user documentation for location-specific icon-only and hidden display rules.
- Clarified in the settings and documentation that location rules modify existing disclosures only and never mark images automatically.
- Documented the recommended custom-class workflow, homepage scoping and stable-selector guidance for changing, queried and randomly selected posts.
- Confirmed featured-image disclosure handling across homepages, archives, query blocks, cards and post loops.

## 2.1.1

- Fixed featured-image disclosures in archive cards, query blocks and other post loops when a theme renders the thumbnail through `wp_get_attachment_image()` instead of the standard post-thumbnail function.
- Ensured each rendered featured image is checked against the metadata of its own post, so changing or randomly selected posts receive the correct disclosure without post-specific selectors.
- Rewrote the location-rule guidance with neutral, reusable examples and clearer advice on icon-only versus hidden rules.


## 2.1.0
- Added selector-based location rules for icon-only and fully hidden disclosure labels.
- Added guidance for page-scoped, selector-based display rules.
- Location rules safely override responsive width logic and support dynamically inserted content.

## 2.0.4

- Added optional machine-readable Schema.org `digitalSourceType` declarations for marked images.
- Deduplicated responsive, cropped and Cloudflare-delivered renditions of the same WordPress attachment in the page-level JSON-LD graph.
- Made automatic image-derived colors reliable for JavaScript-created featured-image labels by reusing the server-sampled attachment color.
- Added standardized IPTC source categories for content created or edited using generative AI.
- Added global defaults and per-image overrides for Gutenberg and featured images.
- Updated the EU AI Act notice and legal wording to distinguish provider marking duties from deployer disclosure duties.
- Clarified that structured data is publisher-supplied and does not create or verify C2PA Content Credentials.

## 2.0.3

- Added Donate and Support links to the WordPress Plugins screen and the settings page.
- Added the current plugin version and developer credit to the settings page.
- Added the official WordPress.org donation link and explicit support information.

## 2.0.2

- Fixed the WP Rocket Delay JavaScript exclusion by deriving the frontend script path from the actual installed plugin directory.
- Renamed remaining internal enqueue handles, the settings-page slug and Gutenberg registration identifiers to the `gdaiidl` prefix.
- Retained serialized block attributes and established CSS selectors for backward compatibility.

## 2.0.1

- Replaced generic plugin-owned identifiers with the unique `gdaiidl` prefix.
- Renamed PHP globals, options, metadata, custom hooks, REST identifiers and JavaScript configuration globals.
- Added automatic migration of settings and metadata from earlier GitHub releases.
- Documented the external Cache Enabler hook as a third-party integration that must retain its published name.

## 2.0

- First public major release for WordPress.org and GitHub.
- Fixed automatic badge colors for featured images on home, archive and index pages.
- Responsive disclosure modes: hidden, compact symbol and full text.
- Optional accessible disclosure popover for compact symbols.
- Automatic badge colors with contrast-aware text.
- Built-in and custom image symbols.
- Gutenberg and featured-image controls.
- Theme integration and broad cache-plugin support.
- WordPress.org-compliant text domain, packaging and Plugin Check annotations.
- Refined legal wording around Article 50 of the EU AI Act.

Earlier internal development history remains available in `readme.txt`.
