( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config ) {
		return;
	}

	const __ = wp.i18n.__;
	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const useEffect = wp.element.useEffect;
	const useRef = wp.element.useRef;
	const useState = wp.element.useState;
	const addFilter = wp.hooks.addFilter;
	const createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	const InspectorControls = wp.blockEditor.InspectorControls;
	const Notice = wp.components.Notice;
	const SelectControl = wp.components.SelectControl;
	const PanelBody = wp.components.PanelBody;
	const Spinner = wp.components.Spinner;
	const TextControl = wp.components.TextControl;
	const ToggleControl = wp.components.ToggleControl;
	const registerPlugin = wp.plugins.registerPlugin;
	const useSelect = wp.data.useSelect;
	const apiFetch = wp.apiFetch;
	const PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	/*
	 * Source-type options come from the server so a single list drives both
	 * editor panels and the settings screen. The inline fallback only runs if
	 * an outdated cached configuration is served without the list.
	 */
	const sourceTypeOptions = Array.isArray( config.sourceTypeOptions ) && config.sourceTypeOptions.length
		? config.sourceTypeOptions
		: [
			{ label: __( 'Use global default', 'ai-image-disclosure-labels' ), value: '' },
			{ label: __( 'Created using generative AI', 'ai-image-disclosure-labels' ), value: 'generated' },
			{ label: __( 'Edited using generative AI', 'ai-image-disclosure-labels' ), value: 'edited' },
			{ label: __( 'Enhanced using AI', 'ai-image-disclosure-labels' ), value: 'enhanced' }
		];

	function defaultTextForSourceType( sourceType, mediaType ) {
		const defaults = mediaType === 'video' && config.defaultVideoTexts ? config.defaultVideoTexts : ( config.defaultTexts || {} );
		const effectiveType = sourceType || config.defaultSourceType || 'generated';
		const publicType = effectiveType === 'generated' ? 'generated' : 'modified';

		if ( Object.prototype.hasOwnProperty.call( defaults, publicType ) ) {
			return defaults[ publicType ] || '';
		}

		return publicType === 'generated' ? ( config.defaultText || 'AI-generated' ) : 'AI-modified';
	}

	const attachmentStatusCache = {};

	function emptyAttachmentStatus() {
		return {
			status: '',
			status_label: '',
			source_type: '',
			source_type_label: ''
		};
	}

	function fetchAttachmentStatus( attachmentId ) {
		const id = parseInt( attachmentId, 10 ) || 0;

		if ( ! id || ! config.attachmentStatusPath ) {
			return Promise.resolve( emptyAttachmentStatus() );
		}

		if ( attachmentStatusCache[ id ] ) {
			return attachmentStatusCache[ id ];
		}

		attachmentStatusCache[ id ] = apiFetch( {
			path: config.attachmentStatusPath + encodeURIComponent( String( id ) ) + '/ai-status'
		} ).then( function ( response ) {
			return {
				status: response && typeof response.status === 'string' ? response.status : '',
				status_label: response && typeof response.status_label === 'string' ? response.status_label : '',
				source_type: response && typeof response.source_type === 'string' ? response.source_type : '',
				source_type_label: response && typeof response.source_type_label === 'string' ? response.source_type_label : ''
			};
		} ).catch( function () {
			return emptyAttachmentStatus();
		} );

		return attachmentStatusCache[ id ];
	}

	function sourceTypeOptionsForAttachment( attachmentData ) {
		const options = sourceTypeOptions.map( function ( option ) {
			return Object.assign( {}, option );
		} );

		if ( ! options.length || options[ 0 ].value !== '' || ! attachmentData || ! attachmentData.status ) {
			return options;
		}

		if ( attachmentData.source_type_label ) {
			options[ 0 ].label = __( 'Use Media Library: ', 'ai-image-disclosure-labels' ) + attachmentData.source_type_label;
		} else if ( attachmentData.status_label ) {
			options[ 0 ].label = __( 'Use Media Library status: ', 'ai-image-disclosure-labels' ) + attachmentData.status_label;
		}

		return options;
	}

	function defaultTextForUsage( sourceType, attachmentData, mediaType ) {
		if ( sourceType ) {
			return defaultTextForSourceType( sourceType, mediaType );
		}

		const defaults = mediaType === 'video' && config.defaultVideoTexts ? config.defaultVideoTexts : ( config.defaultTexts || {} );
		if ( attachmentData && attachmentData.status === 'generated' ) {
			return Object.prototype.hasOwnProperty.call( defaults, 'generated' )
				? ( defaults.generated || '' )
				: ( config.defaultText || 'AI-generated' );
		}

		if ( attachmentData && attachmentData.status === 'modified' ) {
			return Object.prototype.hasOwnProperty.call( defaults, 'modified' )
				? ( defaults.modified || '' )
				: 'AI-modified';
		}

		return defaultTextForSourceType( attachmentData && attachmentData.source_type ? attachmentData.source_type : '', mediaType );
	}

	function inheritedStatusNotice( attachmentData ) {
		if ( ! attachmentData || ! attachmentData.status || ! attachmentData.status_label ) {
			return null;
		}

		return el(
			'div',
			{ className: 'gd-ai-inherited-status' },
			el( 'strong', null, __( 'Media Library status:', 'ai-image-disclosure-labels' ) + ' ' ),
			attachmentData.status_label
		);
	}

	function addMediaAttributes( settings, name ) {
		if ( name !== 'core/image' && name !== 'core/video' ) {
			return settings;
		}

		return Object.assign( {}, settings, {
			attributes: Object.assign( {}, settings.attributes, {
				gdAiLabel: {
					type: 'boolean',
					default: false
				},
				gdAiLabelText: {
					type: 'string',
					default: ''
				},
				gdAiSourceType: {
					type: 'string',
					default: ''
				}
			} )
		} );
	}

	addFilter(
		'blocks.registerBlockType',
		'gdaiidl/add-media-attributes',
		addMediaAttributes
	);

	const withMediaLabelControls = createHigherOrderComponent(
		function ( BlockEdit ) {
			return function ( props ) {
				if ( props.name !== 'core/image' && props.name !== 'core/video' ) {
					return el( BlockEdit, props );
				}

				const isVideo = props.name === 'core/video';

				if ( ( isVideo && config.enableVideos === false ) || ( ! isVideo && config.enableImages === false ) ) {
					return el( BlockEdit, props );
				}

				const enabled = !! props.attributes.gdAiLabel;
				const customText = props.attributes.gdAiLabelText || '';
				const sourceType = props.attributes.gdAiSourceType || '';
				const attachmentId = parseInt( props.attributes.id, 10 ) || 0;
				const [ attachmentData, setAttachmentData ] = useState( emptyAttachmentStatus() );

				useEffect( function () {
					let active = true;
					setAttachmentData( emptyAttachmentStatus() );
					fetchAttachmentStatus( attachmentId ).then( function ( nextData ) {
						if ( active ) {
							setAttachmentData( nextData );
						}
					} );

					return function () {
						active = false;
					};
				}, [ attachmentId ] );

				return el(
					Fragment,
					null,
					el( BlockEdit, props ),
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{
								title: isVideo ? __( 'AI video disclosure', 'ai-image-disclosure-labels' ) : __( 'AI image label', 'ai-image-disclosure-labels' ),
								initialOpen: false
							},
							el( ToggleControl, {
								label: isVideo ? __( 'Show AI disclosure below video', 'ai-image-disclosure-labels' ) : __( 'Show AI label', 'ai-image-disclosure-labels' ),
								checked: enabled,
								onChange: function ( value ) {
									props.setAttributes( { gdAiLabel: !! value } );
								},
								__nextHasNoMarginBottom: true
							} ),
							inheritedStatusNotice( attachmentData ),
							enabled && el( TextControl, {
								label: __( 'Custom text', 'ai-image-disclosure-labels' ),
								value: customText,
								placeholder: defaultTextForUsage( sourceType, attachmentData, isVideo ? 'video' : 'image' ),
								help: __( 'Leave empty to use the matching AI-generated or AI-modified text from the plugin settings.', 'ai-image-disclosure-labels' ),
								onChange: function ( value ) {
									props.setAttributes( { gdAiLabelText: value } );
								},
								__nextHasNoMarginBottom: true
							} ),
							enabled && config.machineReadableEnabled && el( SelectControl, {
								label: __( 'AI source type', 'ai-image-disclosure-labels' ),
								value: sourceType,
								options: sourceTypeOptionsForAttachment( attachmentData ),
								help: __( 'Leave this on the Media Library choice to inherit the attachment classification. Choose another value only for a per-block machine-readable override.', 'ai-image-disclosure-labels' ),
								onChange: function ( value ) {
									props.setAttributes( { gdAiSourceType: value } );
								},
								__nextHasNoMarginBottom: true
							} )
						)
					)
				);
			};
		},
		'withMediaLabelControls'
	);

	addFilter(
		'editor.BlockEdit',
		'gdaiidl/media-controls',
		withMediaLabelControls
	);

	const withMediaLabelPreview = createHigherOrderComponent(
		function ( BlockListBlock ) {
			return function ( props ) {
				if ( props.name !== 'core/image' && props.name !== 'core/video' ) {
					return el( BlockListBlock, props );
				}

				const isVideo = props.name === 'core/video';

				if ( ( isVideo && config.enableVideos === false ) || ( ! isVideo && config.enableImages === false ) ) {
					return el( BlockListBlock, props );
				}

				const attributes = props.attributes || {};
				const attachmentId = parseInt( attributes.id, 10 ) || 0;
				const [ attachmentData, setAttachmentData ] = useState( emptyAttachmentStatus() );

				useEffect( function () {
					let active = true;
					fetchAttachmentStatus( attachmentId ).then( function ( nextData ) {
						if ( active ) {
							setAttachmentData( nextData );
						}
					} );

					return function () {
						active = false;
					};
				}, [ attachmentId ] );

				if ( ! attributes.gdAiLabel ) {
					return el( BlockListBlock, props );
				}

				const text = attributes.gdAiLabelText || defaultTextForUsage( attributes.gdAiSourceType || '', attachmentData, isVideo ? 'video' : 'image' );

				if ( ! text ) {
					return el( BlockListBlock, props );
				}

				const wrapperProps = Object.assign( {}, props.wrapperProps || {} );
				wrapperProps.className = (
					( wrapperProps.className || '' ) +
					( isVideo
						? ' gd-ai-editor-video-preview gd-ai-editor-video-align-' + ( config.videoAlignment || ( String( config.position || '' ).indexOf( 'left' ) !== -1 ? 'left' : 'right' ) )
						: ' gd-ai-editor-preview gd-ai-editor-position-' + config.position )
				).trim();
				wrapperProps['data-gd-ai-label'] = text;

				return el(
					BlockListBlock,
					Object.assign( {}, props, { wrapperProps: wrapperProps } )
				);
			};
		},
		'withMediaLabelPreview'
	);

	addFilter(
		'editor.BlockListBlock',
		'gdaiidl/media-preview',
		withMediaLabelPreview
	);

	function FeaturedImageLabelPanel() {
		const editorContext = useSelect( function ( select ) {
			const editor = select( 'core/editor' );

			return {
				postId: editor.getCurrentPostId(),
				postType: editor.getCurrentPostType(),
				featuredMediaId: parseInt( editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'featured_media' ) : 0, 10 ) || 0
			};
		}, [] );

		const postId = editorContext.postId;
		const postType = editorContext.postType;
		const featuredMediaId = editorContext.featuredMediaId;
		const [ enabled, setEnabled ] = useState( false );
		const [ customText, setCustomText ] = useState( '' );
		const [ sourceType, setSourceType ] = useState( '' );
		const [ attachmentData, setAttachmentData ] = useState( emptyAttachmentStatus() );
		const [ loading, setLoading ] = useState( true );
		const [ saving, setSaving ] = useState( false );
		const [ saved, setSaved ] = useState( false );
		const [ error, setError ] = useState( '' );
		const mountedRef = useRef( true );
		const requestSequenceRef = useRef( 0 );
		const saveQueueRef = useRef( Promise.resolve() );
		const confirmedStateRef = useRef( { enabled: false, text: '', sourceType: '' } );
		const textTimerRef = useRef( null );
		const savedTimerRef = useRef( null );
		const lastSavedTextRef = useRef( '' );

		useEffect( function () {
			return function () {
				mountedRef.current = false;
				window.clearTimeout( textTimerRef.current );
				window.clearTimeout( savedTimerRef.current );
			};
		}, [] );

		useEffect( function () {
			let active = true;

			if (
				! postId ||
				config.allowedPostTypes.indexOf( postType ) === -1
			) {
				setLoading( false );
				return function () {
					active = false;
				};
			}

			setLoading( true );
			setError( '' );
			setSaved( false );

			apiFetch( {
				path: config.restPath + encodeURIComponent( String( postId ) )
			} ).then( function ( response ) {
				if ( ! active || ! mountedRef.current ) {
					return;
				}

				const nextText = response && typeof response.text === 'string'
					? response.text
					: '';

				const nextSourceType = response && typeof response.source_type === 'string'
					? response.source_type
					: '';

				const nextEnabled = !! ( response && response.enabled );
				confirmedStateRef.current = {
					enabled: nextEnabled,
					text: nextText,
					sourceType: nextSourceType
				};
				setEnabled( nextEnabled );
				setCustomText( nextText );
				setSourceType( nextSourceType );
				lastSavedTextRef.current = nextText;
			} ).catch( function ( requestError ) {
				if ( ! active || ! mountedRef.current ) {
					return;
				}

				setError(
					requestError && requestError.message
						? requestError.message
						: __( 'The saved label could not be loaded.', 'ai-image-disclosure-labels' )
				);
			} ).finally( function () {
				if ( active && mountedRef.current ) {
					setLoading( false );
				}
			} );

			return function () {
				active = false;
			};
		}, [ postId, postType ] );

		useEffect( function () {
			let active = true;
			setAttachmentData( emptyAttachmentStatus() );

			fetchAttachmentStatus( featuredMediaId ).then( function ( nextData ) {
				if ( active && mountedRef.current ) {
					setAttachmentData( nextData );
				}
			} );

			return function () {
				active = false;
			};
		}, [ featuredMediaId ] );

		function showSavedState() {
			window.clearTimeout( savedTimerRef.current );
			setSaved( true );
			savedTimerRef.current = window.setTimeout( function () {
				if ( mountedRef.current ) {
					setSaved( false );
				}
			}, 2200 );
		}

		function persist( nextEnabled, nextText, nextSourceType ) {
			if ( ! postId ) {
				return Promise.reject( new Error( __( 'The post ID is not available yet.', 'ai-image-disclosure-labels' ) ) );
			}

			const sequence = requestSequenceRef.current + 1;
			requestSequenceRef.current = sequence;
			setSaving( true );
			setSaved( false );
			setError( '' );

			const request = saveQueueRef.current.catch( function () {
				/* Keep later writes running even if an earlier request failed. */
			} ).then( function () {
				return apiFetch( {
					path: config.restPath + encodeURIComponent( String( postId ) ),
					method: 'POST',
					data: {
						enabled: !! nextEnabled,
						text: nextText || '',
						source_type: nextSourceType || ''
					}
				} );
			} );

			saveQueueRef.current = request;

			return request.then( function ( response ) {
				const savedText = response && typeof response.text === 'string'
					? response.text
					: '';
				const savedSourceType = response && typeof response.source_type === 'string'
					? response.source_type
					: '';
				const savedEnabled = !! ( response && response.enabled );

				confirmedStateRef.current = {
					enabled: savedEnabled,
					text: savedText,
					sourceType: savedSourceType
				};

				if ( ! mountedRef.current || sequence !== requestSequenceRef.current ) {
					return response;
				}

				setEnabled( savedEnabled );
				setCustomText( savedText );
				setSourceType( savedSourceType );
				lastSavedTextRef.current = savedText;
				showSavedState();
				return response;
			} ).catch( function ( requestError ) {
				if ( mountedRef.current && sequence === requestSequenceRef.current ) {
					const confirmed = confirmedStateRef.current;
					setEnabled( confirmed.enabled );
					setCustomText( confirmed.text );
					setSourceType( confirmed.sourceType );
					lastSavedTextRef.current = confirmed.text;
					setError(
						requestError && requestError.message
							? requestError.message
							: __( 'The label could not be saved.', 'ai-image-disclosure-labels' )
					);
				}

				throw requestError;
			} ).finally( function () {
				if ( mountedRef.current && sequence === requestSequenceRef.current ) {
					setSaving( false );
				}
			} );
		}

		function changeEnabled( value ) {
			const next = !! value;
			window.clearTimeout( textTimerRef.current );
			setEnabled( next );
			persist( next, customText, sourceType ).catch( function () {} );
		}

		function changeText( value ) {
			setCustomText( value );
			window.clearTimeout( textTimerRef.current );

			textTimerRef.current = window.setTimeout( function () {
				if ( value !== lastSavedTextRef.current ) {
					persist( enabled, value, sourceType ).catch( function () {} );
				}
			}, 500 );
		}

		function saveTextOnBlur() {
			window.clearTimeout( textTimerRef.current );

			if ( customText !== lastSavedTextRef.current ) {
				persist( enabled, customText, sourceType ).catch( function () {} );
			}
		}

		function changeSourceType( value ) {
			window.clearTimeout( textTimerRef.current );
			setSourceType( value );
			persist( enabled, customText, value ).catch( function () {} );
		}

		if ( config.enableImages === false || config.allowedPostTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'gd-ai-featured-image-label',
				title: __( 'AI label for featured image', 'ai-image-disclosure-labels' ),
				className: 'gd-ai-featured-panel'
			},
			loading && el(
				'div',
				{ className: 'gd-ai-panel-loading' },
				el( Spinner ),
				el( 'span', null, __( 'Loading label …', 'ai-image-disclosure-labels' ) )
			),
			! loading && el( ToggleControl, {
				label: __( 'Show label on the featured image', 'ai-image-disclosure-labels' ),
				checked: enabled,
				onChange: changeEnabled,
				help: attachmentData.status ? __( 'The Media Library classification is inherited automatically. This toggle controls an explicit per-post label override; automatic Media Library display still follows the plugin settings.', 'ai-image-disclosure-labels' ) : __( 'The choice is saved immediately. Nothing is output unless enabled.', 'ai-image-disclosure-labels' ),
				__nextHasNoMarginBottom: true
			} ),
			! loading && inheritedStatusNotice( attachmentData ),
			! loading && enabled && el( TextControl, {
				label: __( 'Custom text', 'ai-image-disclosure-labels' ),
				value: customText,
				placeholder: defaultTextForUsage( sourceType, attachmentData, 'image' ),
				onChange: changeText,
				onBlur: saveTextOnBlur,
				help: __( 'Leave empty to use the matching AI-generated or AI-modified text from the plugin settings.', 'ai-image-disclosure-labels' ),
				__nextHasNoMarginBottom: true
			} ),
			! loading && enabled && config.machineReadableEnabled && el( SelectControl, {
				label: __( 'AI source type', 'ai-image-disclosure-labels' ),
				value: sourceType,
				options: sourceTypeOptionsForAttachment( attachmentData ),
				onChange: changeSourceType,
				help: __( 'Leave this on the Media Library choice to inherit the featured attachment classification. Choose another value only for a per-post machine-readable override.', 'ai-image-disclosure-labels' ),
				__nextHasNoMarginBottom: true
			} ),
			! loading && el(
				'div',
				{ className: 'gd-ai-save-status', 'aria-live': 'polite' },
				saving && el( Fragment, null, el( Spinner ), el( 'span', null, __( 'Saving …', 'ai-image-disclosure-labels' ) ) ),
				! saving && saved && el( 'span', { className: 'gd-ai-save-status__saved' }, __( 'Saved', 'ai-image-disclosure-labels' ) )
			),
			error && el(
				Notice,
				{
					status: 'error',
					isDismissible: false,
					className: 'gd-ai-panel-error'
				},
				error
			)
		);
	}

	if ( PluginDocumentSettingPanel ) {
		registerPlugin( 'gdaiidl-featured', {
			render: FeaturedImageLabelPanel,
			icon: 'format-image'
		} );
	}
} )( window.wp, window.gdaiidlEditorConfig );
