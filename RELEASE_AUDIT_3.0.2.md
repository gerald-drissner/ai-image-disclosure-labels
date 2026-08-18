# Release audit — 3.0.2

## Scope

Focused frontend compatibility maintenance release based on the published 3.0.1 code line.

## Functional change

When a location rule resolves to hidden, the frontend compatibility layer now removes disclosure markup added by this plugin and unwraps a plugin-created featured-image frame so the host theme's original DOM structure is restored. Direct image-element selector matching is also supported.

## Compatibility intent

The 3.0.2 implementation is generic. It adds no Newsblock-specific selector, class name, theme detection or page-builder-specific branch. The purpose is to avoid leaving plugin-owned structural markup behind when no disclosure is meant to be visible at that location.

## Validation

- User-tested on the site that exposed the regression: theme-owned numbered thumbnail overlays returned while AI disclosure symbols remained hidden.
- PHP syntax checks pass for all PHP runtime files.
- JavaScript syntax checks pass for all JavaScript runtime files.
- Distribution ZIP integrity passes.
- Plugin header and WordPress.org stable tag both report 3.0.2.
- No symlinks are present in the production package.

The official WordPress Plugin Check must still pass on GitHub Actions after the 3.0.2 source is pushed to `main` and before the 3.0.2 tag is created.
