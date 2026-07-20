# Changelog

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
