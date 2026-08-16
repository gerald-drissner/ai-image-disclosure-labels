=== AI Image & Video Disclosure Labels ===
Contributors: geralddrissner
Donate link: https://www.paypal.com/paypalme/drissner
Tags: ai image, ai video, ai generated content, content transparency, eu ai act
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds visible AI disclosure labels to selected WordPress images and videos, plus optional machine-readable source information.

== Description ==

AI Image & Video Disclosure Labels adds visible AI disclosure labels to images and videos. At the top of the settings page you can enable image disclosures, video disclosures, or both. Image labels appear on the image; video disclosures are deliberately placed immediately below the video so they do not cover playback controls. By default, labels remain explicitly opt-in per media use and existing content stays untouched. If you enable the optional Media Library auto-label setting, enabled media that you deliberately classify in the Media Library can receive its matching visible disclosure automatically.

**Why disclose AI media?**

Article 50 of the EU AI Act distinguishes between providers and deployers of AI systems. Providers of certain generative AI systems must add machine-readable marks to generated or manipulated outputs. Separate disclosure duties apply to deployers in specified cases, including deepfakes and certain public-interest text. The relevant rules apply from August 2, 2026. This plugin helps site owners add a visible disclosure and an optional publisher-supplied structured-data declaration to selected images and videos.

**Features**

* Independent global switches for image disclosures and video disclosures, so a site can use either feature on its own or both together.
* Per-image switch for Gutenberg Image blocks.
* Per-video switch for Gutenberg Video blocks; the visible disclosure is rendered below the video rather than over playback controls.
* Separate per-post/per-page switch for the featured image.
* Optional custom text for each marked image or video.
* Separate editable default visible texts for AI-generated and AI-modified media, plus optional video-specific wording; per-use and featured-image custom text still takes precedence.
* Optional machine-readable Schema.org `digitalSourceType` declarations using standardized IPTC source categories, emitted as `ImageObject` or `VideoObject` as appropriate.
* Global default and per-use source type for marked images and videos: created using generative AI, edited using generative AI, or enhanced using AI.
* Simplified Media Library classification aligned with plain disclosure language: AI-generated, AI-modified, no AI used, or unclassified. More precise legacy technical provenance is retained internally when available.
* Media Library list tools: an AI status column, status filter and grouped bulk actions make it possible to classify many images and videos without opening them individually.
* Optional automatic visible disclosures for AI-classified Media Library images and videos; this remains off by default.
* Optional AI-assisted image analysis through the WordPress 7.0+ AI Client / configured Connectors when available, or directly through OpenAI, Google Gemini, Anthropic Claude, Cloudflare Workers AI, an OpenAI-compatible HTTPS endpoint, or a custom HTTPS analysis endpoint. Automated analysis remains image-only in 3.0.0; videos are classified manually.
* Dynamic model discovery where the provider exposes a Models API or, in WordPress AI Client mode, through the configured AI provider registry; model names are never hard-coded and manual model/policy names remain supported.
* Provider-independent custom HTTPS endpoint contract for Cloudflare Workers, AI Gateway, dedicated detectors and other future services.
* AI analysis is stored separately from the publisher-facing status. Suggestions can be reviewed, filtered and bulk-applied; “likely non-AI” is never converted to “No AI used” automatically.
* Optional visual auto-classification uses an administrator-adjustable confidence threshold (95% recommended default). The confidence value is the model’s self-assessment, not a calibrated forensic probability.
* A separate custom-endpoint option can accept technically verified provenance only when the administrator’s own endpoint actually verifies a provenance/watermark signal; ordinary model confidence never counts as verified provenance.
* Background bulk analysis for selected images, all unclassified images or the entire Media Library, with image-count and known-cost safety limits.
* No hard-coded API pricing. Cost information is taken from provider/custom-endpoint data when machine-readable, calculated from user-supplied token rates, inferred from previously observed request costs, or shown as unavailable.
* Four image-overlay positions and three design presets, plus detailed design controls with live image preview.
* Optional separate video badge design with its own alignment, colors, opacity, border, radius, typography and padding, plus a dedicated below-video preview. If disabled, videos inherit the image badge design.
* Responsive behavior based on the actually rendered image width: hide the label on tiny thumbnails, show a compact AI symbol on medium images, and the full text label on large images. An optional touch-first mode prefers the compact symbol on phones and tablets by using browser interaction capabilities rather than user-agent guessing.
* Location-specific display rules: force icon-only mode or hide an existing disclosure inside selected page areas by using CSS selectors.
* Featured-image support across homepages, archives, query blocks, cards and post loops, including changing or randomly selected posts.
* Three built-in SVG symbols (AI Monogram, Sparkle, Chip) plus a custom PNG/SVG symbol from the Media Library.
* Symbol size in pixels or as a percentage of the rendered image width.
* Optional disclosure popover for compact symbols: hover or keyboard focus on desktop, tap-to-toggle on touch devices.
* Theme integration: works with the WordPress default themes and common featured-image markup out of the box; unusual themes can be supported by adding CSS selectors in the settings — no code required. Developers can additionally use the `gdaiidl_featured_selectors` and `gdaiidl_post_types` filters.
* Compatible with common page caches: labels are rendered server-side where possible, and supported page caches are cleared automatically when settings or labels change.
* Clean uninstall: removes all options and post metadata when the plugin is deleted.

