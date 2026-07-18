# Contributing

Thank you for considering a contribution.

## Before opening an issue

- Confirm the problem still exists with the latest release.
- Clear the WordPress page cache, CDN cache and browser cache.
- Test with a default WordPress theme where practical.
- Include WordPress, PHP and plugin versions.
- Include the theme name and relevant caching plugins.
- Describe whether the affected image is a Gutenberg Image block, a featured image, an archive thumbnail, a slider image or dynamically inserted content.

## Pull requests

- Keep changes focused.
- Preserve PHP 7.4 compatibility.
- Use the text domain `ai-image-disclosure-labels` for translatable strings.
- Preserve existing option names, metadata keys and public hooks unless a migration is included.
- Run PHP and JavaScript syntax checks.
- Run WordPress Plugin Check.
- Update `readme.txt` and `CHANGELOG.md` for user-visible changes.

## Coding conventions

The project follows WordPress coding and security practices:

- capability checks and nonces for privileged actions;
- sanitization on input;
- escaping on output;
- prefixed globals, options, metadata and custom hooks;
- no remote assets or telemetry.
