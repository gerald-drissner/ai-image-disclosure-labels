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

	function addImageAttributes( settings, name ) {
		if ( name !== 'core/image' ) {
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
				}
			} )
		} );
	}

	addFilter(
		'blocks.registerBlockType',
		'gd-ai-image-labels/add-image-attributes',
		addImageAttributes
	);

	const withImageLabelControls = createHigherOrderComponent(
		function ( BlockEdit ) {
			return function ( props ) {
				if ( props.name !== 'core/image' ) {
					return el( BlockEdit, props );
				}

				const enabled = !! props.attributes.gdAiLabel;
				const customText = props.attributes.gdAiLabelText || '';

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
								title: __( 'AI image label', 'ai-image-disclosure-labels' ),
								initialOpen: false
							},
							el( ToggleControl, {
								label: __( 'Show AI label', 'ai-image-disclosure-labels' ),
								checked: enabled,
								onChange: function ( value ) {
									props.setAttributes( { gdAiLabel: !! value } );
								},
								__nextHasNoMarginBottom: true
							} ),
							enabled && el( TextControl, {
								label: __( 'Custom text', 'ai-image-disclosure-labels' ),
								value: customText,
								placeholder: config.defaultText,
								help: __( 'Leave empty to use the default text from the plugin settings.', 'ai-image-disclosure-labels' ),
								onChange: function ( value ) {
									props.setAttributes( { gdAiLabelText: value } );
								},
								__nextHasNoMarginBottom: true
							} )
						)
					)
				);
			};
		},
		'withImageLabelControls'
	);

	addFilter(
		'editor.BlockEdit',
		'gd-ai-image-labels/image-controls',
		withImageLabelControls
	);

	const withImageLabelPreview = createHigherOrderComponent(
		function ( BlockListBlock ) {
			return function ( props ) {
				if (
					props.name !== 'core/image' ||
					! props.attributes ||
					! props.attributes.gdAiLabel
				) {
					return el( BlockListBlock, props );
				}

				const text = props.attributes.gdAiLabelText || config.defaultText;

				if ( ! text ) {
					return el( BlockListBlock, props );
				}

				const wrapperProps = Object.assign( {}, props.wrapperProps || {} );
				wrapperProps.className = (
					( wrapperProps.className || '' ) +
					' gd-ai-editor-preview gd-ai-editor-position-' +
					config.position
				).trim();
				wrapperProps['data-gd-ai-label'] = text;

				return el(
					BlockListBlock,
					Object.assign( {}, props, { wrapperProps: wrapperProps } )
				);
			};
		},
		'withImageLabelPreview'
	);

	addFilter(
		'editor.BlockListBlock',
		'gd-ai-image-labels/image-preview',
		withImageLabelPreview
	);

	function FeaturedImageLabelPanel() {
		const editorContext = useSelect( function ( select ) {
			const editor = select( 'core/editor' );

			return {
				postId: editor.getCurrentPostId(),
				postType: editor.getCurrentPostType()
			};
		}, [] );

		const postId = editorContext.postId;
		const postType = editorContext.postType;
		const [ enabled, setEnabled ] = useState( false );
		const [ customText, setCustomText ] = useState( '' );
		const [ loading, setLoading ] = useState( true );
		const [ saving, setSaving ] = useState( false );
		const [ saved, setSaved ] = useState( false );
		const [ error, setError ] = useState( '' );
		const mountedRef = useRef( true );
		const requestSequenceRef = useRef( 0 );
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

				setEnabled( !! ( response && response.enabled ) );
				setCustomText( nextText );
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

		function showSavedState() {
			window.clearTimeout( savedTimerRef.current );
			setSaved( true );
			savedTimerRef.current = window.setTimeout( function () {
				if ( mountedRef.current ) {
					setSaved( false );
				}
			}, 2200 );
		}

		function persist( nextEnabled, nextText ) {
			if ( ! postId ) {
				return Promise.reject( new Error( __( 'The post ID is not available yet.', 'ai-image-disclosure-labels' ) ) );
			}

			const sequence = requestSequenceRef.current + 1;
			requestSequenceRef.current = sequence;
			setSaving( true );
			setSaved( false );
			setError( '' );

			return apiFetch( {
				path: config.restPath + encodeURIComponent( String( postId ) ),
				method: 'POST',
				data: {
					enabled: !! nextEnabled,
					text: nextText || ''
				}
			} ).then( function ( response ) {
				if ( ! mountedRef.current || sequence !== requestSequenceRef.current ) {
					return response;
				}

				const savedText = response && typeof response.text === 'string'
					? response.text
					: '';

				setEnabled( !! ( response && response.enabled ) );
				setCustomText( savedText );
				lastSavedTextRef.current = savedText;
				showSavedState();
				return response;
			} ).catch( function ( requestError ) {
				if ( mountedRef.current && sequence === requestSequenceRef.current ) {
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
			const previous = enabled;
			const next = !! value;
			setEnabled( next );

			persist( next, customText ).catch( function () {
				if ( mountedRef.current ) {
					setEnabled( previous );
				}
			} );
		}

		function changeText( value ) {
			setCustomText( value );
			window.clearTimeout( textTimerRef.current );

			textTimerRef.current = window.setTimeout( function () {
				if ( value !== lastSavedTextRef.current ) {
					persist( enabled, value ).catch( function () {} );
				}
			}, 500 );
		}

		function saveTextOnBlur() {
			window.clearTimeout( textTimerRef.current );

			if ( customText !== lastSavedTextRef.current ) {
				persist( enabled, customText ).catch( function () {} );
			}
		}

		if ( config.allowedPostTypes.indexOf( postType ) === -1 ) {
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
				help: __( 'The choice is saved immediately. Nothing is output unless enabled.', 'ai-image-disclosure-labels' ),
				__nextHasNoMarginBottom: true
			} ),
			! loading && enabled && el( TextControl, {
				label: __( 'Custom text', 'ai-image-disclosure-labels' ),
				value: customText,
				placeholder: config.defaultText,
				onChange: changeText,
				onBlur: saveTextOnBlur,
				help: __( 'Leave empty to use the default text.', 'ai-image-disclosure-labels' ),
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
		registerPlugin( 'gd-ai-image-labels-featured', {
			render: FeaturedImageLabelPanel,
			icon: 'format-image'
		} );
	}
} )( window.wp, window.gdAiImageLabelsEditor );