**Important legal note**

This plugin can provide both a visible disclosure label and an optional publisher-supplied Schema.org declaration in the page source. The structured data uses standardized IPTC Digital Source Type categories, but it does not modify the media file, create or verify C2PA Content Credentials, prove origin or authenticity, or replace machine-readable markings supplied by the provider of a generative AI system. Whether and how specific content must be labeled depends on your role, your content and the applicable law. This plugin is a tool, not legal advice.

== External services and privacy ==

The core disclosure/label features work without any external AI service. AI-assisted image analysis is optional and disabled by default.

When an administrator enables AI analysis and requests an analysis (or explicitly enables automatic analysis of new uploads), the plugin sends a temporary resized copy of the selected image and a short classification prompt from the WordPress server to the configured provider. The original Media Library file is not modified. The plugin stores the returned suggestion and related metadata such as confidence, provider/model identifiers, explanation, usage/cost information and analysis time in WordPress attachment metadata. Images may contain personal, confidential or copyrighted material; administrators are responsible for ensuring they are allowed to send that material to the chosen service.

**WordPress AI Client / Connectors (WordPress 7.0+):** When this mode is selected, this plugin does not store or read a duplicate provider API key. WordPress routes the request through an AI provider plugin configured under Settings > Connectors. The external service that receives the image is therefore the provider selected by WordPress; review the terms and privacy policy of every connector/provider you enable for AI use.

Built-in service adapters:

* **OpenAI API** – service: https://platform.openai.com/ ; policies/terms/privacy: https://openai.com/policies/
* **Google Gemini API** – service/docs: https://ai.google.dev/ ; API terms: https://ai.google.dev/gemini-api/terms ; Google Privacy Policy: https://policies.google.com/privacy
* **Anthropic Claude API** – service/docs: https://platform.claude.com/ ; privacy/commercial policy center: https://privacy.anthropic.com/
* **Cloudflare Workers AI** – service/docs: https://developers.cloudflare.com/workers-ai/ ; Cloudflare policies: https://www.cloudflare.com/policies/

The plugin can also connect to an administrator-supplied **OpenAI-compatible HTTPS endpoint** or **custom HTTPS analysis endpoint**. These URLs must use HTTPS. The plugin cannot know the operator's terms or privacy policy, so the site administrator must review and disclose the policies of that endpoint.

Direct-provider API credentials configured in this plugin are used only on the WordPress server and are not exposed to site visitors or localized to browser JavaScript. In WordPress AI Client mode, provider credentials remain managed by WordPress Connectors and are not copied into this plugin. The plugin itself does not add telemetry or send images anywhere merely by being installed or activated.

== Installation ==

1. Upload the ZIP through Plugins > Add Plugin > Upload Plugin, or install it from the plugin directory.
2. Activate AI Image & Video Disclosure Labels.
3. Open Settings > AI Image & Video Labels and first choose Images, Videos, or both.
4. Configure the common image badge design and, if wanted, enable a separate video badge design.
5. In the block editor, select an Image or Video block and open its AI disclosure panel.
6. For a featured image, use "AI label for featured image" in the document settings sidebar.

== Screenshots ==

