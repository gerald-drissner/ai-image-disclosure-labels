# AI Image & Video Disclosure Labels 3.0.2

3.0.2 is a focused theme-compatibility maintenance release.

## Fixed

- Fixed a generic compatibility issue where a hidden location rule could suppress the disclosure label but leave the plugin-added featured-image wrapper in place.
- Hidden location rules now restore the host theme's original featured-image markup, preventing interference with theme-owned counters, overlays, hover effects and structural CSS selectors.
- Location selectors can now match the image element itself, the disclosure frame or an ancestor.
- The 3.0.2 fix is generic and adds no Newsblock-specific branch or new theme-specific selector.

No AI-analysis, database, metadata or provider-routing behavior was changed by this release.
