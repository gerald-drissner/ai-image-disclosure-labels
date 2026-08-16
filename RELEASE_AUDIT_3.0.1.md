# Release Audit — 3.0.1

## Purpose

This release addresses the findings produced by the official WordPress Plugin Check run against 3.0.0 while preserving the existing disclosure and AI-analysis behavior.

## Plugin Check findings addressed in source

- `WordPress.Security.NonceVerification.Missing`: AJAX POST access is centralized in `ajax_request()`, where `check_ajax_referer()` and capability verification occur before input is read.
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: endpoint values pass through `esc_url_raw(..., ['https'])` and the plugin's HTTPS-only validator; other fields use WordPress sanitizers and explicit length bounds.
- `WordPress.DB.PreparedSQL.NotPrepared` / unfinished prepare: dynamic SQL placeholder assembly was removed from the Media Library MIME filter; background-job SQL now uses fixed prepared query shapes.
- `WordPress.DB.DirectDatabaseQuery.*`: the few direct reads retained for background bulk-analysis counting/cursor scans are narrow admin-only queries, prepared, bounded, and intentionally uncached; PHPCS comments document that rationale at the call sites.
- `WordPress.WP.AlternativeFunctions.unlink_unlink`: temporary-file cleanup now uses `wp_delete_file()`.
- `WordPress.WP.I18n.MissingTranslatorsComment` and unordered placeholders: translator comments were added and multi-placeholder strings use ordered placeholders.

## Intentional Plugin Check advisory

`PluginCheck.CodeAnalysis.AIProvider.DirectIntegration` is intentionally ignored in CI.

Reason: direct OpenAI, Gemini, Anthropic, and Cloudflare Workers AI adapters are explicit, optional Software-as-a-Service integrations. WordPress 7 AI Client / Connectors is also supported. The WordPress.org `readme.txt` documents when media leaves the site, what is transmitted, the available providers, service/terms/privacy links, and the administrator's responsibility for custom endpoints.

The CI does not ignore all warnings or all errors; it ignores only this exact advisory code.

## Offloading detector

Remote documentation/policy links were removed from PHP-rendered admin markup. Service URLs remain in `readme.txt`, where WordPress.org expects external-service disclosure. No frontend/admin CSS, JavaScript, fonts, or images are loaded from remote CDNs by the plugin.

## Distribution

`.distignore` excludes:
- Git/GitHub metadata and workflows
- `.wordpress-org`
- GitHub-only README/changelog/publishing/release-audit/release-notes documents
- local build artifacts and ZIPs

The WordPress.org distribution should therefore contain only the 17 runtime/distribution files.

## Compatibility

No existing option names, post meta keys, serialized block attributes, CSS class names, REST namespace, plugin slug, or text domain were changed.

## Local release-candidate validation

The 3.0.1 shipping ZIP was rebuilt from the runtime source and then extracted into a clean directory for a second pass.

Passed locally:

- 17 distributable runtime files and no symlinks.
- `php -l` on all four PHP files.
- `node --check` on all five JavaScript files.
- Balanced CSS delimiters across all six CSS files.
- ZIP integrity and source-versus-extracted-package comparison.
- Version/header assertions for public name, `Version`, `GDAIIDL_VERSION`, text domain and `Stable tag`.
- No remaining direct `unlink()` calls and no unauthenticated `wp_ajax_nopriv_*` AI endpoints.
- No obvious embedded API credentials and no remote CSS/JavaScript/font asset loads.

The official GitHub WordPress Plugin Check run is still the release gate. 3.0.1 must not be tagged or published until the new `main` run succeeds with the updated workflow and production distribution.
