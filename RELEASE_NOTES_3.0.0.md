# AI Image & Video Disclosure Labels 3.0.0

Version 3.0.0 is a major update from the previously published 2.1.4 release. It folds the unpublished 2.2.x-2.5.x development work into one public release.

## Highlights

- Independent top-level **Images** and **Videos** enable switches.
- Renamed public-facing title: **AI Image & Video Disclosure Labels** (existing slug/text domain retained for compatibility).
- Media Library classification for AI-generated, AI-modified, no-AI and unclassified images and videos.
- Inherited attachment classification in Gutenberg Image and Video blocks and featured images, with per-use overrides.
- Video disclosure badges render below the video instead of covering playback controls; optional structured data uses Schema.org `VideoObject`. Videos can inherit image styling or use a separate design and dedicated live preview.
- Separate video design controls reveal immediately when enabled; redundant inherited/active status copy was removed so the section stays concise.
- Recommended choices are identified directly in the settings UI, with the recommended value itself emphasized; automatic image-derived badge color is the fresh-install default.
- Optional AI automatic actions now use a consistent, flatter layout: the confidence threshold is visually grouped under automatic classification without a nested bordered card.
- Separate editable visible texts for AI-generated and AI-modified content.
- Experimental AI-assisted image analysis with WordPress AI Client / Connectors support and direct provider adapters; video classification remains manual in 3.0.0.
- Dynamic searchable model catalogues with no hard-coded model IDs or provider price table.
- Background bulk analysis, compatibility tests, cost/image safety limits and advisory-only auto-classification safeguards. Visual auto-classification has an adjustable threshold with a 95% recommended default; technical provenance auto-application is custom-endpoint-only.
- Reworked settings hierarchy into essential, design/preview, advanced, security/performance and experimental sections, plus numerous editor, Media Library, touch-device, REST and cache-performance improvements.
- Final release hardening adds server-side 80-character disclosure-text limits, HTTPS-only custom/OpenAI-compatible endpoints, strict boolean verified-provenance handling, bounded remote response/model-catalogue sizes, safer bounded local image reads, and a cheaper conditional frontend block scan.
- Privileged AI-analysis actions were re-audited for nonce/capability checks; credentials remain server-side and no unauthenticated public AI-analysis AJAX handler is registered.
- Final JS/CSS polish adds a defensive settings-field null guard, debounced live-preview resize handling, and Windows High Contrast / forced-colors support.

See `CHANGELOG.md` for the complete release notes.
