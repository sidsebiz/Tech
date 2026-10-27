/**
 * Techvenza — shared front-end behaviour (no dependencies, no jQuery).
 *
 * Header (sticky state, dropdowns, drawer, search overlay with live suggestions),
 * account hydration (cache-safe), bookmarks, toasts, modal/confirm dialogs with focus
 * trapping, share & copy, reveal-on-scroll, back-to-top, password helpers, counters,
 * view beacon. Exposes window.techvenza for the other view scripts.
 */
( function () {
	'use strict';
	const cfg = window.techvenzaFront || {};
	const t = cfg.i18n || {};
	const $ = ( s, r ) => ( r || document ).querySelector( s );
	const $$ = ( s, r ) =>
		Array.from( ( r || document ).querySelectorAll( s ) );
	const reduceMotion = () =>
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const esc = ( s ) =>
		String( s === null || s === undefined ? '' : s ).replace(
			/[&<>"']/g,
			( c ) =>
				( {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;',
				} )[ c ]
		);
	const icon = ( name ) =>
		'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#tv-i-' +
		name +
		'"></use></svg>';

	/* ---------------- Session (personalisation via AJAX, never in cached HTML) ---------------- */
	const session = {
		user: null,
		nonce: '',
		bookmarks: [],
		topics: [],
		ready: null,
	};
	session.ready = fetch( cfg.ajax + '?action=techvenza_session', {
		credentials: 'same-origin',
	} )
		.then( ( r ) => ( r.ok ? r.json() : null ) )
		.then( ( j ) => {
			if ( j && j.success ) {
				Object.assign( session, j.data );
			}
			hydrateAccount();
			applyBookmarks();
			document.dispatchEvent(
				new CustomEvent( 'techvenza:session', { detail: session } )
			);
			return session;
		} )
		.catch( () => session );

	function api( path, opts ) {
		opts = opts || {};
		const headers = Object.assign(
			{ 'Content-Type': 'application/json' },
			opts.headers || {}
		);
		if ( session.nonce ) {
			headers[ 'X-WP-Nonce' ] = session.nonce;
		}
		return fetch( cfg.rest + path.replace( /^\//, '' ), {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers,
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} ).then( async ( r ) => {
			const data = await r.json().catch( () => ( {} ) );
			if ( ! r.ok ) {
				const err = new Error(
					( data && data.message ) || t.error || 'Error'
				);
				err.data = data;
				err.status = r.status;
				throw err;
			}
			return data;
		} );
	}

	function hydrateAccount() {
		const u = session.user;
		$$( '[data-account]' ).forEach( ( box ) => {
			if ( ! u ) {
				return;
			}
			const links = [
				'<a class="dropdown-item" role="menuitem" href="' +
					esc( u.links.dashboard ) +
					'">' +
					icon( 'home' ) +
					' ' +
					esc( t.dashboard ) +
					'</a>',
			];
			if ( u.links.portal ) {
				links.push(
					'<a class="dropdown-item" role="menuitem" href="' +
						esc( u.links.portal ) +
						'">' +
						icon( 'pen' ) +
						' ' +
						esc( t.portal ) +
						'</a>'
				);
			}
			if ( u.links.cms ) {
				links.push(
					'<a class="dropdown-item" role="menuitem" href="' +
						esc( u.links.cms ) +
						'">' +
						icon( 'grid' ) +
						' ' +
						esc( t.cms ) +
						'</a>'
				);
			}
			links.push(
				'<a class="dropdown-item" role="menuitem" href="' +
					esc( u.links.logout ) +
					'">' +
					icon( 'logout' ) +
					' ' +
					esc( t.logOut ) +
					'</a>'
			);
			const avatar = u.photo
				? '<img class="avatar sm" src="' +
				  esc( u.photo ) +
				  '" alt="" width="28" height="28" style="object-fit:cover">'
				: '<span class="avatar sm" style="--a1:#111827;--a2:#7C3AED" aria-hidden="true">' +
				  esc( u.initials ) +
				  '</span>';
			box.innerHTML =
				'<div class="dropdown hide-md"><button class="user-btn" type="button" data-dd aria-expanded="false" aria-controls="dd-user" aria-label="' +
				esc( t.accountMenu ) +
				': ' +
				esc( u.name ) +
				'">' +
				avatar +
				'<span style="font-size:.85rem;font-weight:600;color:var(--ink)">' +
				esc( u.first ) +
				'</span>' +
				icon( 'chevDown' ) +
				'</button>' +
				'<div class="dropdown-menu right" id="dd-user" role="menu"><div style="padding:10px 12px"><strong style="color:var(--ink);font-size:.9rem">' +
				esc( u.name ) +
				'</strong><div class="muted" style="font-size:var(--t-meta)">' +
				esc( u.roleLabel ) +
				'</div></div><hr class="divider" style="margin:4px 0">' +
				links.join( '' ) +
				'</div></div>';
		} );
		$$( '[data-drawer-account] [data-login-link]' ).forEach( ( a ) => {
			if ( u ) {
				a.textContent = t.logOut + ' (' + u.first + ')';
				a.href = u.links.logout;
				const dash = document.createElement( 'a' );
				dash.className = 'btn btn-secondary btn-block';
				dash.href = u.links.dashboard;
				dash.textContent = t.dashboard;
				a.parentNode.insertBefore( dash, a );
			}
		} );
	}

	/* ---------------- Toasts ---------------- */
	function toast( msg, type ) {
		type = type || 'ok';
		const box = $( '#toasts' );
		if ( ! box ) {
			return;
		}
		const el = document.createElement( 'div' );
		el.className = 'toast ' + type;
		el.setAttribute( 'role', type === 'err' ? 'alert' : 'status' );
		el.innerHTML =
			icon(
				type === 'err'
					? 'alert'
					: type === 'info'
					? 'info'
					: 'checkCircle'
			) + '<span></span>';
		el.querySelector( 'span' ).textContent = msg;
		box.appendChild( el );
		setTimeout( () => {
			el.classList.add( 'leaving' );
			setTimeout( () => el.remove(), 300 );
		}, 3800 );
	}

	/* ---------------- Modal + confirm (focus-trapped) ---------------- */
	let modalState = null;
	function openModal( o ) {
		closeModal( true );
		const root = $( '#modalRoot' );
		if ( ! root ) {
			return null;
		}
		root.innerHTML =
			'<div class="modal ' +
			( o.wide ? 'wide' : '' ) +
			'" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><div class="modal-head"><h2 id="modalTitle"></h2><button class="icon-btn" type="button" data-close-modal aria-label="' +
			esc( t.close ) +
			'">' +
			icon( 'x' ) +
			'</button></div><div class="modal-body">' +
			( o.body || '' ) +
			'</div>' +
			( o.foot ? '<div class="modal-foot">' + o.foot + '</div>' : '' ) +
			'</div>';
		root.querySelector( '#modalTitle' ).textContent = o.title || '';
		root.classList.add( 'open' );
		root.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
		modalState = { prev: document.activeElement, onClose: o.onClose };
		const f = root.querySelector(
			'.modal-body input, .modal-body select, .modal-body textarea, .modal-foot .btn-primary, .modal-foot .btn, [data-close-modal]'
		);
		setTimeout( () => f && f.focus(), 30 );
		return root.querySelector( '.modal' );
	}
	function closeModal( silent ) {
		const root = $( '#modalRoot' );
		if ( ! root || ! root.classList.contains( 'open' ) ) {
			return;
		}
		root.classList.remove( 'open' );
		root.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		const s = modalState;
		modalState = null;
		setTimeout( () => {
			if ( ! root.classList.contains( 'open' ) ) {
				root.innerHTML = '';
			}
		}, 220 );
		if ( s ) {
			if ( s.onClose && ! silent ) {
				s.onClose();
			}
			if ( s.prev && s.prev.focus ) {
				s.prev.focus();
			}
		}
	}
	function confirmDialog( o ) {
		return new Promise( ( resolve ) => {
			let done = false;
			openModal( {
				title: o.title,
				body: '<p>' + esc( o.text ) + '</p>' + ( o.extra || '' ),
				foot:
					'<button class="btn btn-secondary" type="button" data-close-modal>' +
					esc( t.cancel ) +
					'</button><button class="btn btn-' +
					( o.tone || 'primary' ) +
					'" type="button" data-confirm-yes>' +
					esc( o.confirm || 'OK' ) +
					'</button>',
				onClose: () => ! done && resolve( false ),
			} );
			$( '#modalRoot [data-confirm-yes]' ).addEventListener(
				'click',
				() => {
					done = true;
					const vals = {};
					$$( '#modalRoot .modal-body [name]' ).forEach(
						( e ) => ( vals[ e.name ] = e.value )
					);
					closeModal( true );
					resolve( vals );
				}
			);
		} );
	}
	function trapFocus( container, e ) {
		const f = $$(
			'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select,textarea,[tabindex]:not([tabindex="-1"])',
			container
		).filter(
			( el ) => el.offsetParent !== null || el === document.activeElement
		);
		if ( ! f.length ) {
			return;
		}
		const first = f[ 0 ];
		const last = f[ f.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	/* ---------------- Dropdowns ---------------- */
	function closeDropdowns( except ) {
		$$( '[data-dd][aria-expanded="true"]' ).forEach( ( b ) => {
			if ( b === except ) {
				return;
			}
			b.setAttribute( 'aria-expanded', 'false' );
			const m = document.getElementById(
				b.getAttribute( 'aria-controls' )
			);
			if ( m ) {
				m.classList.remove( 'open' );
			}
		} );
	}
	function toggleDropdown( btn, focusFirst ) {
		const open = btn.getAttribute( 'aria-expanded' ) !== 'true';
		closeDropdowns( btn );
		btn.setAttribute( 'aria-expanded', String( open ) );
		const m = document.getElementById(
			btn.getAttribute( 'aria-controls' )
		);
		if ( ! m ) {
			return;
		}
		m.classList.toggle( 'open', open );
		if ( open && focusFirst ) {
			const f = m.querySelector( 'a,button' );
			if ( f ) {
				f.focus();
			}
		}
	}

	/* ---------------- Drawer ---------------- */
	function openDrawer() {
		const d = $( '#drawer' );
		if ( ! d ) {
			return;
		}
		d.classList.add( 'open' );
		$$( '.drawer-backdrop' ).forEach( ( b ) => b.classList.add( 'open' ) );
		$$( '[data-open-drawer]' ).forEach( ( b ) =>
			b.setAttribute( 'aria-expanded', 'true' )
		);
		document.body.style.overflow = 'hidden';
		setTimeout( () => {
			const c = $( '#drawer [data-close-drawer]' );
			if ( c ) {
				c.focus();
			}
		}, 50 );
	}
	function closeDrawer() {
		const d = $( '#drawer' );
		if ( ! d || ! d.classList.contains( 'open' ) ) {
			return;
		}
		d.classList.remove( 'open' );
		$$( '.drawer-backdrop' ).forEach( ( b ) =>
			b.classList.remove( 'open' )
		);
		$$( '[data-open-drawer]' ).forEach( ( b ) =>
			b.setAttribute( 'aria-expanded', 'false' )
		);
		document.body.style.overflow = '';
	}

	/* ---------------- Search overlay + suggestions ---------------- */
	let searchPrev = null;
	let suggestTimer = null;
	let suggestSeq = 0;
	function openSearch() {
		const o = $( '#searchOverlay' );
		if ( ! o ) {
			window.location.href = cfg.searchUrl + '?s=';
			return;
		}
		searchPrev = document.activeElement;
		o.classList.add( 'open' );
		document.body.style.overflow = 'hidden';
		const i = $( '#overlaySearch' );
		i.value = '';
		renderSuggest( '' );
		setTimeout( () => i.focus(), 60 );
	}
	function closeSearch() {
		const o = $( '#searchOverlay' );
		if ( ! o || ! o.classList.contains( 'open' ) ) {
			return;
		}
		o.classList.remove( 'open' );
		document.body.style.overflow = '';
		if ( searchPrev && searchPrev.focus ) {
			searchPrev.focus();
		}
	}
	function renderSuggest( q ) {
		const box = $( '#searchSuggest' );
		if ( ! box ) {
			return;
		}
		const seq = ++suggestSeq;
		api( 'search/suggest?q=' + encodeURIComponent( q.trim() ) )
			.then( ( d ) => {
				if ( seq !== suggestSeq ) {
					return;
				}
				if ( ! q.trim() ) {
					box.innerHTML =
						'<p class="eyebrow mb-4" style="margin-top:8px">' +
						esc( t.popular ) +
						'</p><div class="chip-row">' +
						( d.topics || [] )
							.map(
								( x ) =>
									'<a class="filter-chip" href="' +
									esc( x.url ) +
									'">' +
									esc( x.name ) +
									'</a>'
							)
							.join( '' ) +
						'</div><p class="eyebrow mt-6 mb-4">' +
						esc( t.trendingNow ) +
						'</p>' +
						( d.articles || [] )
							.map(
								( a ) =>
									'<a href="' +
									esc( a.url ) +
									'" role="option"><span class="s-type">' +
									esc( t.article ) +
									'</span><span>' +
									esc( a.title ) +
									'</span></a>'
							)
							.join( '' );
					return;
				}
				const rows = []
					.concat(
						( d.articles || [] ).map(
							( a ) =>
								'<a href="' +
								esc( a.url ) +
								'" role="option"><span class="s-type">' +
								esc( t.article ) +
								'</span><span>' +
								a.html +
								'</span></a>'
						)
					)
					.concat(
						( d.authors || [] ).map(
							( a ) =>
								'<a href="' +
								esc( a.url ) +
								'" role="option"><span class="s-type">' +
								esc( t.author ) +
								'</span><span>' +
								a.html +
								' <span class="muted">— ' +
								esc( a.title ) +
								'</span></span></a>'
						)
					)
					.concat(
						( d.topics || [] ).map(
							( a ) =>
								'<a href="' +
								esc( a.url ) +
								'" role="option"><span class="s-type">' +
								esc( t.topic ) +
								'</span><span>' +
								a.html +
								'</span></a>'
						)
					);
				box.innerHTML = rows.length
					? rows.join( '' ) +
					  '<a href="' +
					  esc( d.all ) +
					  '" role="option"><span class="s-type">' +
					  esc( t.all ) +
					  '</span><span>' +
					  esc( t.seeAll ) +
					  ' “' +
					  esc( q ) +
					  '” ' +
					  icon( 'arrowRight' ) +
					  '</span></a>'
					: '<p class="muted" style="padding:12px">' +
					  esc( t.noQuick ) +
					  '</p>';
			} )
			.catch( () => {} );
	}

	/* ---------------- Bookmarks ---------------- */
	function applyBookmarks( root ) {
		const set = new Set( ( session.bookmarks || [] ).map( Number ) );
		$$( '[data-action="bookmark"]', root ).forEach( ( b ) => {
			const on = set.has( Number( b.dataset.id ) );
			b.setAttribute( 'aria-pressed', String( on ) );
			const lbl = b.querySelector( '[data-bm-label]' );
			if ( lbl ) {
				lbl.textContent = on ? lbl.dataset.on : lbl.dataset.off;
			}
		} );
	}
	function signInPrompt() {
		const next = encodeURIComponent( window.location.href );
		openModal( {
			title: t.signInSave,
			body: '<p>' + esc( t.signInText ) + '</p>',
			foot:
				'<a class="btn btn-secondary" href="' +
				esc( cfg.loginUrl ) +
				'?action=register&redirect_to=' +
				next +
				'">' +
				esc( t.register ) +
				'</a><a class="btn btn-primary" href="' +
				esc( cfg.loginUrl ) +
				'?redirect_to=' +
				next +
				'">' +
				esc( t.signIn ) +
				'</a>',
		} );
	}
	async function toggleBookmark( btn ) {
		await session.ready;
		if ( ! session.user ) {
			signInPrompt();
			return;
		}
		const id = Number( btn.dataset.id );
		const on = btn.getAttribute( 'aria-pressed' ) !== 'true';
		try {
			const d = await api( 'me/bookmarks/' + id, {
				method: on ? 'PUT' : 'DELETE',
			} );
			session.bookmarks = d.bookmarks || [];
			applyBookmarks();
			toast( on ? t.saved : t.removed, on ? 'ok' : 'info' );
			document.dispatchEvent(
				new CustomEvent( 'techvenza:bookmarks', {
					detail: session.bookmarks,
				} )
			);
		} catch ( e ) {
			toast( e.message, 'err' );
		}
	}

	/* ---------------- Share / copy ---------------- */
	function copyText( text ) {
		const done = () => toast( t.copied );
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard
				.writeText( text )
				.then( done, () => fallbackCopy( text, done ) );
		}
		fallbackCopy( text, done );
	}
	function fallbackCopy( text, done ) {
		const ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {
			toast( t.copyFailed, 'err' );
		}
		ta.remove();
	}
	function share( btn ) {
		const url = btn.dataset.url || window.location.href;
		const title = btn.dataset.title || document.title;
		if ( navigator.share ) {
			navigator.share( { title, url } ).catch( () => {} );
			return;
		}
		const u = encodeURIComponent( url );
		openModal( {
			title: t.shareTitle,
			body:
				'<p class="muted mb-4" style="font-size:var(--t-small)">' +
				esc( t.shareNote ) +
				'</p><div class="grid" style="gap:8px">' +
				'<a class="btn btn-secondary" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=' +
				u +
				'">' +
				icon( 'linkedin' ) +
				' LinkedIn</a>' +
				'<a class="btn btn-secondary" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=' +
				u +
				'&text=' +
				encodeURIComponent( title ) +
				'">' +
				icon( 'xlogo' ) +
				' X</a>' +
				'<a class="btn btn-secondary" href="mailto:?subject=' +
				encodeURIComponent( title ) +
				'&body=' +
				u +
				'">' +
				icon( 'mail' ) +
				' Email</a>' +
				'<button class="btn btn-primary" type="button" data-action="copyLink" data-url="' +
				esc( url ) +
				'">' +
				icon( 'copy' ) +
				' ' +
				esc( t.copyLink ) +
				'</button></div>',
		} );
	}

	/* ---------------- Scroll-linked UI ---------------- */
	let ticking = false;
	function onScroll() {
		if ( ticking ) {
			return;
		}
		ticking = true;
		requestAnimationFrame( () => {
			ticking = false;
			const y = window.scrollY;
			$$( '.site-header' ).forEach( ( h ) =>
				h.classList.toggle( 'scrolled', y > 8 )
			);
			const top = $( '[data-to-top]' );
			if ( top ) {
				top.classList.toggle( 'show', y > 900 );
			}
			document.dispatchEvent(
				new CustomEvent( 'techvenza:scroll', { detail: y } )
			);
		} );
	}

	/* ---------------- Reveal ---------------- */
	let revealIO = null;
	function initReveal( root ) {
		const els = $$( '.reveal:not(.in)', root );
		if ( ! ( 'IntersectionObserver' in window ) || reduceMotion() ) {
			els.forEach( ( e ) => e.classList.add( 'in' ) );
			return;
		}
		if ( ! revealIO ) {
			revealIO = new IntersectionObserver(
				( es ) =>
					es.forEach( ( en ) => {
						if ( en.isIntersecting ) {
							en.target.classList.add( 'in' );
							revealIO.unobserve( en.target );
						}
					} ),
				{ rootMargin: '0px 0px -6% 0px', threshold: 0.05 }
			);
		}
		els.forEach( ( e ) => {
			if ( e.getBoundingClientRect().top < window.innerHeight ) {
				e.classList.add( 'in' );
			} else {
				revealIO.observe( e );
			}
		} );
	}

	/* ---------------- Password + counters ---------------- */
	function pwScore( v ) {
		let s = 0;
		if ( v.length >= 8 ) {
			s++;
		}
		if ( v.length >= 12 ) {
			s++;
		}
		if ( /[A-Z]/.test( v ) && /[a-z]/.test( v ) ) {
			s++;
		}
		if ( /\d/.test( v ) && /[^A-Za-z0-9]/.test( v ) ) {
			s++;
		}
		return Math.min( 4, v.length ? Math.max( 1, s ) : 0 );
	}

	/* ---------------- View beacon (bot-filtered and deduped server-side; once per session) ---------------- */
	function trackView() {
		if ( ! cfg.trackView || ! cfg.postId ) {
			return;
		}
		const key = 'tv_viewed_' + cfg.postId;
		try {
			if ( sessionStorage.getItem( key ) ) {
				return;
			}
			sessionStorage.setItem( key, '1' );
		} catch ( e ) {}
		const url = cfg.rest + 'views/' + cfg.postId;
		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( url );
		} else {
			fetch( url, { method: 'POST', keepalive: true } ).catch( () => {} );
		}
	}

	/* ---------------- Event delegation ---------------- */
	/* ---------------- Follow topic ---------------- */
	function applyTopics( root ) {
		$$( '[data-action="followTopic"]', root ).forEach( ( b ) => {
			const on = ( session.topics || [] ).includes( b.dataset.slug );
			b.setAttribute( 'aria-pressed', String( on ) );
			b.className = 'btn ' + ( on ? 'btn-secondary' : 'btn-primary' );
			b.innerHTML =
				icon( on ? 'check' : 'plus' ) +
				' <span>' +
				esc(
					on
						? b.dataset.labelOn || t.following
						: b.dataset.labelOff || t.follow
				) +
				'</span>';
		} );
	}
	async function followTopic( btn ) {
		await session.ready;
		if ( ! session.user ) {
			signInPrompt();
			return;
		}
		const slug = btn.dataset.slug;
		const on = btn.getAttribute( 'aria-pressed' ) !== 'true';
		try {
			const d = await api( 'me/topics/' + slug, {
				method: on ? 'PUT' : 'DELETE',
			} );
			session.topics =
				d.topics ||
				( on
					? [ ...( session.topics || [] ), slug ]
					: ( session.topics || [] ).filter( ( x ) => x !== slug ) );
			applyTopics();
			toast(
				on ? t.followed || t.following : t.unfollowed || t.follow,
				on ? 'ok' : 'info'
			);
		} catch ( e ) {
			toast( e.message, 'err' );
		}
	}
	document.addEventListener( 'techvenza:session', () => applyTopics() );

	const actions = {
		bookmark: toggleBookmark,
		followTopic,
		share,
		copyLink: ( b ) => copyText( b.dataset.url || window.location.href ),
		togglePw: ( b ) => {
			const i = document.getElementById( b.dataset.target );
			const show = i.type === 'password';
			i.type = show ? 'text' : 'password';
			b.setAttribute( 'aria-pressed', String( show ) );
			b.setAttribute( 'aria-label', show ? t.hidePw : t.showPw );
			b.innerHTML = icon( show ? 'eyeOff' : 'eye' );
		},
	};

	document.addEventListener( 'click', ( e ) => {
		const tgt = e.target;
		const dd = tgt.closest( '[data-dd]' );
		if ( dd ) {
			e.preventDefault();
			toggleDropdown( dd );
			return;
		}
		if ( ! tgt.closest( '.dropdown-menu' ) ) {
			closeDropdowns();
		}
		if ( tgt.closest( '[data-open-search]' ) ) {
			e.preventDefault();
			openSearch();
			return;
		}
		if (
			tgt.closest( '[data-close-search]' ) ||
			tgt === $( '#searchOverlay' )
		) {
			closeSearch();
			return;
		}
		if ( tgt.closest( '[data-open-drawer]' ) ) {
			openDrawer();
			return;
		}
		if ( tgt.closest( '[data-close-drawer]' ) ) {
			closeDrawer();
			return;
		}
		if ( tgt === $( '#modalRoot' ) ) {
			closeModal();
			return;
		}
		const cm = tgt.closest( '[data-close-modal]' );
		if ( cm ) {
			closeModal();
			if ( cm.tagName !== 'A' ) {
				return;
			}
		}
		if ( tgt.closest( '[data-to-top]' ) ) {
			window.scrollTo( {
				top: 0,
				behavior: reduceMotion() ? 'auto' : 'smooth',
			} );
			const main = $( 'main' );
			if ( main ) {
				main.focus( { preventScroll: true } );
			}
			return;
		}
		const a = tgt.closest( '[data-action]' );
		if ( a && actions[ a.dataset.action ] ) {
			if ( a.tagName === 'A' && ! a.hasAttribute( 'data-allow-nav' ) ) {
				e.preventDefault();
			}
			actions[ a.dataset.action ]( a, e );
			return;
		}
		const link = tgt.closest( 'a[href]' );
		if ( link && $( '#drawer' ) && $( '#drawer' ).contains( link ) ) {
			closeDrawer();
		}
	} );

	document.addEventListener( 'input', ( e ) => {
		const tg = e.target;
		if ( tg.dataset && tg.dataset.max ) {
			const c = document.querySelector(
				'[data-count-for="' + tg.id + '"]'
			);
			if ( c ) {
				const n = ( tg.value || '' ).length;
				c.textContent = n + ' / ' + tg.dataset.max;
				c.classList.toggle( 'over', n > Number( tg.dataset.max ) );
			}
		}
		if ( tg.hasAttribute && tg.hasAttribute( 'data-strength' ) ) {
			const s = pwScore( tg.value );
			const f = tg.closest( '.field' );
			const meter = f && f.querySelector( '.strength' );
			if ( meter ) {
				meter.dataset.score = s;
				const hint = f.querySelector( '[id$="-sh"]' );
				if ( hint && t.pwHints ) {
					hint.textContent = t.pwHints[ s ];
				}
			}
		}
		const inv = tg.closest && tg.closest( '.field.invalid' );
		if ( inv && ( tg.value || '' ).length ) {
			inv.classList.remove( 'invalid' );
			tg.removeAttribute( 'aria-invalid' );
		}
		if ( tg.id === 'overlaySearch' ) {
			clearTimeout( suggestTimer );
			suggestTimer = setTimeout( () => renderSuggest( tg.value ), 120 );
		}
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' ) {
			if ( $( '#modalRoot.open' ) ) {
				closeModal();
				return;
			}
			if ( $( '#searchOverlay.open' ) ) {
				closeSearch();
				return;
			}
			if ( $( '#drawer.open' ) ) {
				closeDrawer();
				const b = $( '[data-open-drawer]' );
				if ( b ) {
					b.focus();
				}
				return;
			}
			const open = $( '[data-dd][aria-expanded="true"]' );
			if ( open ) {
				closeDropdowns();
				open.focus();
				return;
			}
		}
		if ( e.key === 'Tab' ) {
			if ( $( '#modalRoot.open' ) ) {
				trapFocus( $( '#modalRoot .modal' ), e );
			} else if ( $( '#searchOverlay.open' ) ) {
				trapFocus( $( '#searchOverlay' ), e );
			} else if ( $( '#drawer.open' ) ) {
				trapFocus( $( '#drawer' ), e );
			}
		}
		const ddBtn = e.target.closest && e.target.closest( '[data-dd]' );
		if ( ddBtn && e.key === 'ArrowDown' ) {
			e.preventDefault();
			if ( ddBtn.getAttribute( 'aria-expanded' ) !== 'true' ) {
				toggleDropdown( ddBtn, true );
			} else {
				const f = document
					.getElementById( ddBtn.getAttribute( 'aria-controls' ) )
					.querySelector( 'a,button' );
				if ( f ) {
					f.focus();
				}
			}
		}
		const menu =
			e.target.closest && e.target.closest( '.dropdown-menu.open' );
		if ( menu && ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) ) {
			e.preventDefault();
			const items = $$( 'a,button', menu );
			const i = items.indexOf( document.activeElement );
			items[
				e.key === 'ArrowDown'
					? ( i + 1 ) % items.length
					: ( i - 1 + items.length ) % items.length
			].focus();
		}
		if ( e.target.id === 'overlaySearch' && e.key === 'ArrowDown' ) {
			const f = $( '#searchSuggest a' );
			if ( f ) {
				e.preventDefault();
				f.focus();
			}
		}
		if (
			e.target.closest &&
			e.target.closest( '#searchSuggest' ) &&
			( e.key === 'ArrowDown' || e.key === 'ArrowUp' )
		) {
			e.preventDefault();
			const items = $$( '#searchSuggest a' );
			const i = items.indexOf( document.activeElement );
			const n =
				e.key === 'ArrowDown'
					? Math.min( items.length - 1, i + 1 )
					: i - 1;
			( n < 0 ? $( '#overlaySearch' ) : items[ n ] ).focus();
		}
		if (
			e.target.getAttribute &&
			e.target.getAttribute( 'role' ) === 'tab' &&
			( e.key === 'ArrowRight' || e.key === 'ArrowLeft' )
		) {
			const list = e.target.closest( '[role="tablist"]' );
			if ( list ) {
				const tabs = $$( '[role="tab"]', list );
				const i = tabs.indexOf( e.target );
				const n =
					tabs[
						( i +
							( e.key === 'ArrowRight' ? 1 : -1 ) +
							tabs.length ) %
							tabs.length
					];
				n.focus();
				n.click();
			}
		}
		const ae = document.activeElement;
		if (
			e.key === '/' &&
			! /INPUT|TEXTAREA|SELECT/.test( ae.tagName ) &&
			! ae.isContentEditable &&
			$( '#searchOverlay' )
		) {
			e.preventDefault();
			openSearch();
		}
	} );

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	document.addEventListener(
		'error',
		( e ) => {
			if ( e.target.tagName === 'IMG' && e.target.closest( '.media' ) ) {
				e.target.closest( '.media' ).classList.add( 'is-empty' );
				e.target.remove();
			}
		},
		true
	);

	function ready() {
		initReveal();
		onScroll();
		trackView();
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}

	/**
	 * Visitor's privacy choice: 'all', 'essential' or '' (not chosen yet).
	 * Without a consent banner every optional feature is allowed.
	 */
	function consent() {
		const m = document.cookie.match(
			/(?:^|;\s*)tv_consent=(essential|all)/
		);
		if ( m ) {
			return m[ 1 ];
		}
		return cfg.consent ? '' : 'all';
	}

	window.techvenza = {
		consent,
		$,
		$$,
		esc,
		icon,
		api,
		session,
		toast,
		openModal,
		closeModal,
		confirmDialog,
		initReveal,
		applyBookmarks,
		applyTopics,
		copyText,
		cfg,
		t,
		actions,
		reduceMotion,
	};
} )();
