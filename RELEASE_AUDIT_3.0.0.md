# Release Audit — AI Image & Video Disclosure Labels 3.0.0

Date: 2026-08-16

This document records the final pre-release static/code audit for the 3.0.0 package. It is a release checklist and engineering record, not a claim that static analysis can prove the absence of every runtime or environment-specific bug.

## Identity and compatibility

- Public plugin name: **AI Image & Video Disclosure Labels**.
- Version: **3.0.0**.
- Existing WordPress.org slug/folder retained: `ai-image-disclosure-labels`.
- Existing text domain retained: `ai-image-disclosure-labels`.
- Existing `GDAIIDL_*`, option/meta keys and serialized block compatibility identifiers retained.

## Settings and UX review

- Independent top-level enable switches exist for **Images** and **Videos**.
- Videos inherit image badge styling by default; **Use a separate design for video badges** reveals the dedicated controls immediately.
- The duplicate video design status/recommendation strip was removed.
- Where the plugin has a defensible recommendation, the recommended value itself is emphasized in the explanatory copy (for example **on**, **off**, **95%**, **1024 px**).
- Automatic image-derived badge color is the fresh-install default; existing saved settings are preserved on upgrade.
- Separate live image and video previews remain available.
- Experimental AI-analysis wording distinguishes model suggestions from technically verified provenance.
- The optional automatic-actions area uses a single visual boundary for automatic classification and its confidence threshold; the previous nested/double-bordered layout was removed, including a responsive mobile margin adjustment.

## Security and data-boundary review

- Visible custom disclosure text is sanitized and capped server-side at 80 characters.
- Direct-provider API credentials remain server-side; secrets can also be supplied through `wp-config.php` constants.
- Secret settings remain separate from ordinary autoloaded plugin settings.
- Privileged AI-analysis AJAX actions require authenticated WordPress capabilities and nonces; no `wp_ajax_nopriv_*` AI-analysis handler is registered.
- REST mutation routes retain permission callbacks / edit-capability checks.
- Administrator-supplied custom and OpenAI-compatible endpoints are restricted to HTTPS.
- Arbitrary endpoint requests use WordPress safe HTTP functions.
- Remote inference responses are bounded to 2 MiB; model-catalogue responses are bounded to 5 MiB.
- Normalized model catalogues are capped at 1,000 entries.
- `verified_provenance` is accepted only when the custom endpoint returns the literal JSON boolean `true`; a string such as `"true"` or a model confidence score is not sufficient.
- No PHP `eval`, shell-execution or unsafe `unserialize()` call was found in the shipping source during the audit.

## Stability and performance review

- Conditional frontend asset detection first checks whether relevant Image/Video blocks are present before parsing the full block tree.
- Automatic image-color sampling checks readability and bounds the local source file/read to 5 MiB before GD decoding.
- Temporary AI-analysis images are bounded to 8 MiB before they are read and encoded; cleanup remains on error paths.
- Plugin CSS/JS assets use file-aware cache busting so repeated 3.0.0 test builds do not silently reuse stale files.
- AI visual analysis remains image-only; video analysis is not simulated from an arbitrary frame.
- No provider request is made from ordinary public frontend views.

## Syntax and package gates

The final build process must complete all of these successfully on both source and freshly re-extracted release archives:

- `php -l` for every shipping PHP file.
- `node --check` for every shipping JavaScript file.
- Matching plugin header `Version`, `GDAIIDL_VERSION` and WordPress.org `Stable tag`.
- ZIP integrity test.
- No symlinks in the shipping archive.
- Expected clean plugin root directory: `ai-image-disclosure-labels/`.
- GitHub/WordPress Plugin Check workflow must still pass before the release tag is published.

## Runtime smoke tests recommended before publishing

1. Toggle image and video support independently and verify the disabled medium stops producing editor/frontend disclosure output without losing stored classification.
2. Toggle the separate video design without saving and verify the dedicated controls and video preview update immediately.
3. Inspect **Automatic actions — optional** at desktop and narrow admin widths: the upload-analysis action should be one card, and automatic classification plus its confidence threshold should be one grouped card with no nested/double border.
4. Insert a Video block with and without a caption and verify the disclosure renders below the playback surface and before the caption.
5. Verify a classified Media Library image/video inherits its status, then override that status on one block use.
6. With conditional assets enabled, visit a page with no relevant blocks and a page containing a disclosure to confirm styling/script behavior.
7. Run one provider compatibility test and one real image analysis on any provider intended for production use; confirm no API key is present in rendered HTML/browser-localized configuration.
8. Run the repository's official WordPress Plugin Check workflow and resolve any error before tagging `3.0.0`.
## Final local verification result

The final shipping archive is rebuilt from this audited source and extracted into a clean directory for a second verification pass. The release procedure verifies:

- 17 distributable files and 0 symlinks.
- ZIP integrity.
- All 4 PHP files with `php -l`.
- All 5 JavaScript files with `node --check`.
- Matching public plugin name, `Version`, `GDAIIDL_VERSION` and WordPress.org `Stable tag` at **3.0.0**.
- Source tree versus re-extracted shipping ZIP by recursive file comparison.
- Targeted assertions for the video-design toggle, removal of duplicate status markup, HTTPS endpoint sanitization, bounded HTTP responses, strict verified-provenance handling and absence of unauthenticated AI-analysis AJAX handlers.
- A private-helper smoke test for UTF-8-safe 80-character label truncation and HTTPS endpoint acceptance/rejection.

The exact shipping ZIP SHA-256 is recorded in `PLUGIN_SHA256_3.0.0.txt` after the final archive is produced.

The repository's WordPress Plugin Check workflow remains the final repository/runtime-oriented gate before tagging and publishing the release.

## Final independent-review follow-up

A second external pass reviewed all five JavaScript files and all six CSS files end to end and found no release-blocking security, correctness, or stability issue. The final pre-release polish applied from that review is intentionally narrow: a defensive null guard around the settings field wrapper, debounced admin preview resize handling, and explicit `forced-colors` support for Windows High Contrast mode.

The review also noted several non-blocking robustness considerations that are intentionally deferred rather than changed immediately before release: shared background-job option storage can theoretically have a cross-job last-write-wins race under true parallel execution; the transient job lock is not fully atomic; repeated auto-apply batches can trigger broad page-cache purges; the badge-color sampler may decode a bounded original image when no thumbnail exists; and short-lived AI transients are allowed to expire naturally after uninstall. None changes the 3.0.0 release decision, and the concurrency items are better handled together in a later maintenance refactor than as a last-minute storage-model change.
