/**
 * Techvenza — article reading tools.
 *
 * Reading progress bar, table-of-contents highlighting (and auto-built TOCs), text size and
 * dark reading theme (both remembered per browser), smooth in-page anchor scrolling.
 */
( function () {
	'use strict';
	const tv = window.techvenza;
	if ( ! tv ) {
		return;
	}
	const { $, $$, esc, t } = tv;
	const STEPS = [ 1, 1.0625, 1.125, 1.25, 1.375 ];
	const store = {
		get( k, d ) {
			try {
				const v = window.localStorage.getItem( 'tv:' + k );
				return v === null ? d : JSON.parse( v );
			} catch ( e ) {
				return d;
			}
		},
		set( k, v ) {
			if ( tv.consent && tv.consent() !== 'all' ) {
				return;
			}
			try {
				window.localStorage.setItem( 'tv:' + k, JSON.stringify( v ) );
			} catch ( e ) {}
		},
	};
	const body = () =>
		$( '.tv-article-body' ) ||
		$( '.tv-article-main .prose' ) ||
		$( '.prose' );
	const wrap = () => $( '.tv-article' ) || document.body;

	/* ---------- Progress ---------- */
	const bar = $( '[data-read-progress]' );
	let ticking = false;
	function progress() {
		ticking = false;
		const el = body();
		if ( ! bar || ! el ) {
			return;
		}
		const r = el.getBoundingClientRect();
		const total = r.height - window.innerHeight * 0.6;
		const p = total > 0 ? Math.min( 1, Math.max( 0, -r.top / total ) ) : 0;
		bar.style.transform = 'scaleX(' + p.toFixed( 4 ) + ')';
	}
	window.addEventListener(
		'scroll',
		() => {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( progress );
			}
		},
		{ passive: true }
	);

	/* ---------- Auto TOC (standalone TOC block) ---------- */
	$$( '[data-toc-auto]' ).forEach( ( nav ) => {
		const el = body();
		if ( ! el ) {
			return;
		}
		const hs = $$( 'h2, h3', el ).filter( ( h ) => h.textContent.trim() );
		if ( hs.length < 2 ) {
			return;
		}
		hs.forEach( ( h, i ) => {
			if ( ! h.id ) {
				h.id = 'section-' + ( i + 1 );
			}
		} );
		$( 'ol', nav ).innerHTML = hs
			.map(
				( h ) =>
					'<li><a href="#' +
					esc( h.id ) +
					'"' +
					( h.tagName === 'H3' ? ' class="sub"' : '' ) +
					'>' +
					esc( h.textContent.trim() ) +
					'</a></li>'
			)
			.join( '' );
		nav.hidden = false;
	} );

	/* ---------- TOC highlighting ---------- */
	const tocLinks = $$( '[data-toc] a[href^="#"]' );
	const targets = Array.from(
		new Set(
			tocLinks.map( ( a ) =>
				decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) )
			)
		)
	)
		.map( ( id ) => document.getElementById( id ) )
		.filter( Boolean );
	function setActive( id ) {
		tocLinks.forEach( ( a ) => {
			const on = a.getAttribute( 'href' ) === '#' + id;
			a.classList.toggle( 'active', on );
			if ( on ) {
				a.setAttribute( 'aria-current', 'location' );
			} else {
				a.removeAttribute( 'aria-current' );
			}
		} );
	}
	if ( targets.length && 'IntersectionObserver' in window ) {
		const visible = new Map();
		const io = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( e ) =>
					visible.set( e.target.id, e.isIntersecting )
				);
				let act = null;
				for ( const h of targets ) {
					if ( visible.get( h.id ) ) {
						act = h.id;
						break;
					}
				}
				if ( ! act ) {
					const above = targets.filter(
						( h ) => h.getBoundingClientRect().top < 120
					);
					act = above.length
						? above[ above.length - 1 ].id
						: targets[ 0 ].id;
				}
				setActive( act );
			},
			{ rootMargin: '-80px 0px -60% 0px' }
		);
		targets.forEach( ( h ) => io.observe( h ) );
	}
	document.addEventListener( 'click', ( e ) => {
		const a = e.target.closest(
			'[data-toc] a[href^="#"], .toc-mobile a[href^="#"]'
		);
		if ( ! a ) {
			return;
		}
		const el = document.getElementById(
			decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) )
		);
		if ( ! el ) {
			return;
		}
		e.preventDefault();
		el.scrollIntoView( {
			behavior: tv.reduceMotion && tv.reduceMotion() ? 'auto' : 'smooth',
			block: 'start',
		} );
		if ( ! el.hasAttribute( 'tabindex' ) ) {
			el.setAttribute( 'tabindex', '-1' );
		}
		el.focus( { preventScroll: true } );
		window.history.replaceState( null, '', '#' + el.id );
		const det = a.closest( 'details' );
		if ( det ) {
			det.open = false;
		}
	} );

	/* ---------- Text size ---------- */
	let size = store.get( 'fontSize', 1.125 );
	if ( STEPS.indexOf( size ) < 0 ) {
		size = 1.125;
	}
	function applySize( announce ) {
		const el = body();
		if ( el ) {
			el.style.setProperty( '--fs', size + 'rem' );
		}
		const i = STEPS.indexOf( size );
		$$( '[data-action="fontSize"]' ).forEach( ( b ) => {
			b.disabled =
				( b.dataset.dir === '-1' && i <= 0 ) ||
				( b.dataset.dir === '1' && i >= STEPS.length - 1 );
		} );
		if ( announce ) {
			tv.toast(
				( t.textSize || 'Text size' ) +
					': ' +
					Math.round( ( size / 1.125 ) * 100 ) +
					'%',
				'info'
			);
		}
	}
	tv.actions.fontSize = ( b ) => {
		let i = STEPS.indexOf( size );
		i = Math.max(
			0,
			Math.min( STEPS.length - 1, i + Number( b.dataset.dir ) )
		);
		size = STEPS[ i ];
		store.set( 'fontSize', size );
		applySize( true );
	};

	/* ---------- Dark reading theme ---------- */
	let dark = !! store.get( 'readingDark', false );
	function applyDark() {
		wrap().classList.toggle( 'reading-dark', dark );
		$$( '[data-action="readingTheme"]' ).forEach( ( b ) => {
			b.setAttribute( 'aria-pressed', String( dark ) );
			b.setAttribute(
				'aria-label',
				dark
					? t.darkOn || 'Light reading theme'
					: t.darkOff || 'Dark reading theme'
			);
			b.innerHTML = tv.icon( dark ? 'sun' : 'moon' );
		} );
	}
	tv.actions.readingTheme = () => {
		dark = ! dark;
		store.set( 'readingDark', dark );
		applyDark();
	};

	/* ---------- Bookmark label in the share row ---------- */
	document.addEventListener( 'techvenza:bookmarks', () => {
		$$( '[data-bm-label]' ).forEach( ( s ) => {
			const b = s.closest( '[data-action="bookmark"]' );
			s.textContent =
				b && b.getAttribute( 'aria-pressed' ) === 'true'
					? t.savedLabel || 'Saved'
					: t.saveLabel || 'Save';
		} );
	} );

	applySize( false );
	applyDark();
	progress();
} )();