1. Settings overview with image/video enablement, editable wording, grouped essential/advanced/experimental settings, and live image/video previews.
2. Gutenberg Image block settings with the per-image label toggle and optional custom text.
3. Full text disclosure label displayed on a large frontend image.
4. Compact symbol-only disclosure displayed on a medium-sized frontend image.
5. Accessible disclosure popover opened from the compact symbol.

== Frequently Asked Questions ==

= Does the plugin mark media automatically? =

Not by default. Existing posts, pages, images and videos remain unchanged unless an editor explicitly enables a disclosure. If you deliberately enable the optional Media Library auto-label setting, positively AI-classified attachments can then receive their matching visible disclosure automatically.

= Does this make my site compliant with the EU AI Act? =

The plugin supports visible disclosure and can optionally add a publisher-supplied Schema.org `digitalSourceType` declaration to the page source. This does not create verified provenance or C2PA Content Credentials and does not replace provider-supplied machine-readable marking. Legal compliance depends on your specific situation; please consult qualified legal counsel.

= What does the machine-readable option add? =

When enabled, the plugin outputs a Schema.org `ImageObject` or `VideoObject` declaration in the page source for each marked image or video. It is not visually displayed. The `digitalSourceType` value uses standardized source categories for content created using generative AI, edited using generative AI, or algorithmically enhanced. The declaration is supplied by the publisher and is not cryptographically verified.

= Does the machine-readable option create C2PA Content Credentials? =

No. It does not modify the media file, create or verify Content Credentials, or prove origin or authenticity. Existing provider-supplied credentials are not replaced.

= Why does the technical provenance still distinguish AI-edited and AI-enhanced images? =

The publisher-facing Media Library status is deliberately simpler: AI-generated or AI-modified. Internally, however, older or explicitly set technical provenance can still distinguish generative editing (for example generative fill, inpainting or outpainting) from algorithmic enhancement (for example AI upscaling, denoising or sharpening). Those operations map to different standardized Digital Source Type categories. The plugin keeps that precision when it actually knows it instead of guessing a narrower provenance type from the generic AI-modified label.

= How does Media Library marking work? =

Open an image or video in the Media Library (Attachment details or the Edit media screen) and choose its "AI status". You can classify it as AI-generated, AI-modified, no AI used, or leave it unclassified. The status travels with the attachment, so it is convenient for featured images and media reused across posts. The list view also provides an AI status column, filter and bulk actions. Existing 2.3.x AI-edited/AI-enhanced values appear as AI-modified in the simplified publisher interface, while their more precise technical provenance remains stored unless you deliberately replace it.

= If I mark media in both the Media Library and the editor, which one wins? =

The editor always wins for that specific use. A per-Image-block, per-Video-block or featured-image source type set in the editor overrides the Media Library mark for that use, and custom disclosure text set in the editor is always kept. The Media Library mark is used only when that specific use does not set its own source type.

= Can I change every visible disclosure text? =

Yes. Settings > AI Image & Video Labels provides separate default public texts for AI-generated and AI-modified media. The AI-modified wording is also the default visible wording for more precise AI-edited or AI-enhanced provenance, while structured data can keep the finer technical distinction. Per-image, per-video and featured-image custom text still overrides these defaults. Media marked "No AI used" or left unclassified never receives an automatic visible text label.

= What does "No AI used" mean? =

It is an explicit publisher-entered classification for media for which you want to record that no AI was used. It does not create an AI `digitalSourceType`, does not prove that a file is camera-original or otherwise authentic, and should not be treated as a forensic verification result.

= Can the plugin automatically detect AI-generated images? =

Version 3.0.0 can optionally ask a configured external vision-capable AI service to assess an image and store a suggestion such as "likely AI-generated", "likely AI-modified", "likely non-AI" or "uncertain". This is probabilistic analysis, not forensic proof. General vision models can be wrong; an absent watermark or a "likely non-AI" answer does not prove that no AI was used.
The built-in direct-provider adapters perform visual analysis; they do not claim to cryptographically verify C2PA, SynthID or proprietary provider watermarks. If you operate a custom endpoint that performs genuine provenance/watermark verification, it can return `verified_provenance=true` and evidence labels. Automatic application of that stronger signal is a separate opt-in setting.

