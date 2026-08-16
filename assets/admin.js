( function ( config, wp ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	const __ = wp && wp.i18n ? wp.i18n.__ : function ( text ) { return text; };

	const byId = function ( id ) {
		return document.getElementById( id );
	};

	const fields = {
		labelText: byId( 'gd-ai-label-text' ),
		labelTextModified: byId( 'gd-ai-label-text-modified' ),
		previewTextType: byId( 'gd-ai-preview-text-type' ),
		enableImages: byId( 'gd-ai-enable-images' ),
		enableVideos: byId( 'gd-ai-enable-videos' ),
		videoSeparateDesign: byId( 'gd-ai-video-separate-design' ),
		videoLabelText: byId( 'gd-ai-video-label-text' ),
		videoLabelTextModified: byId( 'gd-ai-video-label-text-modified' ),
		videoAlignment: byId( 'gd-ai-video-alignment' ),
		videoBackgroundColor: byId( 'gd-ai-video-background-color' ),
		videoBackgroundOpacity: byId( 'gd-ai-video-background-opacity' ),
		videoTextColor: byId( 'gd-ai-video-text-color' ),
		videoBorderColor: byId( 'gd-ai-video-border-color' ),
		videoBorderWidth: byId( 'gd-ai-video-border-width' ),
		videoBorderRadius: byId( 'gd-ai-video-border-radius' ),
		videoFontSize: byId( 'gd-ai-video-font-size' ),
		videoFontWeight: byId( 'gd-ai-video-font-weight' ),
		videoPaddingVertical: byId( 'gd-ai-video-padding-vertical' ),
		videoPaddingHorizontal: byId( 'gd-ai-video-padding-horizontal' ),
		videoTextTransform: byId( 'gd-ai-video-text-transform' ),
		position: byId( 'gd-ai-position' ),
		backgroundColor: byId( 'gd-ai-background-color' ),
		backgroundOpacity: byId( 'gd-ai-background-opacity' ),
		textColor: byId( 'gd-ai-text-color' ),
		borderColor: byId( 'gd-ai-border-color' ),
		borderWidth: byId( 'gd-ai-border-width' ),
		borderRadius: byId( 'gd-ai-border-radius' ),
		fontSize: byId( 'gd-ai-font-size' ),
		fontWeight: byId( 'gd-ai-font-weight' ),
		paddingVertical: byId( 'gd-ai-padding-vertical' ),
		paddingHorizontal: byId( 'gd-ai-padding-horizontal' ),
		offsetVertical: byId( 'gd-ai-offset-vertical' ),
		offsetHorizontal: byId( 'gd-ai-offset-horizontal' ),
		textTransform: byId( 'gd-ai-text-transform' ),
		minimumImageWidth: byId( 'gd-ai-minimum-image-width' ),
		minimumTextWidth: byId( 'gd-ai-minimum-text-width' ),
		smallImageMode: byId( 'gd-ai-small-image-mode' ),
		iconSizeValue: byId( 'gd-ai-icon-size-value' ),
		iconSizeUnit: byId( 'gd-ai-icon-size-unit' ),
		customIconId: byId( 'gd-ai-custom-icon-id' ),
		iconTooltipEnabled: byId( 'gd-ai-icon-tooltip-enabled' ),
		backgroundColorMode: byId( 'gd-ai-background-color-mode' ),
		fontFamilyMode: byId( 'gd-ai-font-family-mode' ),
		fontFamilyCustom: byId( 'gd-ai-font-family-custom' )
	};

	const fontStacks = config.fontStacks || {};
	const fontCustomField = byId( 'gd-ai-font-family-custom-field' );

	function previewFontFamily() {
		const mode = fields.fontFamilyMode ? fields.fontFamilyMode.value : 'inherit';

		if ( mode === 'custom' ) {
			return fields.fontFamilyCustom ? fields.fontFamilyCustom.value : '';
		}

		return fontStacks[ mode ] || '';
	}

	function isAutoColor() {
		return !! ( fields.backgroundColorMode && fields.backgroundColorMode.checked );
	}

	function syncColorModeUi() {
		const auto = isAutoColor();
		const colorInputs = [ fields.backgroundColor, fields.textColor, fields.borderColor ];

		colorInputs.forEach( function ( input ) {
			if ( input ) {
				const field = input.closest( '.gd-ai-field' );

				input.setAttribute( 'aria-disabled', auto ? 'true' : 'false' );
				input.tabIndex = auto ? -1 : 0;

				if ( field ) {
					field.classList.toggle( 'gd-ai-field-disabled', auto );
				}
			}
		} );
	}

	const preview = byId( 'gd-ai-preview' );
	const videoPreview = byId( 'gd-ai-video-preview' );
	const videoPreviewRow = byId( 'gd-ai-video-preview-row' );
	const videoPreviewLabel = byId( 'gd-ai-video-preview-label' );
	const videoDesignFields = document.querySelector( '.gd-ai-video-design-fields' );
	const label = byId( 'gd-ai-preview-label' );
	const symbolPreview = byId( 'gd-ai-symbol-preview' );
	const previewSymbol = byId( 'gd-ai-preview-symbol' );
	const previewSymbolIcon = byId( 'gd-ai-preview-symbol-icon' );
	const previewSymbolTooltip = byId( 'gd-ai-preview-symbol-tooltip' );
	const customCardPreview = byId( 'gd-ai-custom-icon-card-preview' );
	const selectCustomIconButton = byId( 'gd-ai-select-custom-icon' );
	const removeCustomIconButton = byId( 'gd-ai-remove-custom-icon' );
	const customPreset = byId( 'gd-ai-preset-custom' );
	const customIconRadio = byId( 'gd-ai-icon-style-custom' );
	let applyingPreset = false;
	let mediaFrame = null;
	let resizeTimer = null;
	let customIcon = config.customIcon || null;

	if ( ! preview || ! label || ! previewSymbol || ! previewSymbolIcon ) {
		return;
	}

	function hexToRgba( hex, opacity ) {
		const clean = ( hex || '#171717' ).replace( '#', '' );
		const normalized = clean.length === 3
			? clean.split( '' ).map( function ( char ) { return char + char; } ).join( '' )
			: clean;
		const number = parseInt( normalized, 16 );

		if ( Number.isNaN( number ) ) {
			return 'rgba(23,23,23,0.78)';
		}

		return 'rgba(' +
			( number >> 16 ) + ',' +
			( ( number >> 8 ) & 255 ) + ',' +
			( number & 255 ) + ',' +
			Math.max( 0, Math.min( 100, Number( opacity ) || 0 ) ) / 100 +
		')';
	}

	function value( key, fallback ) {
		return fields[ key ] ? fields[ key ].value : fallback;
	}

	function previewLabelText() {
		const type = value( 'previewTextType', 'generated' );

		if ( type === 'modified' ) {
			return value( 'labelTextModified', 'AI-modified' );
		}

		return value( 'labelText', 'AI-generated' );
	}


	function videoPreviewLabelText() {
		const type = value( 'previewTextType', 'generated' );
		if ( type === 'modified' ) {
			return value( 'videoLabelTextModified', '' ) || value( 'labelTextModified', 'AI-modified' );
		}
		return value( 'videoLabelText', '' ) || value( 'labelText', 'AI-generated' );
	}

	function separateVideoDesign() {
		return !! ( fields.videoSeparateDesign && fields.videoSeparateDesign.checked );
	}

	function syncVideoDesignUi() {
		const separate = separateVideoDesign();

		if ( videoDesignFields ) {
			videoDesignFields.hidden = ! separate;
		}

	}

	function getSelectedIconStyle() {
		const selected = document.querySelector( '.gd-ai-icon-choice input[type="radio"]:checked' );
		return selected ? selected.value : 'monogram';
	}

	function escapeAttribute( valueToEscape ) {
		return String( valueToEscape || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function iconMarkup( style ) {
		if ( style === 'custom' && customIcon && customIcon.url ) {
			return '<img class="gd-ai-icon gd-ai-icon-custom" src="' +
				escapeAttribute( customIcon.url ) + '" alt="">';
		}

		return config.icons && config.icons[ style ]
			? config.icons[ style ]
			: ( config.icons ? config.icons.monogram : '' );
	}

	function applyBadgeAppearance( element, iconOnly ) {
		element.style.backgroundColor = hexToRgba(
			value( 'backgroundColor', '#171717' ),
			value( 'backgroundOpacity', 78 )
		);
		element.style.color = value( 'textColor', '#ffffff' );
		element.style.borderColor = value( 'borderColor', '#ffffff' );
		element.style.borderWidth = value( 'borderWidth', 1 ) + 'px';
		element.style.borderStyle = 'solid';
		element.style.borderRadius = value( 'borderRadius', 3 ) + 'px';
		element.style.fontSize = value( 'fontSize', 9 ) + 'px';
		element.style.fontWeight = value( 'fontWeight', 600 );
		element.style.padding = iconOnly
			? Math.max( 2, Number( value( 'paddingVertical', 3 ) ) || 0 ) + 'px ' +
				Math.max( 3, ( Number( value( 'paddingHorizontal', 5 ) ) || 0 ) - 1 ) + 'px'
			: value( 'paddingVertical', 3 ) + 'px ' + value( 'paddingHorizontal', 5 ) + 'px';
		element.style.textTransform = value( 'textTransform', 'none' );
		element.style.fontFamily = previewFontFamily();
	}


	function applyVideoBadgeAppearance( element ) {
		if ( ! element ) {
			return;
		}

		if ( ! separateVideoDesign() ) {
			applyBadgeAppearance( element, false );
			return;
		}

		element.style.backgroundColor = hexToRgba( value( 'videoBackgroundColor', '#171717' ), value( 'videoBackgroundOpacity', 78 ) );
		element.style.color = value( 'videoTextColor', '#ffffff' );
		element.style.borderColor = value( 'videoBorderColor', '#ffffff' );
		element.style.borderWidth = value( 'videoBorderWidth', 1 ) + 'px';
		element.style.borderStyle = 'solid';
		element.style.borderRadius = value( 'videoBorderRadius', 3 ) + 'px';
		element.style.fontSize = value( 'videoFontSize', 9 ) + 'px';
		element.style.fontWeight = value( 'videoFontWeight', 600 );
		element.style.padding = value( 'videoPaddingVertical', 3 ) + 'px ' + value( 'videoPaddingHorizontal', 5 ) + 'px';
		element.style.textTransform = value( 'videoTextTransform', 'none' );
		element.style.fontFamily = previewFontFamily();
	}

	function updateIconSizeConstraints() {
		if ( ! fields.iconSizeValue || ! fields.iconSizeUnit ) {
			return;
		}

		if ( fields.iconSizeUnit.value === 'percent' ) {
			fields.iconSizeValue.min = '1';
			fields.iconSizeValue.max = '30';
		} else {
			fields.iconSizeValue.min = '8';
			fields.iconSizeValue.max = '80';
		}
	}

	function getPreviewIconSize() {
		const numericValue = Math.max( 0, Number( value( 'iconSizeValue', 16 ) ) || 0 );

		if ( value( 'iconSizeUnit', 'px' ) === 'percent' ) {
			const baseWidth = symbolPreview ? symbolPreview.getBoundingClientRect().width : 250;
			return Math.max( 1, baseWidth * numericValue / 100 );
		}

		return numericValue;
	}

	function updateCustomCardPreview() {
		if ( ! customCardPreview ) {
			return;
		}

		if ( customIcon && customIcon.url ) {
			customCardPreview.innerHTML = '<img src="' + escapeAttribute( customIcon.url ) + '" alt="">';
		} else {
			customCardPreview.innerHTML = '<span class="dashicons dashicons-upload"></span>';
		}

		if ( removeCustomIconButton ) {
			removeCustomIconButton.disabled = ! ( customIcon && customIcon.url );
		}
	}

	function updatePreview() {
		syncColorModeUi();

		if ( fontCustomField && fields.fontFamilyMode ) {
			fontCustomField.hidden = fields.fontFamilyMode.value !== 'custom';
		}

		const previewText = previewLabelText();
		label.textContent = previewText;
		label.style.display = previewText ? '' : 'none';
		previewSymbol.style.display = previewText ? '' : 'none';
		applyBadgeAppearance( label, false );

		label.style.top = 'auto';
		label.style.right = 'auto';
		label.style.bottom = 'auto';
		label.style.left = 'auto';

		const position = value( 'position', 'bottom-right' );
		const vertical = value( 'offsetVertical', 7 ) + 'px';
		const horizontal = value( 'offsetHorizontal', 7 ) + 'px';

		if ( position.indexOf( 'top-' ) === 0 ) {
			label.style.top = vertical;
		} else {
			label.style.bottom = vertical;
		}

		if ( position.indexOf( '-left' ) !== -1 ) {
			label.style.left = horizontal;
		} else {
			label.style.right = horizontal;
		}

		syncVideoDesignUi();
		if ( preview ) {
			preview.classList.toggle( 'gd-ai-preview-disabled', !! ( fields.enableImages && ! fields.enableImages.checked ) );
		}
		if ( videoPreview && videoPreviewLabel && videoPreviewRow ) {
			const videoText = videoPreviewLabelText();
			videoPreview.classList.toggle( 'gd-ai-preview-disabled', !! ( fields.enableVideos && ! fields.enableVideos.checked ) );
			videoPreviewLabel.textContent = videoText;
			videoPreviewLabel.style.display = videoText ? '' : 'none';
			applyVideoBadgeAppearance( videoPreviewLabel );
			let videoAlignment = separateVideoDesign() ? value( 'videoAlignment', 'right' ) : ( position.indexOf( '-left' ) !== -1 ? 'left' : 'right' );
			if ( [ 'left', 'center', 'right' ].indexOf( videoAlignment ) === -1 ) {
				videoAlignment = 'right';
			}
			videoPreviewRow.className = 'gd-ai-video-preview-row gd-ai-video-preview-align-' + videoAlignment;
		}

		applyBadgeAppearance( previewSymbol, true );
		previewSymbolIcon.innerHTML = iconMarkup( getSelectedIconStyle() );
		const iconSize = getPreviewIconSize();
		previewSymbolIcon.style.width = iconSize + 'px';
		previewSymbolIcon.style.height = iconSize + 'px';

		if ( previewSymbolTooltip ) {
			previewSymbolTooltip.textContent = previewText;
		}

		const tooltipEnabled = !! ( fields.iconTooltipEnabled && fields.iconTooltipEnabled.checked );
		previewSymbol.classList.toggle( 'gd-ai-preview-tooltip-enabled', tooltipEnabled );
		previewSymbol.setAttribute( 'aria-disabled', tooltipEnabled ? 'false' : 'true' );
		previewSymbol.tabIndex = tooltipEnabled ? 0 : -1;
		if ( ! tooltipEnabled ) {
			previewSymbol.classList.remove( 'gd-ai-preview-tooltip-open' );
			previewSymbol.setAttribute( 'aria-expanded', 'false' );
		}

		updateIconSizeConstraints();
		updateCustomCardPreview();
	}

	function setField( key, valueToSet ) {
		if ( fields[ key ] ) {
			fields[ key ].value = valueToSet;
		}
	}

	function applyPreset( presetName ) {
		const preset = config.presets ? config.presets[ presetName ] : null;

		if ( ! preset ) {
			return;
		}

		applyingPreset = true;

		try {
			setField( 'backgroundColor', preset.background_color );
			setField( 'backgroundOpacity', preset.background_opacity );
			setField( 'textColor', preset.text_color );
			setField( 'borderColor', preset.border_color );
			setField( 'borderWidth', preset.border_width );
			setField( 'borderRadius', preset.border_radius );
			setField( 'fontSize', preset.font_size );
			setField( 'fontWeight', preset.font_weight );
			setField( 'paddingVertical', preset.padding_vertical );
			setField( 'paddingHorizontal', preset.padding_horizontal );
			updatePreview();
		} finally {
			applyingPreset = false;
		}
	}

	function isAllowedIcon( attachment ) {
		const mime = attachment.get( 'mime' ) || '';
		const filename = String( attachment.get( 'filename' ) || attachment.get( 'url' ) || '' ).toLowerCase();
		return ( config.allowedMimeTypes || [] ).indexOf( mime ) !== -1 || /\.(png|svg)$/.test( filename );
	}

	function openMediaFrame() {
		if ( ! wp || ! wp.media ) {
			return;
		}

		if ( mediaFrame ) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media( {
			title: __( 'Select AI symbol', 'ai-image-disclosure-labels' ),
			button: { text: __( 'Use symbol', 'ai-image-disclosure-labels' ) },
			library: { type: 'image' },
			multiple: false
		} );

		mediaFrame.on( 'select', function () {
			const attachment = mediaFrame.state().get( 'selection' ).first();

			if ( ! attachment || ! isAllowedIcon( attachment ) ) {
				window.alert( __( 'Please select a PNG or SVG file.', 'ai-image-disclosure-labels' ) );
				return;
			}

			customIcon = {
				id: attachment.get( 'id' ),
				url: attachment.get( 'url' ),
				mime: attachment.get( 'mime' )
			};

			if ( fields.customIconId ) {
				fields.customIconId.value = customIcon.id;
			}

			if ( customIconRadio ) {
				customIconRadio.checked = true;
			}

			updatePreview();
		} );

		mediaFrame.open();
	}

	document.querySelectorAll( '.gd-ai-preset-card' ).forEach( function ( card ) {
		const input = card.querySelector( 'input[type="radio"]' );

		if ( ! input ) {
			return;
		}

		card.addEventListener( 'click', function () {
			input.checked = true;
			applyPreset( input.value );
		} );

		input.addEventListener( 'change', function () {
			applyPreset( input.value );
		} );
	} );

	document.querySelectorAll( '.gd-ai-icon-choice input[type="radio"]' ).forEach( function ( input ) {
		input.addEventListener( 'change', updatePreview );
	} );

	if ( selectCustomIconButton ) {
		selectCustomIconButton.addEventListener( 'click', openMediaFrame );
	}

	if ( removeCustomIconButton ) {
		removeCustomIconButton.addEventListener( 'click', function () {
			customIcon = null;

			if ( fields.customIconId ) {
				fields.customIconId.value = '';
			}

			if ( customIconRadio && customIconRadio.checked ) {
				const monogram = document.querySelector( '.gd-ai-icon-choice input[value="monogram"]' );
				if ( monogram ) {
					monogram.checked = true;
				}
			}

			updatePreview();
		} );
	}

	if ( fields.videoSeparateDesign ) {
		fields.videoSeparateDesign.addEventListener( 'change', function () {
			syncVideoDesignUi();
			updatePreview();
		} );
	}

	const designFieldKeys = [
		'backgroundColor', 'backgroundOpacity', 'textColor', 'borderColor',
		'borderWidth', 'borderRadius', 'fontSize', 'fontWeight',
		'paddingVertical', 'paddingHorizontal'
	];

	Object.keys( fields ).forEach( function ( key ) {
		if ( ! fields[ key ] ) {
			return;
		}

		const handleFieldChange = function () {
			if ( ! applyingPreset && designFieldKeys.indexOf( key ) !== -1 && customPreset ) {
				customPreset.checked = true;
			}

			updatePreview();
		};

		fields[ key ].addEventListener( 'input', handleFieldChange );
		fields[ key ].addEventListener( 'change', handleFieldChange );
	} );

	window.addEventListener( 'resize', function () {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( updatePreview, 100 );
	} );
	previewSymbol.addEventListener( 'click', function () {
		if ( ! fields.iconTooltipEnabled || ! fields.iconTooltipEnabled.checked ) {
			return;
		}

		const open = ! previewSymbol.classList.contains( 'gd-ai-preview-tooltip-open' );
		previewSymbol.classList.toggle( 'gd-ai-preview-tooltip-open', open );
		previewSymbol.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );

	previewSymbol.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' || event.key === ' ' ) {
			event.preventDefault();
			previewSymbol.click();
		} else if ( event.key === 'Escape' ) {
			previewSymbol.classList.remove( 'gd-ai-preview-tooltip-open' );
			previewSymbol.setAttribute( 'aria-expanded', 'false' );
		}
	} );

	updatePreview();
} )( window.gdaiidlAdminConfig, window.wp );
