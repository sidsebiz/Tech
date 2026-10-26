/**
 * Techvenza — in-place filtering, sorting and pagination for listing blocks.
 *
 * Progressive enhancement: every filter is a normal link or GET form with a shareable,
 * crawlable URL. With JavaScript, the matching block is re-rendered through
 * /techvenza/v1/fragment/{block} and swapped in place, and the URL is updated with pushState.
 */
( function () {
	'use strict';
	const tv = window.techvenza;
	if ( ! tv ) {
		return;
	}
	let seq = 0;

	function region( el ) {
		return el && el.closest( '[data-live]' );
	}

	async function load( box, url, opts ) {
		opts = opts || {};
		const block = box.dataset.live;
		const target = new URL( url, window.location.href );
		const params = new URLSearchParams( target.search );
		params.set( 'attrs', box.dataset.attrs || '{}' );
		params.set( '_base', target.origin + target.pathname );
		if ( box.dataset.term ) {
			params.set( '_term', box.dataset.term );
		}
		const my = ++seq;
		box.setAttribute( 'aria-busy', 'true' );
		box.classList.add( 'is-loading' );
		try {
			const res = await fetch(
				tv.cfg.rest + 'fragment/' + block + '?' + params.toString(),
				{ credentials: 'same-origin' }
			);
			const data = await res.json();
			if ( my !== seq || ! data || typeof data.html !== 'string' ) {
				return;
			}
			const active = document.activeElement;
			const activeId = active && box.contains( active ) ? active.id : '';
			const caret =
				activeId && typeof active.selectionStart === 'number'
					? active.selectionStart
					: null;
			const tmp = document.createElement( 'div' );
			tmp.innerHTML = data.html;
			const fresh =
				tmp.querySelector( '[data-live="' + block + '"]' ) ||
				tmp.firstElementChild;
			if ( ! fresh ) {
				return;
			}
			box.replaceWith( fresh );
			if ( ! opts.pop ) {
				window.history.pushState(
					{ tvLive: block },
					'',
					target.pathname + target.search + target.hash
				);
			}
			tv.initReveal( fresh );
			tv.applyBookmarks( fresh );
			if ( activeId ) {
				const el = document.getElementById( activeId );
				if ( el ) {
					el.focus( { preventScroll: true } );
					if ( caret !== null && el.setSelectionRange ) {
						el.setSelectionRange( caret, caret );
					}
				}
			} else if ( opts.scroll ) {
				const top = fresh.querySelector( '[data-live-top]' ) || fresh;
				top.scrollIntoView( {
					behavior: window.matchMedia(
						'(prefers-reduced-motion: reduce)'
					).matches
						? 'auto'
						: 'smooth',
					block: 'start',
				} );
			}
			const status = fresh.querySelector( '[data-live-status]' );
			if ( status ) {
				status.setAttribute( 'role', 'status' );
			}
		} catch ( e ) {
			window.location.href = url;
		} finally {
			box.removeAttribute( 'aria-busy' );
			box.classList.remove( 'is-loading' );
		}
	}

	function formUrl( form ) {
		const url = new URL(
			form.getAttribute( 'action' ) || window.location.href,
			window.location.href
		);
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( v, k ) => {
			if ( String( v ).trim() !== '' ) {
				params.set( k, v );
			}
		} );
		url.search = params.toString();
		return url.toString();
	}

	document.addEventListener( 'click', ( e ) => {
		const a = e.target.closest( 'a[href]' );
		if ( ! a || e.metaKey || e.ctrlKey || e.shiftKey || e.button ) {
			return;
		}
		const box = region( a );
		if (
			! box ||
			! (
				a.hasAttribute( 'data-live-link' ) || a.closest( '.pagination' )
			)
		) {
			return;
		}
		e.preventDefault();
		load( box, a.href, {
			scroll:
				!! a.closest( '.pagination' ) ||
				a.hasAttribute( 'data-live-more' ),
		} );
	} );

	document.addEventListener( 'submit', ( e ) => {
		const form = e.target;
		const box = region( form );
		if ( ! box || ( form.method || 'get' ).toLowerCase() !== 'get' ) {
			return;
		}
		e.preventDefault();
		load( box, formUrl( form ) );
	} );

	let timer = null;
	document.addEventListener( 'input', ( e ) => {
		const input = e.target.closest( '[data-live-input]' );
		if ( ! input ) {
			return;
		}
		clearTimeout( timer );
		timer = setTimeout( () => {
			const form = input.form;
			const box = region( input );
			if ( form && box ) {
				load( box, formUrl( form ) );
			}
		}, 260 );
	} );

	document.addEventListener( 'change', ( e ) => {
		const el = e.target.closest( '[data-live-change]' );
		if ( el && el.form && region( el ) ) {
			load( region( el ), formUrl( el.form ) );
		}
	} );

	window.addEventListener( 'popstate', () => {
		const box = document.querySelector( '[data-live]' );
		if ( box ) {
			load( box, window.location.href, { pop: true } );
		}
	} );
} )();