The analysis result is deliberately separate from the publisher-facing AI status. Automatic application is off by default and, if enabled, only high-confidence AI-generated/AI-modified suggestions can be applied to previously unclassified images. The plugin never automatically declares "No AI used".

= Which AI providers are supported? =

On WordPress 7.0+, the plugin can use the built-in WordPress AI Client when at least one AI provider plugin is registered; credentials remain managed under Settings > Connectors. Direct adapters for OpenAI, Google Gemini, Anthropic Claude and Cloudflare Workers AI remain available, along with an OpenAI-compatible HTTPS Chat Completions endpoint or a custom HTTPS JSON endpoint such as a Cloudflare Worker. Model names are entered manually or discovered dynamically where supported; the plugin does not ship, rank, infer or substitute fixed model IDs.

A model accepting images is not automatically a reliable AI-image detector. Check the provider/model's current capability documentation and validate it on representative images before enabling automatic classification. Some general multimodal providers explicitly warn that their vision models should not be relied on to determine whether an image is synthetic.

= How do I get an API key? =

If you choose WordPress AI Client mode on WordPress 7.0+, configure the provider once under Settings > Connectors; this plugin does not ask for or copy that key. The direct-provider modes remain available if you prefer to manage a separate key here. The settings page contains collapsible provider-specific instructions for those direct modes.

Keys are used only on the WordPress server and are never localized to frontend/admin JavaScript. Advanced users can define GDAIIDL_OPENAI_API_KEY, GDAIIDL_GEMINI_API_KEY, GDAIIDL_ANTHROPIC_API_KEY, GDAIIDL_CLOUDFLARE_API_TOKEN, GDAIIDL_OPENAI_COMPATIBLE_API_KEY or GDAIIDL_CUSTOM_API_TOKEN in wp-config.php instead of storing a key in the database.

= How do I use my own Cloudflare Worker or private model router? =

Choose "Custom HTTPS analysis endpoint". The plugin sends an authenticated JSON request containing action="analyze", the configured model or policy name, a short classification prompt and a temporary resized base64 image. Your endpoint returns classification and confidence, with optional reason, resolved_model, token usage and cost_usd. The same endpoint can answer action="models" with a current model/policy list. This allows a custom Worker to resolve a stable policy such as cf-policy:quality to whichever model you currently prefer without changing the WordPress plugin.

For a short Cloudflare setup: create a Worker under Workers & Pages, add a Workers AI binding named `AI` (available to Worker code as `env.AI`), and make the Worker accept the plugin JSON contract. For `action="analyze"`, call your current model or routing policy and map its result to the plugin response fields. Protect the endpoint with a bearer token if desired, then enter the Worker URL, model/policy and the same token in the plugin. Workers AI model input schemas can differ, so use the current Cloudflare schema for the model you select rather than copying an old model-specific example. The settings page links directly to Cloudflare's current Worker and Workers AI binding documentation. WordPress safe HTTP requests reject localhost/private-LAN destinations by default to reduce SSRF risk, so a public HTTPS Worker protected by authentication is the recommended setup.

= How are AI videos labelled? =

For the core WordPress Video block, enable the AI disclosure in the block sidebar or classify the uploaded video in the Media Library and enable automatic Media Library labels. The disclosure appears immediately below the video, outside the playback surface. This avoids covering native controls and works with captions because it is inserted before the figure caption. Optional machine-readable output uses a Schema.org `VideoObject`. AI-assisted frame/video analysis is not included in 3.0.0.

= Does AI analysis send my images to another service? =

Yes, but only when you explicitly request analysis or enable automatic analysis of new uploads. A temporary resized copy (1024 px maximum by default) is sent; the Media Library original is not modified. The configured provider's privacy policy, retention rules and API charges apply. The public-facing site does not call the AI provider.

= How much does bulk AI analysis cost? =

There is intentionally no hard-coded price table because model names and rates change frequently. A fast/low-cost vision model is normally the appropriate choice for this narrow classification task; premium/high-reasoning models may cost several times more. Before a whole-library job starts, the plugin shows a cost estimate when it has trustworthy data. Otherwise it says that the estimate is unavailable.

