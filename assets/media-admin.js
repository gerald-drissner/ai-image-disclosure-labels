( function () {
	'use strict';

	const statusClasses = [
		'gdaiidl-status-unclassified',
		'gdaiidl-status-no-ai',
		'gdaiidl-status-generated',
		'gdaiidl-status-modified',
		'gdaiidl-status-edited',
		'gdaiidl-status-enhanced'
	];

	function syncStatusBadge( select ) {
		if ( ! select || ! select.matches( '.gdaiidl-media-source-select' ) ) {
			return;
		}

		const control = select.closest( '[data-gdaiidl-media-source-control]' );
		const badge = control ? control.querySelector( '[data-gdaiidl-status-badge]' ) : null;
		const option = select.options[ select.selectedIndex ];

		if ( ! badge || ! option ) {
			return;
		}

		const status = option.dataset.gdaiidlStatus || 'unclassified';
		const text = option.dataset.gdaiidlBadge || option.textContent || '';

		statusClasses.forEach( function ( className ) {
			badge.classList.remove( className );
		} );

		badge.classList.add( 'gdaiidl-status-' + status );
		badge.textContent = text;
	}

	document.addEventListener( 'change', function ( event ) {
		const target = event.target;

		if ( target && target.matches && target.matches( '.gdaiidl-media-source-select' ) ) {
			syncStatusBadge( target );
		}
	} );
} )();
