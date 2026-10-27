/**
 * Techvenza — reader dashboard and author portal interactions.
 *
 * Sidebar (collapse / mobile drawer), list actions (clear saved & history, delete draft,
 * mark notifications read), account deletion request, profile photo upload and preview,
 * auto-submitting filters. Every action has a server endpoint; pages work without it for reading.
 */
( function () {
	'use strict';
	const tv = window.techvenza;
	if ( ! tv ) {
		return;
	}
	const { $, $$, t } = tv;
	const shell = $( '#appShell' );
	const store = {
		get( k ) {
			try {
				return window.localStorage.getItem( 'tv:' + k );
			} catch ( e ) {
				return null;
			}
		},
		set( k, v ) {
			if ( tv.consent && tv.consent() !== 'all' ) {
				return;
			}
			try {
				window.localStorage.setItem( 'tv:' + k, v );
			} catch ( e ) {}
		},
	};

	/* Sidebar */
	if ( shell && store.get( 'sbCollapsed' ) === '1' ) {
		shell.classList.add( 'collapsed' );
		const b = $( '[data-action="sbCollapse"]' );
		if ( b ) {
			b.setAttribute( 'aria-pressed', 'true' );
		}
	}
	tv.actions.sbCollapse = ( b ) => {
		const on = ! shell.classList.contains( 'collapsed' );
		shell.classList.toggle( 'collapsed', on );
		b.setAttribute( 'aria-pressed', String( on ) );
		b.setAttribute(
			'aria-label',
			on
				? t.expandSidebar || 'Expand sidebar'
				: t.collapseSidebar || 'Collapse sidebar'
		);
		store.set( 'sbCollapsed', on ? '1' : '0' );
	};
	tv.actions.sbOpen = () => {
		shell.classList.add( 'sb-open' );
		window.setTimeout( () => {
			const l = $( '.sidebar .sb-link', shell );
			if ( l ) {
				l.focus();
			}
		}, 60 );
	};
	tv.actions.sbClose = () => {
		shell.classList.remove( 'sb-open' );
		const b = $( '[data-action="sbOpen"]' );
		if ( b ) {
			b.focus();
		}
	};
	document.addEventListener( 'keydown', ( e ) => {
		if (
			e.key === 'Escape' &&
			shell &&
			shell.classList.contains( 'sb-open' )
		) {
			tv.actions.sbClose();
		}
	} );

	/* Confirmed destructive actions */
	async function confirmThen( opts, fn ) {
		const ok = await tv.confirmDialog( opts );
		if ( ok ) {
			try {
				await fn();
			} catch ( e ) {
				tv.toast( e.message || t.error, 'err' );
			}
		}
	}
	tv.actions.clearSaved = () =>
		confirmThen(
			{
				title: t.clearSavedTitle || 'Clear saved articles?',
				text:
					t.clearSavedText ||
					'This removes every story from your reading list.',
				confirm: t.clearAll || 'Clear all',
				tone: 'danger',
			},
			async () => {
				await tv.api( 'me/bookmarks', { method: 'DELETE' } );
				window.location.reload();
			}
		);
	tv.actions.clearHistory = () =>
		confirmThen(
			{
				title: t.clearHistoryTitle || 'Clear reading history?',
				text:
					t.clearHistoryText ||
					'Your recently viewed list will be removed.',
				confirm: t.clearHistory || 'Clear history',
				tone: 'danger',
			},
			async () => {
				await tv.api( 'me/history', { method: 'DELETE' } );
				window.location.reload();
			}
		);
	tv.actions.deleteDraft = ( b ) =>
		confirmThen(
			{
				title: t.deleteDraftTitle || 'Delete this draft?',
				text: (
					t.deleteDraftText || '“%s” will be moved to the trash.'
				).replace( '%s', b.dataset.title || '' ),
				confirm: t.deleteDraft || 'Delete draft',
				tone: 'danger',
			},
			async () => {
				const d = await tv.api( 'portal/drafts/' + b.dataset.id, {
					method: 'DELETE',
				} );
				const row = b.closest( '[data-row]' );
				if ( row ) {
					row.remove();
				}
				tv.toast( d.message || 'Deleted', 'info' );
			}
		);
	tv.actions.deleteAccount = () =>
		confirmThen(
			{
				title: t.deleteAccountTitle || 'Request account deletion?',
				text:
					t.deleteAccountText ||
					'We will email you a link to confirm the request.',
				confirm: t.sendRequest || 'Send request',
				tone: 'danger',
			},
			async () => {
				const d = await tv.api( 'me/deletion-request', {
					method: 'POST',
				} );
				tv.toast( d.message, 'info' );
			}
		);
	tv.actions.readAll = async () => {
		await tv.api( 'me/notifications/read', {
			method: 'POST',
			body: { id: 0 },
		} );
		$$( '.notif.unread' ).forEach( ( n ) =>
			n.classList.remove( 'unread' )
		);
		$$( '.sb-count' ).forEach( ( c ) => c.remove() );
		tv.toast( t.allRead || 'All notifications marked as read.', 'info' );
	};
	document.addEventListener( 'click', ( e ) => {
		const a = e.target.closest( '[data-read-notif]' );
		if ( a ) {
			tv.api( 'me/notifications/read', {
				method: 'POST',
				body: { id: Number( a.dataset.readNotif ) },
			} ).catch( () => {} );
		}
	} );

	/* Saved list: drop rows that were un-saved */
	document.addEventListener( 'techvenza:bookmarks', ( e ) => {
		const ids = new Set( ( e.detail || [] ).map( Number ) );
		$$( '[data-saved-list] [data-row]' ).forEach( ( row ) => {
			if ( ! ids.has( Number( row.dataset.row ) ) ) {
				row.remove();
			}
		} );
	} );

	/* Auto-submit filters */
	document.addEventListener( 'change', ( e ) => {
		if (
			e.target.matches &&
			e.target.matches( '[data-autosubmit]' ) &&
			e.target.form
		) {
			e.target.form.submit();
		}
	} );

	/* Profile photo upload */
	const photo = $( '[data-photo-input]' );
	if ( photo ) {
		photo.addEventListener( 'change', async () => {
			const file = photo.files[ 0 ];
			if ( ! file ) {
				return;
			}
			if (
				! /^image\/(jpeg|png|webp)$/.test( file.type ) ||
				file.size > 3 * 1024 * 1024
			) {
				tv.toast(
					t.photoInvalid || 'Use a JPG, PNG or WebP under 3 MB.',
					'err'
				);
				photo.value = '';
				return;
			}
			const body = new FormData();
			body.append( 'photo', file );
			try {
				const res = await fetch( tv.cfg.rest + 'portal/photo', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': tv.session.nonce },
					body,
				} );
				const d = await res.json();
				if ( ! res.ok ) {
					throw new Error( d.message || t.error );
				}
				const hidden = photo.form.querySelector( '[name="photo"]' );
				if ( hidden ) {
					hidden.value = d.id;
				}
				const prev = $( '[data-avatar-preview]' );
				if ( prev ) {
					prev.innerHTML =
						'<img class="avatar lg" src="' +
						tv.esc( d.url ) +
						'" alt="" width="72" height="72" style="object-fit:cover">';
				}
				tv.toast( t.photoSaved || 'Photo uploaded.', 'ok' );
			} catch ( err ) {
				tv.toast( err.message, 'err' );
			}
		} );
	}

	/* Profile preview after save */
	document.addEventListener( 'techvenza:submitted', ( e ) => {
		const box = $( '[data-profile-preview]' );
		if ( box && e.detail && e.detail.preview ) {
			box.innerHTML = e.detail.preview;
			tv.initReveal( box );
		}
	} );
} )();