The plugin can use provider/custom-endpoint reported cost, machine-readable pricing returned with a model/result, manual token rates, a manual fixed request cost, or the observed average cost of previous requests. Its cost limit only covers costs it can actually observe or calculate; the API provider's billing system is authoritative.

= Can I analyse my whole Media Library? =

Yes. You can queue all currently unclassified images or the entire Media Library from Settings > AI Image & Video Labels. In Media > Library list view you can also select specific images and use "Analyse selected with AI" or "Re-analyse selected with AI". Jobs run in small WP-Cron background batches instead of attempting hundreds of API requests in one PHP request. You can set a maximum number of images and a maximum known cost per job.

= Why does the plugin use a compact symbol on some tablets? =

If "Prefer the compact symbol on touch-first devices" is enabled, the frontend checks browser interaction capabilities such as pointer accuracy, hover support and touch points. This avoids depending on WordPress user-agent detection and works more reliably for large Android tablets and browsers using desktop-site mode. Touch-enabled laptops with a normal fine mouse or trackpad keep the desktop presentation.

= My theme does not show the label on the featured image. What can I do? =

Open Settings > AI Image & Video Labels > Theme integration and add the CSS selector of your theme's featured image (one per line), for example `.hero-media img.wp-post-image`. Developers can also use the `gdaiidl_featured_selectors` filter.

= How do location-specific display rules work? =

Enable the rules under Settings > AI Image & Video Labels > Location-specific display. Add one CSS selector per line. An icon-only rule changes an existing disclosure in the matched area to the compact symbol; a hidden rule removes that visible disclosure in the matched area. These rules never mark an image as AI-generated. The image or featured image must already have its disclosure enabled.

For a stable editor-controlled setup, add a custom class such as `ai-label-disclosure-symbol-only` to the outer Group, Cover, Query or layout container. Enter the class name without a dot in the block editor, then enter `.ai-label-disclosure-symbol-only` in the plugin setting. To restrict it to the posts homepage, use `body.home .ai-label-disclosure-symbol-only`.

Avoid post IDs, attachment IDs and generated content-specific classes because they may change when a new, queried or randomly selected post is displayed.

= When should I use icon-only or hidden rules? =

Use icon-only mode where a full label would dominate a hero card, overlay tile or other prominent layout and could be misunderstood as describing the whole article or section. Use hidden rules only when even the compact symbol cannot be displayed clearly. Hidden rules remove the visible disclosure in that location, so use them sparingly.

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

