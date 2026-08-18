( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	const minimumWidth = Math.max( 0, Number( config.minimumImageWidth ) || 0 );
	const minimumTextWidth = Math.max( 0, Number( config.minimumTextWidth ) || 0 );
	const smallImageMode = config.smallImageMode === 'hide' ? 'hide' : 'icon';
	const touchCompactEnabled = !! config.touchCompactMode;
	const iconSizeValue = Math.max( 0, Number( config.iconSizeValue ) || 0 );
	const iconSizeUnit = config.iconSizeUnit === 'percent' ? 'percent' : 'px';
	const tooltipEnabled = !! config.tooltipEnabled;
	const autoColor = !! config.autoColor;
	const locationRulesEnabled = !! config.locationRulesEnabled;
	const iconOnlySelectors = Array.isArray( config.iconOnlySelectors ) ? config.iconOnlySelectors : [];
	const hiddenSelectors = Array.isArray( config.hiddenSelectors ) ? config.hiddenSelectors : [];
	const backgroundOpacity = Math.max( 0, Math.min( 100, parseInt( config.backgroundOpacity, 10 ) || 78 ) ) / 100;
	const featuredAutoColor = config.featuredAutoColor && typeof config.featuredAutoColor === 'object'
		? config.featuredAutoColor
		: null;
	const coloredLabels = new WeakSet();

	/*
	 * Average color of an image via a 1x1 canvas resample. Returns null for
	 * cross-origin images without CORS headers (tainted canvas) and any
	 * other failure, in which case the configured fixed colors remain.
	 */
	function sampleImageColor( image ) {
		if ( ! image || ! image.complete || ! image.naturalWidth ) {
			return null;
		}

		try {
			const canvas = document.createElement( 'canvas' );
			canvas.width = 1;
			canvas.height = 1;
			const context = canvas.getContext( '2d', { willReadFrequently: true } );
			context.drawImage( image, 0, 0, 1, 1 );
			const data = context.getImageData( 0, 0, 1, 1 ).data;

			return { r: data[ 0 ], g: data[ 1 ], b: data[ 2 ] };
		} catch ( error ) {
			return null;
		}
	}


	function linearizeSrgbChannel( value ) {
		const channel = Math.max( 0, Math.min( 255, Number( value ) || 0 ) ) / 255;
		return channel <= 0.04045
			? channel / 12.92
			: Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
	}

	function relativeLuminance( color ) {
		return 0.2126 * linearizeSrgbChannel( color.r ) +
			0.7152 * linearizeSrgbChannel( color.g ) +
			0.0722 * linearizeSrgbChannel( color.b );
	}

	function contrastRatio( first, second ) {
		const lighter = Math.max( first, second );
		const darker = Math.min( first, second );
		return ( lighter + 0.05 ) / ( darker + 0.05 );
	}

	function readableTextColor( color ) {
		const backgroundLuminance = relativeLuminance( color );
		const darkLuminance = relativeLuminance( { r: 17, g: 17, b: 17 } );
		const lightLuminance = 1;
		return contrastRatio( backgroundLuminance, darkLuminance ) >= contrastRatio( backgroundLuminance, lightLuminance )
			? '#111111'
			: '#ffffff';
	}

	function applyProvidedAutoColor( label, colors ) {
		if ( ! label || ! colors || typeof colors !== 'object' ) {
			return false;
		}

		const background = typeof colors.background === 'string' ? colors.background : '';
		const border = typeof colors.border === 'string' ? colors.border : background;
		const text = typeof colors.text === 'string' ? colors.text : '';

		if ( ! background || ! text ) {
			return false;
		}

		label.style.setProperty( '--gd-ai-label-bg', background );
		label.style.setProperty( '--gd-ai-label-border-color', border );
		label.style.setProperty( '--gd-ai-label-color', text );
		coloredLabels.add( label );

		return true;
	}

	function applyAutoColor( label, image ) {
		if ( ! autoColor || ! label || coloredLabels.has( label ) ) {
			return;
		}

		/* Server-side auto colors take precedence when present. */
		if ( label.style.getPropertyValue( '--gd-ai-label-bg' ) ) {
			coloredLabels.add( label );
			return;
		}

		const color = sampleImageColor( image );

		if ( ! color ) {
			return;
		}

		coloredLabels.add( label );

		const rgb = color.r + ',' + color.g + ',' + color.b;
		label.style.setProperty( '--gd-ai-label-bg', 'rgba(' + rgb + ',' + backgroundOpacity + ')' );
		label.style.setProperty( '--gd-ai-label-border-color', 'rgba(' + rgb + ',' + backgroundOpacity + ')' );
		label.style.setProperty( '--gd-ai-label-color', readableTextColor( color ) );
	}
	const needsSizeLogic = minimumWidth > 0 || minimumTextWidth > 0;
	const supportsContainerQueries = !! (
		window.CSS &&
		typeof window.CSS.supports === 'function' &&
		window.CSS.supports( 'container-type', 'inline-size' )
	);
	const observedLabels = new WeakSet();
	const interactiveLabels = new WeakSet();
	let resizeObserver = null;
	let resizeTimer = null;

	function getLabelFrame( label ) {
		return label ? label.closest( '.gd-ai-image-frame, .gd-ai-featured-theme-fallback' ) : null;
	}

	function getLabelImage( label ) {
		const frame = getLabelFrame( label );
		return frame ? frame.querySelector( 'img' ) : null;
	}

	function frameUsesContainerQueries( label ) {
		if ( ! supportsContainerQueries ) {
			return false;
		}

		const frame = getLabelFrame( label );

		if ( ! frame ) {
			return false;
		}

		const type = window.getComputedStyle( frame ).containerType;
		return type === 'inline-size' || type === 'size';
	}

	function mediaQueryMatches( query ) {
		return typeof window.matchMedia === 'function' && window.matchMedia( query ).matches;
	}

	function isTouchFirstDevice() {
		if ( ! touchCompactEnabled ) {
			return false;
		}

		if (
			mediaQueryMatches( '(hover: none) and (pointer: coarse)' ) ||
			mediaQueryMatches( '(any-hover: none) and (any-pointer: coarse)' )
		) {
			return true;
		}

		/*
		 * Fallback for tablet browsers that expose touch points but incomplete
		 * pointer media features (including some browsers in desktop-site mode).
		 * A fine, hover-capable primary pointer wins, so touch-enabled laptops
		 * with a mouse/trackpad keep the normal desktop presentation.
		 */
		const touchPoints = Number( window.navigator && window.navigator.maxTouchPoints ) || 0;
		const hasFinePrimaryPointer = mediaQueryMatches( '(hover: hover) and (pointer: fine)' );

		return touchPoints > 0 && ! hasFinePrimaryPointer;
	}

	function syncTouchCompactClass( label ) {
		if ( ! label ) {
			return false;
		}

		const active = isTouchFirstDevice();
		label.classList.toggle( 'gd-ai-touch-compact-forced', active );

		return active;
	}

	function setFallbackIconSize( label, renderedWidth ) {
		if ( iconSizeUnit === 'percent' && iconSizeValue > 0 ) {
			label.style.setProperty( '--gd-ai-label-icon-size', ( renderedWidth * iconSizeValue / 100 ) + 'px' );
		} else if ( iconSizeValue > 0 ) {
			label.style.setProperty( '--gd-ai-label-icon-size', iconSizeValue + 'px' );
		}
	}

	function getModeForWidth( renderedWidth ) {
		if ( minimumWidth > 0 && renderedWidth < minimumWidth ) {
			return 'hidden';
		}

		if ( minimumTextWidth > 0 && renderedWidth < minimumTextWidth ) {
			return smallImageMode === 'icon' ? 'icon' : 'hidden';
		}

		return 'text';
	}

	function setTooltipOpen( label, open ) {
		if ( ! label ) {
			return;
		}

		const trigger = label.querySelector( '.gd-ai-label-trigger' );
		label.classList.toggle( 'gd-ai-tooltip-open', !! open );

		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function closeTooltips( exceptLabel ) {
		document.querySelectorAll( '.gd-ai-image-label.gd-ai-tooltip-open' ).forEach( function ( label ) {
			if ( label !== exceptLabel ) {
				setTooltipOpen( label, false );
			}
		} );
	}

	function initializeTooltip( label ) {
		if ( ! tooltipEnabled || ! label || interactiveLabels.has( label ) ) {
			return;
		}

		const trigger = label.querySelector( '.gd-ai-label-trigger' );

		if ( ! trigger ) {
			return;
		}

		interactiveLabels.add( label );
		trigger.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			const nextOpen = ! label.classList.contains( 'gd-ai-tooltip-open' );
			closeTooltips( label );
			setTooltipOpen( label, nextOpen );
		} );

		trigger.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			trigger.click();
		} );
	}


	function matchesLocationSelector( label, selectors ) {
		if ( ! locationRulesEnabled || ! label || ! selectors.length ) {
			return false;
		}

		const frame = label.closest( '.gd-ai-image-frame, .gd-ai-featured-theme-fallback' ) || label;
		const image = getLabelImage( label );

		for ( let index = 0; index < selectors.length; index += 1 ) {
			try {
				if (
					frame.matches( selectors[ index ] ) ||
					frame.closest( selectors[ index ] ) ||
					( image && image.matches( selectors[ index ] ) )
				) {
					return true;
				}
			} catch ( error ) {
				/* Ignore invalid selectors rather than breaking all labels. */
			}
		}

		return false;
	}

	function restoreLocationHiddenFeaturedMarkup( label ) {
		if ( ! label ) {
			return false;
		}

		const wrapper = label.closest( '.gd-ai-featured-wrap' );

		if ( wrapper && wrapper.parentNode ) {
			const parent = wrapper.parentNode;
			label.remove();

			while ( wrapper.firstChild ) {
				parent.insertBefore( wrapper.firstChild, wrapper );
			}

			wrapper.remove();
			return true;
		}

		/*
		 * The JavaScript featured-image fallback reuses a theme-owned container
		 * instead of creating a wrapper. If that fallback label is hidden by a
		 * location rule, remove only the classes/data that this plugin added.
		 */
		const fallback = label.closest( '.gd-ai-featured-theme-fallback' );

		if ( fallback && fallback.dataset.gdAiFeaturedLabel === '1' ) {
			label.remove();
			fallback.classList.remove( 'gd-ai-image-frame', 'gd-ai-featured-theme-fallback' );
			delete fallback.dataset.gdAiFeaturedLabel;
			return true;
		}

		return false;
	}

	function getLocationMode( label ) {
		if ( matchesLocationSelector( label, hiddenSelectors ) ) {
			return 'hidden';
		}

		if ( matchesLocationSelector( label, iconOnlySelectors ) ) {
			return 'icon';
		}

		return '';
	}

	function syncTooltipForWidth( label, renderedWidth ) {
		if ( ! tooltipEnabled || ! label || renderedWidth <= 0 ) {
			return;
		}

		const locationMode = getLocationMode( label );
		const touchMode = isTouchFirstDevice() ? 'icon' : '';

		if ( ( locationMode || touchMode || getModeForWidth( renderedWidth ) ) !== 'icon' ) {
			setTooltipOpen( label, false );
		}
	}

	function updateLabelVisibility( label ) {
		if ( ! label ) {
			return;
		}

		syncTouchCompactClass( label );

		const locationMode = getLocationMode( label );

		/*
		 * A featured-image disclosure may add a lightweight wrapper or temporary
		 * classes to a host-theme container. When a location rule explicitly
		 * hides the disclosure, restore the original theme markup so its overlays,
		 * counters, hover effects and structural selectors remain untouched.
		 */
		if ( locationMode === 'hidden' && restoreLocationHiddenFeaturedMarkup( label ) ) {
			return;
		}

		const image = getLabelImage( label );

		if ( ! image ) {
			return;
		}

		applyAutoColor( label, image );

		const renderedWidth = image.getBoundingClientRect().width;

		if ( renderedWidth <= 0 ) {
			return;
		}

		syncTooltipForWidth( label, renderedWidth );

		if ( ! locationMode && frameUsesContainerQueries( label ) ) {
			return;
		}

		setFallbackIconSize( label, renderedWidth );

		const nextMode = locationMode || getModeForWidth( renderedWidth );
		label.classList.remove( 'gd-ai-label-size-hidden', 'gd-ai-label-size-icon', 'gd-ai-label-size-text' );

		if ( nextMode === 'hidden' ) {
			label.classList.add( 'gd-ai-label-size-hidden' );
			label.setAttribute( 'aria-hidden', 'true' );
		} else if ( nextMode === 'icon' ) {
			label.classList.add( 'gd-ai-label-size-icon' );
			label.removeAttribute( 'aria-hidden' );
		} else {
			label.classList.add( 'gd-ai-label-size-text' );
			label.removeAttribute( 'aria-hidden' );
		}
	}

	function observeLabel( label ) {
		if ( ! label || observedLabels.has( label ) ) {
			return;
		}

		observedLabels.add( label );
		initializeTooltip( label );
		syncTouchCompactClass( label );

		if ( ! needsSizeLogic && ! tooltipEnabled && ! autoColor && ! locationRulesEnabled && ! touchCompactEnabled ) {
			return;
		}

		const image = getLabelImage( label );

		if ( ! image ) {
			return;
		}

		updateLabelVisibility( label );

		if ( ! image.complete ) {
			image.addEventListener( 'load', function () {
				updateLabelVisibility( label );
			}, { once: true } );
		}

		if ( resizeObserver ) {
			resizeObserver.observe( image );
		}
	}

	function observeAllLabels( root ) {
		const scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll( '.gd-ai-image-label' ).forEach( observeLabel );
	}

	function getFeaturedSelectors() {
		if ( Array.isArray( config.featuredSelectors ) && config.featuredSelectors.length ) {
			return config.featuredSelectors;
		}

		return [ 'img.wp-post-image' ];
	}

	function addFeaturedLabel() {
		if ( ! config.featuredFallback || ! config.labelText ) {
			return;
		}

		const selectors = getFeaturedSelectors();
		let image = null;

		for ( let index = 0; index < selectors.length; index += 1 ) {
			try {
				image = document.querySelector( selectors[ index ] );
			} catch ( error ) {
				image = null;
			}

			if ( image ) {
				break;
			}
		}

		if ( ! image ) {
			return;
		}

		const serverWrapper = image.closest( '.gd-ai-featured-wrap' );

		if ( serverWrapper && serverWrapper.querySelector( '.gd-ai-image-label' ) ) {
			return;
		}

		let container = image.closest(
			'figure.cs-entry__post-media, figure.post-media, .cs-entry__post-media, ' +
			'.post-thumbnail, .entry-thumbnail, .featured-image, figure'
		);

		if ( ! container ) {
			container = image.parentElement;
		}

		if ( container && container.tagName === 'A' ) {
			container = container.parentElement;
		}

		if ( ! container || container.dataset.gdAiFeaturedLabel === '1' ) {
			return;
		}

		container.classList.add( 'gd-ai-image-frame', 'gd-ai-featured-theme-fallback' );
		container.dataset.gdAiFeaturedLabel = '1';

		const label = document.createElement( 'span' );
		const presetClass = [ 'subtle', 'light', 'pill' ].indexOf( config.preset ) !== -1
			? ' gd-ai-preset-' + config.preset
			: ' gd-ai-preset-custom';
		label.className = 'gd-ai-image-label' + presetClass + ( touchCompactEnabled ? ' gd-ai-touch-compact' : '' );
		label.setAttribute( 'role', 'note' );
		label.setAttribute( 'aria-label', config.labelText );
		label.setAttribute( 'data-gd-ai-featured-label', '1' );
		applyProvidedAutoColor( label, featuredAutoColor );

		const iconWrap = document.createElement( 'span' );
		iconWrap.className = 'gd-ai-label-icon';
		iconWrap.setAttribute( 'aria-hidden', 'true' );
		iconWrap.innerHTML = config.iconHtml || '';

		if ( tooltipEnabled ) {
			const tooltipId = 'gd-ai-label-tooltip-' + Date.now() + '-' + Math.random().toString( 36 ).slice( 2, 8 );
			const trigger = document.createElement( 'span' );
			trigger.className = 'gd-ai-label-trigger';
			trigger.setAttribute( 'role', 'button' );
			trigger.tabIndex = 0;
			trigger.setAttribute( 'aria-expanded', 'false' );
			trigger.setAttribute( 'aria-controls', tooltipId );
			trigger.setAttribute( 'aria-label', config.tooltipButtonLabel || config.labelText );
			trigger.appendChild( iconWrap );
			label.classList.add( 'gd-ai-tooltip-enabled' );
			label.appendChild( trigger );

			const tooltip = document.createElement( 'span' );
			tooltip.id = tooltipId;
			tooltip.className = 'gd-ai-label-tooltip';
			tooltip.setAttribute( 'role', 'tooltip' );
			tooltip.textContent = config.labelText;
			label.appendChild( tooltip );
		} else {
			label.appendChild( iconWrap );
		}

		const textWrap = document.createElement( 'span' );
		textWrap.className = 'gd-ai-label-text';
		textWrap.textContent = config.labelText;
		label.insertBefore( textWrap, label.querySelector( '.gd-ai-label-tooltip' ) );
		container.appendChild( label );
		observeLabel( label );
	}

	function initialize() {
		if ( ( needsSizeLogic || tooltipEnabled || autoColor || locationRulesEnabled || touchCompactEnabled ) && 'ResizeObserver' in window ) {
			resizeObserver = new ResizeObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					const image = entry.target;
					const frame = image.closest( '.gd-ai-image-frame, .gd-ai-featured-theme-fallback' );

					if ( ! frame ) {
						return;
					}

					frame.querySelectorAll( '.gd-ai-image-label' ).forEach( updateLabelVisibility );
				} );
			} );
		}

		addFeaturedLabel();
		observeAllLabels( document );

		if ( ( needsSizeLogic || tooltipEnabled || autoColor || locationRulesEnabled || touchCompactEnabled ) && ! resizeObserver ) {
			window.addEventListener( 'resize', function () {
				window.clearTimeout( resizeTimer );
				resizeTimer = window.setTimeout( function () {
					document.querySelectorAll( '.gd-ai-image-label' ).forEach( updateLabelVisibility );
				}, 100 );
			} );
		}

		if ( touchCompactEnabled && resizeObserver ) {
			window.addEventListener( 'resize', function () {
				window.clearTimeout( resizeTimer );
				resizeTimer = window.setTimeout( function () {
					document.querySelectorAll( '.gd-ai-image-label' ).forEach( updateLabelVisibility );
				}, 100 );
			} );
		}

		if ( tooltipEnabled ) {
			document.addEventListener( 'click', function ( event ) {
				const target = event.target;
				if ( ! target || typeof target.closest !== 'function' || ! target.closest( '.gd-ai-image-label' ) ) {
					closeTooltips();
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Escape' ) {
					closeTooltips();
				}
			} );
		}

		if ( 'MutationObserver' in window ) {
			/*
			 * Batched: heavy DOM activity (page builders, infinite scroll)
			 * triggers one deferred scan instead of one scan per added node.
			 */
			let mutationTimer = null;

			const mutationObserver = new MutationObserver( function ( mutations ) {
				let relevant = false;

				for ( let index = 0; index < mutations.length; index += 1 ) {
					if ( mutations[ index ].addedNodes.length ) {
						relevant = true;
						break;
					}
				}

				if ( ! relevant ) {
					return;
				}

				window.clearTimeout( mutationTimer );
				mutationTimer = window.setTimeout( function () {
					observeAllLabels( document );
				}, 150 );
			} );

			mutationObserver.observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize, { once: true } );
	} else {
		initialize();
	}
} )( window.gdaiidlFrontendConfig );
