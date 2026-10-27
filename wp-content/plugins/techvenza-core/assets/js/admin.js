/**
 * Techvenza admin: calendar drag-to-reschedule, transition note validation, checklist confirmation,
 * and confirm prompts for destructive one-click forms.
 */
/* eslint-disable no-alert -- Native confirm/alert are the accessible, expected admin pattern. */
( function () {
	'use strict';

	const cfg = window.techvenzaAdmin || { i18n: {} };
	const t = ( key, ...args ) => {
		let s = cfg.i18n[ key ] || key;
		args.forEach( ( a, i ) => {
			s = s.replace( '%' + ( i + 1 ) + '$s', a );
		} );
		return s;
	};

	/* ---------- Confirm prompts ---------- */
	document.addEventListener( 'submit', ( e ) => {
		const form = e.target;
		if ( ! ( form instanceof HTMLFormElement ) ) {
			return;
		}
		const msg =
			form.getAttribute( 'data-confirm' ) ||
			( e.submitter && e.submitter.getAttribute( 'data-confirm-send' ) );
		if ( msg && ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );

	/* ---------- Transition form: note required / checklist ---------- */
	document.querySelectorAll( '[data-transition-form]' ).forEach( ( form ) => {
		const note = form.querySelector( 'textarea[name="note"]' );
		form.addEventListener( 'submit', ( e ) => {
			const btn = e.submitter;
			if ( ! btn ) {
				return;
			}
			if (
				btn.hasAttribute( 'data-needs-note' ) &&
				note &&
				! note.value.trim()
			) {
				e.preventDefault();
				note.setAttribute( 'aria-invalid', 'true' );
				note.setCustomValidity(
					note.getAttribute( 'placeholder' ) || ''
				);
				note.reportValidity();
				note.focus();
				return;
			}
			if ( 'approve' === btn.value ) {
				const checks = document.querySelectorAll(
					'[data-review-check]'
				);
				const missing = Array.from( checks ).some(
					( c ) => ! c.checked
				);
				if (
					missing &&
					! window.confirm( t( 'confirmApproveIncomplete' ) )
				) {
					e.preventDefault();
				}
			}
		} );
		if ( note ) {
			note.addEventListener( 'input', () => {
				note.removeAttribute( 'aria-invalid' );
				note.setCustomValidity( '' );
			} );
		}
	} );

	/* ---------- Calendar drag & drop ---------- */
	const cal = document.querySelector( '[data-calendar]' );
	if ( ! cal || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}
	const status = document.querySelector( '[data-cal-status]' );
	const say = ( msg ) => {
		if ( status ) {
			status.textContent = msg;
		}
	};
	let dragged = null;

	document.addEventListener( 'dragstart', ( e ) => {
		const item =
			e.target.closest &&
			e.target.closest( '.cal-item[draggable="true"]' );
		if ( ! item ) {
			return;
		}
		dragged = item;
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', item.dataset.id );
	} );
	document.addEventListener( 'dragend', () => {
		dragged = null;
		cal.querySelectorAll( '.day.drop' ).forEach( ( d ) =>
			d.classList.remove( 'drop' )
		);
	} );

	cal.addEventListener( 'dragover', ( e ) => {
		const day = e.target.closest( '.day[data-drop]' );
		if ( ! day || ! dragged ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		cal.querySelectorAll( '.day.drop' ).forEach(
			( d ) => d !== day && d.classList.remove( 'drop' )
		);
		day.classList.add( 'drop' );
	} );
	cal.addEventListener( 'dragleave', ( e ) => {
		const day = e.target.closest( '.day' );
		if ( day && ! day.contains( e.relatedTarget ) ) {
			day.classList.remove( 'drop' );
		}
	} );

	cal.addEventListener( 'drop', ( e ) => {
		const day = e.target.closest( '.day[data-drop]' );
		const item = dragged;
		if ( ! day || ! item ) {
			return;
		}
		e.preventDefault();
		day.classList.remove( 'drop' );
		const date = day.dataset.date;
		const label =
			day.querySelector( '.dn' )?.getAttribute( 'aria-label' ) || date;
		if ( ! window.confirm( t( 'moveTo', item.dataset.title, label ) ) ) {
			return;
		}
		const origin = item.parentElement;
		const next = item.nextSibling;
		const wasTray = !! item.closest( '[data-tray]' );
		day.appendChild( item );
		item.classList.add( 'saving' );
		window.wp
			.apiFetch( {
				path: '/techvenza/v1/calendar/move',
				method: 'POST',
				data: { id: Number( item.dataset.id ), date },
			} )
			.then( ( res ) => {
				item.classList.remove( 'saving' );
				item.classList.remove( 'approved' );
				item.classList.add( 'future' );
				if ( wasTray && origin && 'LI' === origin.tagName ) {
					origin.remove();
				}
				say( ( res && res.message ) || t( 'moved' ) );
			} )
			.catch( ( err ) => {
				item.classList.remove( 'saving' );
				origin.insertBefore( item, next );
				say( ( err && err.message ) || t( 'moveFailed' ) );
				window.alert( ( err && err.message ) || t( 'moveFailed' ) );
			} );
	} );
} )();