= 3.0.0 =
* Major update since the previously published 2.1.4 release. Unpublished 2.2.x-2.5.x development builds are consolidated into this release.
* Added independent top-level enable switches for image disclosures and video disclosures. Disabled media types keep their stored metadata but no longer expose normal editor/public disclosure output.
* Added optional video-specific label wording, a separate video design mode, left/center/right below-video alignment, dedicated visual controls and a live video preview.
* Fixed the separate-video-design switch so its dedicated controls appear immediately, removed duplicate inherited/active status copy, and added file-aware cache busting across plugin assets so same-version test builds cannot silently reuse stale admin JavaScript or CSS.
* Marked genuinely recommended choices directly in the settings UI and bolded the recommended value in explanatory text. Automatic image-derived badge color is now the fresh-install default, while existing saved choices remain unchanged on upgrade.
* Made AI-analysis automation clearer: 95% remains the recommended default auto-apply threshold, the threshold is now shown directly beside visual auto-classification, and technical provenance auto-application is explicitly limited to a custom endpoint that really performs provenance/watermark verification.
* Polished the optional automatic-actions layout so upload analysis and automatic classification use a consistent single-card hierarchy; the confidence threshold is now visually subordinate to automatic classification without a nested/double border.
* Added attachment-level Media Library classification with simple publisher-facing statuses: AI-generated, AI-modified, no AI used, or unclassified. Existing precise edited/enhanced provenance is preserved internally where known.
* Extended publisher-facing disclosure to uploaded videos and Gutenberg Video blocks. Videos inherit the same Media Library classification and per-block overrides as images, while the visible disclosure is rendered below the video so playback controls remain unobstructed.
* Added optional machine-readable `VideoObject` source declarations alongside existing `ImageObject` output. AI-assisted analysis remains image-only in 3.0.0.
* Added Media Library list columns, filters and bulk actions for classification, plus inheritance into Gutenberg Image and Video blocks and featured-image controls unless a specific per-use override is set.
* Added separate editable default visible texts for AI-generated and AI-modified media. No automatic public label is ever shown for “No AI used” or unclassified/unknown media.
* Added optional automatic visible labels for positively AI-classified Media Library attachments. This remains disabled by default.
* Added experimental AI-assisted image analysis with direct OpenAI, Google Gemini, Anthropic Claude and Cloudflare Workers AI adapters, plus OpenAI-compatible HTTPS and custom HTTPS endpoints.
* Added WordPress 7.0+ AI Client / Connectors integration. When a compatible AI provider is configured in WordPress, the plugin can reuse those credentials instead of asking for a duplicate API key.
* Added dynamic model discovery and searchable catalogues where providers expose them. Model IDs and provider prices are not hard-coded, ranked, renamed or silently substituted by the plugin.
* Added a compatibility test using a tiny built-in image, a reset control, alphabetical/searchable model selection, and optional manual model or policy entry.
* Added background analysis jobs for selected images, all unclassified images or the entire Media Library, with progress, cancellation, small batches, image-count limits and known-cost safety limits.
* Added provider-independent usage/cost tracking without a bundled price table. Provider-reported cost, token usage, machine-readable pricing, manual rates and observed averages are supported; unknown costs remain explicitly unknown.
* AI visual analysis remains advisory: it never automatically classifies an image as “No AI used”, and high-confidence auto-application is opt-in and only affects previously unclassified images.
* Added an explicitly custom-endpoint-only path for technically verified provenance/watermark results without treating ordinary vision-model confidence as proof.
* Analysis sends only a temporary resized copy by default, never modifies the Media Library original, keeps credentials server-side, uses WordPress safe HTTP requests, and makes no AI-provider calls on public frontend page views.
* Hardened release input/output boundaries: visible disclosure text is capped server-side at 80 characters; custom and OpenAI-compatible endpoints must use HTTPS; verified provenance requires the literal JSON boolean `verified_provenance=true`; model catalogues are bounded; and remote AI/model responses have explicit size limits.
* Hardened local image processing with bounded file/read checks for automatic badge-color sampling and temporary analysis images, including cleanup on failure.
* Improved conditional frontend asset detection by avoiding full block-tree parsing when a post contains no relevant Image or Video block.
* Reviewed privileged AI-analysis actions for capability and nonce protection; provider credentials remain server-side and no public unauthenticated AI-analysis AJAX endpoint is registered.
* Reorganized the settings page into Essential settings, Design and previews, Advanced options, Security/privacy/performance, and Experimental AI analysis.
* Improved Media Library attachment controls, touch-first compact-symbol behavior, responsive admin styling, REST/save race handling, cache-purge efficiency and compatibility with the WordPress 7.1 editor.
* Final JS/CSS polish from an independent second pass: added a defensive admin field-wrapper null guard, debounced settings-preview resize updates, and explicit Windows High Contrast / forced-colors support for disclosure badges and live previews.

= 2.1.4 =
* Added WordPress 7.1 compatibility for the always-iframed post editor by loading image-preview styles inside the editor canvas.
* No changes to saved block markup, frontend disclosure output, settings, or existing metadata.

= 2.1.3 =
* Added a recommended performance option that loads frontend CSS and JavaScript only on pages containing disclosure labels.
* Added automatic late-loading safeguards for disclosures rendered by Query blocks, post loops, themes and page builders.
* Added an explicit defer loading strategy for the frontend script.

= 2.1.2 =
* Finalized location-specific display documentation and clarified that selector rules modify existing disclosures only; they never mark images automatically.
* Documented the recommended custom-class workflow, homepage scoping and the difference between icon-only and hidden rules.
* Confirmed featured-image disclosure handling across homepages, archives, query blocks, cards and post loops.

= 2.1.1 =
* Fixed disclosures for marked featured images rendered inside archive cards, query blocks and post loops through direct attachment-image calls.
* Each featured image is now matched to the post currently being rendered, including changing and randomly selected posts.
* Reworked the location-rule instructions with generic examples and clearer usage guidance.

= 2.1.0 =
* Added selector-based icon-only and hidden display rules for specific page locations.
* Added neutral guidance for page-scoped selector rules and custom editor classes.

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
