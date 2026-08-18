[1mdiff --git a/CHANGELOG.md b/CHANGELOG.md[m
[1mindex 8e8ff86..95d7d4f 100644[m
[1m--- a/CHANGELOG.md[m
[1m+++ b/CHANGELOG.md[m
[36m@@ -1,5 +1,12 @@[m
 # Changelog[m
 [m
[32m+[m[32m## 3.0.2[m
[32m+[m
[32m+[m[32m- Fixed a theme-compatibility issue where hiding an AI disclosure at a configured location could leave the plugin's featured-image wrapper in place and interfere with theme-owned thumbnail overlays, counters, hover effects or structural CSS selectors.[m
[32m+[m[32m- Hidden location rules now restore the host theme's original featured-image markup by removing the disclosure label and the plugin-added wrapper.[m
[32m+[m[32m- Location selectors can now match the image element itself as well as the disclosure frame or an ancestor, making selector rules more predictable across themes and page builders.[m
[32m+[m[32m- The compatibility fix is generic and contains no theme-specific selectors or Newsblock-only behavior.[m
[32m+[m
 ## 3.0.1[m
 [m
 - Release-hardening update after the official WordPress Plugin Check run on 3.0.0.[m
[1mdiff --git a/README.md b/README.md[m
[1mindex 4bd5ac2..7c76fea 100644[m
[1m--- a/README.md[m
[1m+++ b/README.md[m
[36m@@ -8,6 +8,10 @@[m [mA WordPress plugin for visible AI image and video disclosures, attachment-level[m
 [m
 By default, the plugin does not label existing content automatically. Editors can mark individual image/video uses manually, classify reusable attachments in the Media Library, and optionally enable automatic visible disclosures for positively AI-classified attachments.[m
 [m
[32m+[m[32m## 3.0.2 theme compatibility fix[m
[32m+[m
[32m+[m[32m3.0.2 fixes a generic frontend compatibility issue in hidden location rules. When a configured location suppresses an AI disclosure, the plugin now removes its own featured-image wrapper and restores the host theme's original markup. This prevents theme-owned counters, overlays, hover effects and structural CSS selectors from being disrupted. Location selectors can also match the image element itself, the disclosure frame or an ancestor. The 3.0.2 change adds no Newsblock-specific branch or new theme-specific selector.[m
[32m+[m
 ## 3.0.1 release hardening[m
 [m
 3.0.1 is a maintenance release produced after running the official WordPress Plugin Check against 3.0.0. It makes the AJAX security boundary easier for static analysis to verify, replaces direct temporary-file deletion with WordPress APIs, tightens prepared SQL shapes, completes translator annotations, and keeps GitHub-only release documents out of the production package.[m
[1mdiff --git a/ai-image-disclosure-labels.php b/ai-image-disclosure-labels.php[m
[1mindex bcaf334..ab5331b 100644[m
[1m--- a/ai-image-disclosure-labels.php[m
[1m+++ b/ai-image-disclosure-labels.php[m
[36m@@ -3,7 +3,7 @@[m
  * Plugin Name:       AI Image & Video Disclosure Labels[m
  * Plugin URI:        https://github.com/gerald-drissner/ai-image-disclosure-labels[m
  * Description:       Adds visible AI disclosure labels for images and videos, Media Library classification, optional machine-readable source data, and optional AI-assisted image analysis.[m
[31m- * Version:           3.0.1[m
[32m+[m[32m * Version:           3.0.2[m
  * Requires at least: 6.7[m
  * Requires PHP:      7.4[m
  * Author:            Gerald Drißner[m
[36m@@ -15,7 +15,7 @@[m
 [m
 defined( 'ABSPATH' ) || exit;[m
 [m
[31m-define( 'GDAIIDL_VERSION', '3.0.1' );[m
[32m+[m[32mdefine( 'GDAIIDL_VERSION', '3.0.2' );[m
 define( 'GDAIIDL_FILE', __FILE__ );[m
 define( 'GDAIIDL_DIR', plugin_dir_path( __FILE__ ) );[m
 define( 'GDAIIDL_URL', plugin_dir_url( __FILE__ ) );[m
[1mdiff --git a/assets/frontend.js b/assets/frontend.js[m
[1mindex 2180b04..ffa2249 100644[m
[1m--- a/assets/frontend.js[m
[1m+++ b/assets/frontend.js[m
[36m@@ -273,10 +273,15 @@[m
 		}[m
 [m
 		const frame = label.closest( '.gd-ai-image-frame, .gd-ai-featured-theme-fallback' ) || label;[m
[32m+[m		[32mconst image = getLabelImage( label );[m
 [m
 		for ( let index = 0; index < selectors.length; index += 1 ) {[m
 			try {[m
[31m-				if ( frame.matches( selectors[ index ] ) || frame.closest( selectors[ index ] ) ) {[m
[32m+[m				[32mif ([m
[32m+[m					[32mframe.matches( selectors[ index ] ) ||[m
[32m+[m					[32mframe.closest( selectors[ index ] ) ||[m
[32m+[m					[32m( image && image.matches( selectors[ index ] ) )[m
[32m+[m				[32m) {[m
 					return true;[m
 				}[m
 			} catch ( error ) {[m
[36m@@ -287,6 +292,42 @@[m
 		return false;[m
 	}[m
 [m
[32m+[m	[32mfunction restoreLocationHiddenFeaturedMarkup( label ) {[m
[32m+[m		[32mif ( ! label ) {[m
[32m+[m			[32mreturn false;[m
[32m+[m		[32m}[m
[32m+[m
[32m+[m		[32mconst wrapper = label.closest( '.gd-ai-featured-wrap' );[m
[32m+[m
[32m+[m		[32mif ( wrapper && wrapper.parentNode ) {[m
[32m+[m			[32mconst parent = wrapper.parentNode;[m
[32m+[m			[32mlabel.remove();[m
[32m+[m
[32m+[m			[32mwhile ( wrapper.firstChild ) {[m
[32m+[m				[32mparent.insertBefore( wrapper.firstChild, wrapper );[m
[32m+[m			[32m}[m
[32m+[m
[32m+[m			[32mwrapper.remove();[m
[32m+[m			[32mreturn true;[m
[32m+[m		[32m}[m
[32m+[m
[32m+[m		[32m/*[m
[32m+[m		[32m * The JavaScript featured-image fallback reuses a theme-owned container[m
[32m+[m		[32m * instead of creating a wrapper. If that fallback label is hidden by a[m
[32m+[m		[32m * location rule, remove only the classes/data that this plugin added.[m
[32m+[m		[32m */[m
[32m+[m		[32mconst fallback = label.closest( '.gd-ai-featured-theme-fallback' );[m
[32m+[m
[32m+[m		[32mif ( fallback && fallback.dataset.gdAiFeaturedLabel === '1' ) {[m
[32m+[m			[32mlabel.remove();[m
[32m+[m			[32mfallback.classList.remove( 'gd-ai-image-frame', 'gd-ai-featured-theme-fallback' );[m
[32m+[m			[32mdelete fallback.dataset.gdAiFeaturedLabel;[m
[32m+[m			[32mreturn true;[m
[32m+[m		[32m}[m
[32m+[m
[32m+[m		[32mreturn false;[m
[32m+[m	[32m}[m
[32m+[m
 	function getLocationMode( label ) {[m
 		if ( matchesLocationSelector( label, hiddenSelectors ) ) {[m
 			return 'hidden';[m
[36m@@ -319,6 +360,18 @@[m
 [m
 		syncTouchCompactClass( label );[m
 [m
[32m+[m		[32mconst locationMode = getLocationMode( label );[m
[32m+[m
[32m+[m		[32m/*[m
[32m+[m		[32m * A featured-image disclosure may add a lightweight wrapper or temporary[m
[32m+[m		[32m * classes to a host-theme container. When a location rule explicitly[m
[32m+[m		[32m * hides the disclosure, restore the original theme markup so its overlays,[m
[32m+[m		[32m * counters, hover effects and structural selectors remain untouched.[m
[32m+[m		[32m */[m
[32m+[m		[32mif ( locationMode === 'hidden' && restoreLocationHiddenFeaturedMarkup( label ) ) {[m
[32m+[m			[32mreturn;[m
[32m+[m		[32m}[m
[32m+[m
 		const image = getLabelImage( label );[m
 [m
 		if ( ! image ) {[m
[36m@@ -335,8 +388,6 @@[m
 [m
 		syncTooltipForWidth( label, renderedWidth );[m
 [m
[31m-		const locationMode = getLocationMode( label );[m
[31m-[m
 		if ( ! locationMode && frameUsesContainerQueries( label ) ) {[m
 			return;[m
 		}[m
[1mdiff --git a/readme.txt b/readme.txt[m
[1mindex 0289d01..01fbf7d 100644[m
[1m--- a/readme.txt[m
[1m+++ b/readme.txt[m
[36m@@ -5,7 +5,7 @@[m [mTags: ai image, ai video, ai generated content, content transparency, eu ai act[m
 Requires at least: 6.7[m
 Tested up to: 7.1[m
 Requires PHP: 7.4[m
[31m-Stable tag: 3.0.1[m
[32m+[m[32mStable tag: 3.0.2[m
 License: GPLv2 or later[m
 License URI: https://www.gnu.org/licenses/gpl-2.0.html[m
 [m
[36m@@ -183,7 +183,7 @@[m [mOpen Settings > AI Image & Video Labels > Theme integration and add the CSS sele[m
 [m
 = How do location-specific display rules work? =[m
 [m
[31m-Enable the rules under Settings > AI Image & Video Labels > Location-specific display. Add one CSS selector per line. An icon-only rule changes an existing disclosure in the matched area to the compact symbol; a hidden rule removes that visible disclosure in the matched area. These rules never mark an image as AI-generated. The image or featured image must already have its disclosure enabled.[m
[32m+[m[32mEnable the rules under Settings > AI Image & Video Labels > Location-specific display. Add one CSS selector per line. An icon-only rule changes an existing disclosure in the matched area to the compact symbol; a hidden rule removes that visible disclosure in the matched area. For featured images, hidden rules also restore theme-owned markup when the plugin had added a wrapper or temporary fallback classes, so theme overlays, counters and structural selectors remain intact. These rules never mark an image as AI-generated. The image or featured image must already have its disclosure enabled.[m
 [m
 For a stable editor-controlled setup, add a custom class such as `ai-label-disclosure-symbol-only` to the outer Group, Cover, Query or layout container. Enter the class name without a dot in the block editor, then enter `.ai-label-disclosure-symbol-only` in the plugin setting. To restrict it to the posts homepage, use `body.home .ai-label-disclosure-symbol-only`.[m
 [m
[36m@@ -219,6 +219,12 @@[m [mFor support, visit [drissner.media/kontakt](https://drissner.media/kontakt). If[m
 [m
 == Changelog ==[m
 [m
[32m+[m[32m= 3.0.2 =[m
[32m+[m[32m* Fixed a theme-compatibility issue where hiding an AI disclosure at a configured location could leave the plugin's featured-image wrapper in place and interfere with theme-owned thumbnail overlays, counters, hover effects or structural CSS selectors.[m
[32m+[m[32m* Hidden location rules now restore the host theme's original featured-image markup by removing the disclosure label and the plugin-added wrapper.[m
[32m+[m[32m* Location selectors can now match the image element itself as well as the disclosure frame or an ancestor, making selector rules more predictable across themes and page builders.[m
[32m+[m[32m* The 3.0.2 compatibility change is generic and adds no Newsblock-specific branch or new theme-specific selector.[m
[32m+[m
 = 3.0.1 =[m
 * Release-hardening update following the official WordPress Plugin Check run for 3.0.0.[m
 * Consolidated AI-admin AJAX input handling so nonce verification, capability checks and sanitization are explicit in the same request boundary.[m
