/**
 * Techvenza — privacy choices.
 *
 * Stores the visitor's choice in a first-party `tv_consent` cookie (`essential` or `all`),
 * shows the banner until a choice is made, re-opens it from [data-cookie-prefs] buttons and
 * loads analytics only after "Allow all".
 */
( function () {
	'use strict';
	const tv = window.techvenza;
	const cfg = window.techvenzaConsent || { enabled: false, analytics: {} };
	if ( ! tv ) {
		return;
	}
	const { $, toast } = tv;
	const banner = $( '#cookieBanner' );
	let loaded = false;

	function setChoice( value ) {
		const maxAge = 60 * 60 * 24 * 365;
		document.cookie =
			'tv_consent=' +
			value +
			'; max-age=' +
			maxAge +
			'; path=/; SameSite=Lax' +
			( cfg.secure ? '; Secure' : '' );
		if ( value !== 'all' ) {
			// Forget optional preferences stored earlier.
			try {
				Object.keys( window.localStorage )
					.filter( ( k ) => k.indexOf( 'tv:' ) === 0 )
					.forEach( ( k ) => window.localStorage.removeItem( k ) );
			} catch ( e ) {}
		}
		document.dispatchEvent(
			new CustomEvent( 'techvenza:consent', { detail: value } )
		);
	}

	function loadAnalytics() {
		const a = cfg.analytics || {};
		if ( loaded || ! a.provider || ! a.src ) {
			return;
		}
		loaded = true;
		const s = document.createElement( 'script' );
		s.async = true;
		s.src = a.src;
		if ( a.provider === 'plausible' ) {
			s.defer = true;
			s.setAttribute( 'data-domain', a.id );
		} else if ( a.provider === 'ga4' ) {
			window.dataLayer = window.dataLayer || [];
			window.gtag = function () {
				window.dataLayer.push( arguments );
			};
			window.gtag( 'js', new Date() );
			window.gtag( 'config', a.id, { anonymize_ip: true } );
		}
		document.head.appendChild( s );
	}

	function show( focus ) {
		if ( ! banner ) {
			return;
		}
		banner.hidden = false;
		const first = $( 'button', banner );
		if ( focus && first ) {
			first.focus();
		}
	}

	document.addEventListener( 'click', ( e ) => {
		const choice = e.target.closest( '[data-cookie]' );
		if ( choice ) {
			setChoice( choice.dataset.cookie );
			if ( banner ) {
				banner.hidden = true;
			}
			toast(
				( cfg.i18n || {} )[
					choice.dataset.cookie === 'all' ? 'all' : 'essential'
				],
				'info'
			);
			if ( choice.dataset.cookie === 'all' ) {
				loadAnalytics();
			}
			return;
		}
		if ( e.target.closest( '[data-cookie-prefs]' ) ) {
			show( true );
		}
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && banner && ! banner.hidden && tv.consent() ) {
			banner.hidden = true;
		}
	} );

	const current = tv.consent();
	if ( current === 'all' ) {
		loadAnalytics();
	} else if ( ! current && cfg.enabled ) {
		show( false );
	}
} )();
