# AI Image Disclosure & Labels

<p align="center">
  <img src=".wordpress-org/icon-256x256.png" width="160" height="160" alt="AI Image Disclosure & Labels icon">
</p>

A WordPress plugin for adding visible AI disclosure labels to selected Gutenberg images and featured images.

The plugin does nothing automatically. Editors explicitly decide which images receive a label.

## Features

- Per-image switch for Gutenberg Image blocks.
- Separate switch for post, page and supported custom-post-type featured images.
- Full text labels on large images.
- Compact AI symbols on medium images.
- Automatic hiding on very small thumbnails.
- Optional hover, keyboard-focus and tap-to-toggle disclosure popover.
- Three built-in SVG symbols plus a custom PNG or SVG from the Media Library.
- Automatic badge colors derived from the image, with readable light or dark text.
- Three design presets and detailed visual controls.
- Theme integration through settings and developer filters.
- Cache clearing integrations for common WordPress caching plugins.
- No Google Fonts or other remote font requests.
- Accessible keyboard and screen-reader behavior.
- Clean uninstall.

## Requirements

- WordPress 6.7 or later
- PHP 7.4 or later

## Installation

1. Install the plugin ZIP through **Plugins → Add Plugin → Upload Plugin**.
2. Activate **AI Image Disclosure & Labels**.
3. Open **Settings → AI Image Labels**.
4. Select an Image block in the editor and enable **AI image label**.
5. For a featured image, use **AI label for featured image** in the document sidebar.

## Responsive disclosure modes

| Rendered image width | Result |
|---|---|
| Below the minimum marker width | Nothing is displayed |
| Between the marker and text thresholds | Compact symbol |
| At or above the text threshold | Full disclosure text |

All thresholds are configurable.

## EU AI Act note

Article 50 of Regulation (EU) 2024/1689 includes transparency duties for providers and deployers of certain AI systems, including visible disclosure of deep fakes and certain other AI-generated content. The relevant transparency rules apply from 2 August 2026.

This plugin helps add a visible disclosure. It does not determine whether a particular image must be labelled, does not add machine-readable provenance or content credentials, and is not legal advice.

## Development

The WordPress.org plugin slug and translation domain are:

```text
ai-image-disclosure-labels
```

Plugin-owned PHP globals, option names, metadata keys, custom hooks, REST routes, JavaScript configuration objects, enqueue handles, settings slugs and editor registration identifiers use the unique `gdaiidl` prefix. Earlier GitHub settings and metadata are migrated automatically. Existing serialized block attributes (`gdAiLabel` and `gdAiLabelText`) and established `gd-ai-*` CSS selectors remain unchanged for backward compatibility with saved posts and custom styling.

Run the official Plugin Check workflow before each release and review every reported error and warning.

## Support and contributions

Bug reports and focused pull requests are welcome through the GitHub issue tracker and pull-request workflow.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Maintainer

Gerald Drißner  
https://drissner.media/
