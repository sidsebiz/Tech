/**
 * Techvenza — progressive form handling.
 *
 * Every form works without JavaScript (normal POST to admin-post.php or wp-login.php).
 * With JavaScript, forms marked [data-tv-form] are validated inline (accessible error
 * messages, focus on the first invalid field) and, when they declare data-endpoint, are
 * submitted to the REST API and replaced by the server's success message in place.
 *
 * Validation attributes: required, type=email|url, minlength/maxlength, data-min (chars),
 * data-urls (at least one URL in a list), data-match="otherFieldId", and
 * data-required-group on a .field wrapping a checkbox group.
 */
( function () {
	'use strict';
	const tv = window.techvenza;
	if ( ! tv ) {
		return;
	}
	const { $, $$, t } = tv;
	const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
	const isUrl = ( v ) => {
		try {
			const u = new URL( v );
			return u.protocol === 'https:' || u.protocol === 'http:';
		} catch ( e ) {
			return false;
		}
	};

	function setError( field, on, msg ) {
		if ( ! field ) {
			return;
		}
		field.classList.toggle( 'invalid', on );
		const ctrl =
			$(
				'input:not([type=checkbox]):not([type=radio]),select,textarea',
				field
			) || $( 'input', field );
		if ( ctrl ) {
			if ( on ) {
				ctrl.setAttribute( 'aria-invalid', 'true' );
			} else {
				ctrl.removeAttribute( 'aria-invalid' );
			}
		}
		if ( on && msg ) {
			const span =
				$( '.error span:last-child', field ) || $( '.error', field );
			if ( span ) {
				span.textContent = msg;
			}
		}
	}

	function checkControl( el ) {
		if ( el.type === 'checkbox' ) {
			return ! el.required || el.checked;
		}
		const v = ( el.value || '' ).trim();
		if ( el.required && ! v ) {
			return false;
		}
		if ( ! v ) {
			return true;
		}
		if ( el.type === 'email' && ! EMAIL.test( v ) ) {
			return false;
		}
		if ( el.type === 'url' && ! isUrl( v ) ) {
			return false;
		}
		if ( el.dataset.min && v.length < Number( el.dataset.min ) ) {
			return false;
		}
		if ( el.dataset.max && v.length > Number( el.dataset.max ) ) {
			return false;
		}
		if (
			el.hasAttribute( 'data-urls' ) &&
			! v
				.split( /[\s,]+/ )
				.filter( Boolean )
				.some( isUrl )
		) {
			return false;
		}
		if ( el.dataset.match ) {
			const other = document.getElementById( el.dataset.match );
			if ( other && other.value !== el.value ) {
				return false;
			}
		}
		return true;
	}

	function validate( form ) {
		let first = null;
		$$( '.field', form ).forEach( ( field ) => {
			let ok = true;
			if ( field.hasAttribute( 'data-required-group' ) ) {
				ok = $$( 'input[type=checkbox],input[type=radio]', field ).some(
					( i ) => i.checked
				);
			} else {
				$$( 'input,select,textarea', field ).forEach( ( el ) => {
					if ( el.type !== 'hidden' && ! checkControl( el ) ) {
						ok = false;
					}
				} );
			}
			setError( field, ! ok );
			if ( ! ok && ! first ) {
				first = field;
			}
		} );
		if ( first ) {
			const ctrl = $( 'input,select,textarea', first );
			if ( ctrl ) {
				ctrl.focus();
			}
			tv.toast( t.fixFields, 'err' );
			return false;
		}
		return true;
	}

	function serialize( form ) {
		const out = {};
		new FormData( form ).forEach( ( v, k ) => {
			if ( k.endsWith( '[]' ) ) {
				const key = k.slice( 0, -2 );
				( out[ key ] = out[ key ] || [] ).push( v );
			} else {
				out[ k ] = v;
			}
		} );
		$$( 'input[type=checkbox]:not([name$="[]"])', form ).forEach( ( c ) => {
			out[ c.name ] = c.checked;
		} );
		return out;
	}

	/**
	 * Error banner for a form (inside it, or in its card/panel).
	 *
	 * @param {HTMLFormElement} form Form.
	 */
	function errorNote( form ) {
		for ( const sel of [ '[data-form-box]', '.panel', '.auth-card' ] ) {
			const box = form.closest( sel );
			const note = box && $( '[data-form-error]', box );
			if ( note ) {
				return note;
			}
		}
		return null;
	}

	async function submit( form ) {
		const note0 = errorNote( form );
		if ( note0 ) {
			note0.hidden = true;
		}
		const btn = $( '[type=submit]', form );
		const label = btn ? btn.innerHTML : '';
		if ( btn ) {
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
		}
		try {
			await recaptchaToken( form );
			const data = await tv.api( form.dataset.endpoint, {
				method: form.dataset.method || 'POST',
				body: serialize( form ),
			} );
			if ( data.redirect ) {
				window.location.assign( data.redirect );
				return;
			}
			const box = form.closest( '[data-form-box]' ) || form;
			if ( data.html ) {
				box.innerHTML = data.html;
				const focus = $( 'h2,h3,[tabindex]', box );
				if ( focus ) {
					focus.setAttribute( 'tabindex', '-1' );
					focus.focus();
				}
				box.scrollIntoView( {
					block: 'center',
					behavior: tv.reduceMotion() ? 'auto' : 'smooth',
				} );
			}
			if ( data.message ) {
				tv.toast( data.message, 'ok' );
			} else if ( ! data.html ) {
				tv.toast( t.savedOk || 'Saved.', 'ok' );
			}
			form.dispatchEvent(
				new CustomEvent( 'techvenza:submitted', {
					bubbles: true,
					detail: data,
				} )
			);
		} catch ( e ) {
			const errors =
				( e.data && e.data.data && e.data.data.errors ) ||
				( e.data && e.data.errors ) ||
				null;
			if ( errors ) {
				Object.keys( errors ).forEach( ( name ) =>
					setError(
						$( '[data-field="' + name + '"]', form ),
						true,
						errors[ name ]
					)
				);
				const f = $(
					'.field.invalid input,.field.invalid select,.field.invalid textarea',
					form
				);
				if ( f ) {
					f.focus();
				}
			}
			const note = errorNote( form );
			if ( note ) {
				note.hidden = false;
				note.querySelector( 'span' ).textContent = e.message;
			}
			tv.toast( e.message || t.error, 'err' );
		} finally {
			if ( btn ) {
				btn.disabled = false;
				btn.removeAttribute( 'aria-busy' );
				btn.innerHTML = label;
			}
		}
	}

	/**
	 * reCAPTCHA v3: fetch a fresh token into the hidden field right before submitting.
	 *
	 * @param {HTMLFormElement} form Form.
	 */
	function recaptchaToken( form ) {
		const input = $( '[data-recaptcha]', form );
		if ( ! input || ! window.grecaptcha ) {
			return Promise.resolve();
		}
		return new Promise( ( resolve ) => {
			window.grecaptcha.ready( () => {
				window.grecaptcha
					.execute( input.dataset.recaptcha, {
						action: input.dataset.action || 'submit',
					} )
					.then( ( token ) => {
						input.value = token;
						resolve();
					}, resolve );
			} );
		} );
	}

	document.addEventListener( 'submit', ( e ) => {
		const form = e.target.closest( 'form[data-tv-form]' );
		if ( ! form ) {
			return;
		}
		if ( ! validate( form ) ) {
			e.preventDefault();
			return;
		}
		if ( form.dataset.endpoint ) {
			e.preventDefault();
			submit( form );
		} else if ( $( '[data-recaptcha]', form ) && ! form.dataset.tvReady ) {
			e.preventDefault();
			recaptchaToken( form ).then( () => {
				form.dataset.tvReady = '1';
				form.submit();
			} );
		}
	} );

	document.addEventListener( 'change', ( e ) => {
		const field = e.target.closest && e.target.closest( '.field.invalid' );
		if (
			field &&
			( e.target.type === 'checkbox' ||
				e.target.type === 'radio' ||
				e.target.tagName === 'SELECT' )
		) {
			setError( field, false );
		}
	} );

	window.techvenza.forms = { validate, serialize, setError };
} )();
