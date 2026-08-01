# Changelog

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
