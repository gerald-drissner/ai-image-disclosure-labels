# AI Image & Video Disclosure Labels 3.0.1

3.0.1 is a release-hardening update after the official WordPress Plugin Check run on the 3.0.0 GitHub/WordPress.org package.

## What changed

- Consolidated AI-admin AJAX request handling so nonce verification, capability checks, and input sanitization are explicit in the same request boundary.
- Replaced temporary analysis-file `unlink()` calls with WordPress `wp_delete_file()`.
- Reworked the Media Library MIME filter to use fixed prepared query shapes instead of dynamically assembled placeholder fragments.
- Tightened preparation of the background AI job queries and documented the narrow admin-only direct-query exceptions.
- Added all missing translator comments and ordered placeholders in translated strings.
- Removed remote documentation links from PHP-rendered admin markup. External service, terms, and privacy links remain clearly documented in the WordPress.org `readme.txt`.
- Updated `.distignore` so GitHub-only release notes, audits, workflow files, and development documentation are excluded from the WordPress.org package.
- Updated Plugin Check CI to ignore only `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration`. The direct OpenAI, Gemini, Anthropic, and Cloudflare adapters are intentional optional service integrations; WordPress 7 AI Client / Connectors remains fully supported.

## Important

AI-assisted analysis remains optional and off by default. The core image/video disclosure features do not require an external AI service. Direct-provider credentials remain server-side, and provider/service disclosure remains documented in `readme.txt`.

This maintenance release does not change the disclosure model, stored classifications, block attributes, metadata keys, or public plugin slug/text domain.
