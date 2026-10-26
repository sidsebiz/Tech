/**
 * Category / series / profile screens: colour pickers with WCAG contrast hint, media picker.
 */
( function ( $, cfg ) {
	cfg = cfg || {};

	function lum( hex ) {
		const h = ( hex || '' ).replace( '#', '' );
		if ( h.length !== 6 ) {
			return null;
		}
		const c = [ 0, 2, 4 ]
			.map( ( i ) => parseInt( h.substr( i, 2 ), 16 ) / 255 )
			.map( ( v ) =>
				v <= 0.03928
					? v / 12.92
					: Math.pow( ( v + 0.055 ) / 1.055, 2.4 )
			);
		return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
	}

	function checkContrast() {
		const out = $( '.tv-contrast' );
		if ( ! out.length ) {
			return;
		}
		const ink =
			$( '[name="tv_ink"]' ).val() || $( '[name="tv_color"]' ).val();
		const soft = $( '[name="tv_soft"]' ).val() || '#ffffff';
		const a = lum( ink );
		const b = lum( soft );
		if ( a === null || b === null ) {
			out.text( '' );
			return;
		}
		const ratio = ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
		const text =
			( ratio >= 4.5 ? cfg.okContrast : cfg.lowContrast ) || '%s';
		out.text( text.replace( '%s', ratio.toFixed( 2 ) ) ).css(
			'color',
			ratio >= 4.5 ? '#047857' : '#b32d2e'
		);
	}

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.tv-color' ).wpColorPicker( {
				change: () => setTimeout( checkContrast, 10 ),
				clear: () => setTimeout( checkContrast, 10 ),
			} );
		}
		checkContrast();

		$( document ).on( 'click', '.tv-media-pick', function ( e ) {
			e.preventDefault();
			const wrap = $( this ).closest( '.tv-media-field' );
			const frame = wp.media( {
				title: cfg.choose || 'Choose image',
				multiple: false,
				library: { type: 'image' },
			} );
			frame.on( 'select', () => {
				const att = frame.state().get( 'selection' ).first().toJSON();
				wrap.find( 'input[type=hidden]' ).val( att.id );
				const url =
					( att.sizes &&
						att.sizes.thumbnail &&
						att.sizes.thumbnail.url ) ||
					att.url;
				wrap.find( 'img' ).attr( 'src', url ).show();
			} );
			frame.open();
		} );
		$( document ).on( 'click', '.tv-media-clear', function ( e ) {
			e.preventDefault();
			const wrap = $( this ).closest( '.tv-media-field' );
			wrap.find( 'input[type=hidden]' ).val( '' );
			wrap.find( 'img' ).attr( 'src', '' ).hide();
		} );
	} );
} )( window.jQuery, window.techvenzaTerm );
