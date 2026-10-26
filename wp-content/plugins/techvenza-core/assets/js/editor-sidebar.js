/**
 * Techvenza — block editor document sidebar for articles.
 *
 * Plain ES2019 against the wp.* globals (no build step required).
 * Panels: Article details, Disclosures & corrections, SEO & social, Editorial workflow.
 */
( function ( wp, cfg ) {
	if ( ! wp || ! wp.plugins || ! cfg ) {
		return;
	}
	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { useState } = wp.element;
	const { __, sprintf } = wp.i18n;
	const { useSelect, useDispatch } = wp.data;
	const C = wp.components;
	const PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	const PluginPrePublishPanel =
		( wp.editor && wp.editor.PluginPrePublishPanel ) ||
		( wp.editPost && wp.editPost.PluginPrePublishPanel );

	function useMeta() {
		const meta = useSelect(
			( s ) => s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );
		const set = ( key, value ) => editPost( { meta: { [ key ]: value } } );
		return [ meta, set ];
	}

	function Repeater( { items, onChange, fields, addLabel } ) {
		const list = Array.isArray( items ) ? items : [];
		const update = ( i, patch ) =>
			onChange(
				list.map( ( it, j ) =>
					j === i
						? typeof it === 'object'
							? Object.assign( {}, it, patch )
							: patch
						: it
				)
			);
		return el(
			'div',
			{ className: 'tv-repeater' },
			list.map( ( item, i ) =>
				el(
					'div',
					{
						key: i,
						style: {
							borderTop: '1px solid #e0e0e0',
							paddingTop: 8,
							marginTop: 8,
						},
					},
					fields
						? fields.map( ( f ) =>
								el(
									f.type === 'textarea'
										? C.TextareaControl
										: C.TextControl,
									{
										key: f.key,
										label: f.label,
										type: f.inputType,
										value: item[ f.key ] || '',
										onChange: ( v ) =>
											update( i, { [ f.key ]: v } ),
									}
								)
						  )
						: el( C.TextareaControl, {
								label: sprintf(
									/* translators: %d: item number */ __(
										'Point %d',
										'techvenza-core'
									),
									i + 1
								),
								value: item,
								rows: 2,
								onChange: ( v ) => update( i, v ),
						  } ),
					el(
						C.Button,
						{
							isDestructive: true,
							variant: 'link',
							onClick: () =>
								onChange( list.filter( ( _, j ) => j !== i ) ),
						},
						__( 'Remove', 'techvenza-core' )
					)
				)
			),
			el(
				C.Button,
				{
					variant: 'secondary',
					style: { marginTop: 8 },
					onClick: () =>
						onChange( list.concat( [ fields ? {} : '' ] ) ),
				},
				addLabel
			)
		);
	}

	function Counter( { value, max } ) {
		const n = ( value || '' ).length;
		return el(
			'p',
			{
				style: {
					textAlign: 'right',
					fontSize: 11,
					margin: '-12px 0 12px',
					color: n > max ? '#cc1818' : '#757575',
				},
			},
			n + ' / ' + max
		);
	}

	function ArticlePanel() {
		const [ meta, set ] = useMeta();
		const types = Object.keys( cfg.types ).map( ( k ) => ( {
			value: k,
			label: cfg.types[ k ],
		} ) );
		return el(
			PluginDocumentSettingPanel,
			{
				name: 'techvenza-article',
				title: __( 'Article details', 'techvenza-core' ),
				className: 'techvenza-article-panel',
			},
			el( C.SelectControl, {
				label: __( 'Format', 'techvenza-core' ),
				value: meta.tv_article_type,
				options: types,
				onChange: ( v ) => set( 'tv_article_type', v ),
			} ),
			meta.tv_article_type === 'guide' &&
				el( C.SelectControl, {
					label: __( 'Guide level', 'techvenza-core' ),
					value: meta.tv_guide_level,
					options: [
						{
							value: '',
							label: __( 'All levels', 'techvenza-core' ),
						},
					].concat(
						cfg.levels.map( ( l ) => ( { value: l, label: l } ) )
					),
					onChange: ( v ) => set( 'tv_guide_level', v ),
				} ),
			el( C.ToggleControl, {
				label: __( 'Set reading time manually', 'techvenza-core' ),
				checked: !! meta.tv_reading_time_manual,
				help: meta.tv_reading_time_manual
					? null
					: sprintf(
							/* translators: %d: minutes */ __(
								'Calculated on save: %d min',
								'techvenza-core'
							),
							meta.tv_reading_time || 1
					  ),
				onChange: ( v ) => set( 'tv_reading_time_manual', v ),
			} ),
			meta.tv_reading_time_manual &&
				el( C.RangeControl, {
					label: __( 'Reading time (minutes)', 'techvenza-core' ),
					min: 1,
					max: 60,
					value: meta.tv_reading_time || 1,
					onChange: ( v ) => set( 'tv_reading_time', v ),
				} ),
			el(
				'p',
				{ style: { fontWeight: 600, marginBottom: 4 } },
				__( 'Key points', 'techvenza-core' )
			),
			el(
				'p',
				{ style: { fontSize: 12, color: '#757575', marginTop: 0 } },
				__(
					'Shown as takeaways and used in summaries.',
					'techvenza-core'
				)
			),
			el( Repeater, {
				items: meta.tv_key_points,
				onChange: ( v ) => set( 'tv_key_points', v ),
				addLabel: __( 'Add key point', 'techvenza-core' ),
			} ),
			cfg.canReview &&
				el(
					Fragment,
					null,
					el( 'hr' ),
					el(
						'p',
						{ style: { fontWeight: 600 } },
						__( 'Editorial placement', 'techvenza-core' )
					),
					el( C.ToggleControl, {
						label: __( 'Featured', 'techvenza-core' ),
						checked: !! meta.tv_is_featured,
						onChange: ( v ) => set( 'tv_is_featured', v ),
					} ),
					el( C.ToggleControl, {
						label: __( "Editor's pick", 'techvenza-core' ),
						checked: !! meta.tv_is_editors_pick,
						onChange: ( v ) => set( 'tv_is_editors_pick', v ),
					} ),
					el( C.ToggleControl, {
						label: __( 'Force into Trending', 'techvenza-core' ),
						help: __(
							'Overrides the views-based ranking.',
							'techvenza-core'
						),
						checked: !! meta.tv_is_trending_manual,
						onChange: ( v ) => set( 'tv_is_trending_manual', v ),
					} ),
					el( C.SelectControl, {
						label: __( 'Assigned editor', 'techvenza-core' ),
						value: meta.tv_assigned_editor || 0,
						options: [
							{
								value: 0,
								label: __( 'Unassigned', 'techvenza-core' ),
							},
						].concat( cfg.editors ),
						onChange: ( v ) =>
							set( 'tv_assigned_editor', parseInt( v, 10 ) || 0 ),
					} ),
					el( C.TextareaControl, {
						label: __( 'Scheduling note', 'techvenza-core' ),
						value: meta.tv_scheduled_note,
						rows: 2,
						onChange: ( v ) => set( 'tv_scheduled_note', v ),
					} )
				)
		);
	}

	function DisclosurePanel() {
		const [ meta, set ] = useMeta();
		return el(
			PluginDocumentSettingPanel,
			{
				name: 'techvenza-disclosures',
				title: __( 'Disclosures & corrections', 'techvenza-core' ),
			},
			el( C.ToggleControl, {
				label: __( 'AI tools were used materially', 'techvenza-core' ),
				checked: !! meta.tv_ai_disclosure,
				onChange: ( v ) => set( 'tv_ai_disclosure', v ),
			} ),
			meta.tv_ai_disclosure &&
				el( C.TextareaControl, {
					label: __( 'AI disclosure text', 'techvenza-core' ),
					value: meta.tv_ai_disclosure_text,
					rows: 3,
					onChange: ( v ) => set( 'tv_ai_disclosure_text', v ),
				} ),
			el( C.TextareaControl, {
				label: __( 'Conflicts of interest', 'techvenza-core' ),
				help: __(
					'Employment, investments or client relationships related to the topic.',
					'techvenza-core'
				),
				value: meta.tv_conflict_disclosure,
				rows: 3,
				onChange: ( v ) => set( 'tv_conflict_disclosure', v ),
			} ),
			cfg.canReview &&
				el( C.ToggleControl, {
					label: __( 'Sponsored content', 'techvenza-core' ),
					checked: !! meta.tv_is_sponsored,
					onChange: ( v ) => set( 'tv_is_sponsored', v ),
				} ),
			cfg.canReview &&
				meta.tv_is_sponsored &&
				el( C.TextControl, {
					label: __( 'Sponsor name', 'techvenza-core' ),
					value: meta.tv_sponsor_name,
					onChange: ( v ) => set( 'tv_sponsor_name', v ),
				} ),
			el(
				'p',
				{ style: { fontWeight: 600, marginBottom: 4 } },
				__( 'Corrections', 'techvenza-core' )
			),
			el( Repeater, {
				items: meta.tv_corrections,
				onChange: ( v ) => set( 'tv_corrections', v ),
				fields: [
					{
						key: 'date',
						label: __( 'Date', 'techvenza-core' ),
						inputType: 'date',
					},
					{
						key: 'text',
						label: __( 'What changed', 'techvenza-core' ),
						type: 'textarea',
					},
				],
				addLabel: __( 'Add correction', 'techvenza-core' ),
			} )
		);
	}

	function SeoPanel() {
		const [ meta, set ] = useMeta();
		const post = useSelect( ( s ) => {
			const ed = s( 'core/editor' );
			const featured = ed.getEditedPostAttribute( 'featured_media' );
			const media = featured ? s( 'core' ).getMedia( featured ) : null;
			return {
				title: ed.getEditedPostAttribute( 'title' ) || '',
				excerpt: ed.getEditedPostAttribute( 'excerpt' ) || '',
				slug: ed.getEditedPostAttribute( 'slug' ) || '',
				image: media && media.source_url,
			};
		}, [] );
		const title = meta.tv_seo_title || post.title;
		const desc = meta.tv_seo_description || post.excerpt;
		return el(
			PluginDocumentSettingPanel,
			{
				name: 'techvenza-seo',
				title: __( 'SEO & social preview', 'techvenza-core' ),
			},
			el( C.TextControl, {
				label: __( 'SEO title', 'techvenza-core' ),
				value: meta.tv_seo_title,
				placeholder: post.title,
				onChange: ( v ) => set( 'tv_seo_title', v ),
			} ),
			el( Counter, { value: title, max: 60 } ),
			el( C.TextareaControl, {
				label: __( 'Meta description', 'techvenza-core' ),
				value: meta.tv_seo_description,
				placeholder: post.excerpt,
				rows: 3,
				onChange: ( v ) => set( 'tv_seo_description', v ),
			} ),
			el( Counter, { value: desc, max: 160 } ),
			el(
				'div',
				{
					style: {
						fontFamily: 'Arial, sans-serif',
						border: '1px solid #ddd',
						borderRadius: 8,
						padding: 12,
						marginBottom: 12,
					},
				},
				el(
					'div',
					{ style: { fontSize: 12, color: '#4D5156' } },
					cfg.siteDomain + ' › ' + ( post.slug || '…' )
				),
				el(
					'div',
					{
						style: {
							fontSize: 16,
							color: '#1A0DAB',
							lineHeight: 1.3,
						},
					},
					title.length > 62
						? title.slice( 0, 60 ) + '…'
						: title || __( 'Add a title', 'techvenza-core' )
				),
				el(
					'div',
					{ style: { fontSize: 12, color: '#4D5156' } },
					desc.length > 162
						? desc.slice( 0, 158 ) + '…'
						: desc ||
								__(
									'Add an excerpt or meta description.',
									'techvenza-core'
								)
				)
			),
			el(
				'div',
				{
					style: {
						border: '1px solid #ddd',
						borderRadius: 8,
						overflow: 'hidden',
						marginBottom: 12,
					},
				},
				el( 'div', {
					style: {
						aspectRatio: '1.91/1',
						background: post.image
							? 'center/cover url(' + post.image + ')'
							: 'linear-gradient(135deg,#2563EB,#7C3AED)',
					},
				} ),
				el(
					'div',
					{
						style: {
							padding: '8px 10px',
							background: '#f8fafc',
							fontSize: 12,
						},
					},
					el(
						'div',
						{
							style: {
								textTransform: 'uppercase',
								color: '#64748B',
								fontSize: 10,
							},
						},
						cfg.siteDomain
					),
					el(
						'strong',
						null,
						post.title || __( 'Untitled', 'techvenza-core' )
					)
				)
			),
			el( C.TextControl, {
				label: __( 'Canonical URL (optional)', 'techvenza-core' ),
				type: 'url',
				value: meta.tv_canonical_url,
				onChange: ( v ) => set( 'tv_canonical_url', v ),
			} ),
			el( C.ToggleControl, {
				label: __(
					'Hide from search engines (noindex)',
					'techvenza-core'
				),
				checked: !! meta.tv_noindex,
				onChange: ( v ) => set( 'tv_noindex', v ),
			} )
		);
	}

	/* Submission checklist shared by the workflow panel and the pre-publish panel. */
	function useChecklist() {
		return useSelect( ( s ) => {
			const ed = s( 'core/editor' );
			const blocks = s( 'core/block-editor' ).getBlocks();
			const meta = ed.getEditedPostAttribute( 'meta' ) || {};
			const content = ed.getEditedPostContent() || '';
			const text = content
				.replace( /<!--[\s\S]*?-->/g, ' ' )
				.replace( /<[^>]+>/g, ' ' );
			const words = text.trim() ? text.trim().split( /\s+/ ).length : 0;
			const featured = ed.getEditedPostAttribute( 'featured_media' );
			const media = featured ? s( 'core' ).getMedia( featured ) : null;
			const cats = (
				ed.getEditedPostAttribute( 'categories' ) || []
			).filter( ( c ) => c !== 1 );
			const excerpt = ed.getEditedPostAttribute( 'excerpt' ) || '';
			const metaDesc = meta.tv_seo_description || excerpt;
			const imagesMissingAlt = blocks.filter(
				( b ) => b.name === 'core/image' && ! b.attributes.alt
			).length;
			return [
				{
					ok: !! (
						ed.getEditedPostAttribute( 'title' ) || ''
					).trim(),
					label: __( 'Title added', 'techvenza-core' ),
				},
				{
					ok: excerpt.trim().length >= 40,
					label: __(
						'Excerpt of at least 40 characters',
						'techvenza-core'
					),
				},
				{
					ok: cats.length > 0,
					label: __( 'Category chosen', 'techvenza-core' ),
				},
				{
					ok: words >= cfg.minWords,
					label: sprintf(
						/* translators: 1: words, 2: minimum */ __(
							'At least %2$d words (%1$d now)',
							'techvenza-core'
						),
						words,
						cfg.minWords
					),
				},
				{
					ok: ! featured || ( media && !! media.alt_text ),
					label: __( 'Cover image has alt text', 'techvenza-core' ),
				},
				{
					ok: imagesMissingAlt === 0,
					label: __(
						'All inline images have alt text',
						'techvenza-core'
					),
				},
				{
					ok: metaDesc.length <= 160,
					label: __(
						'Meta description ≤ 160 characters',
						'techvenza-core'
					),
				},
			];
		}, [] );
	}

	function Checklist( { items } ) {
		return el(
			'ul',
			{ style: { margin: '0 0 12px', padding: 0, listStyle: 'none' } },
			items.map( ( it, i ) =>
				el(
					'li',
					{
						key: i,
						style: {
							display: 'flex',
							gap: 6,
							alignItems: 'flex-start',
							fontSize: 12,
							margin: '4px 0',
							color: it.ok ? '#047857' : '#b45309',
						},
					},
					el( 'span', { 'aria-hidden': true }, it.ok ? '✓' : '•' ),
					el(
						'span',
						null,
						it.label,
						el(
							'span',
							{ className: 'screen-reader-text' },
							it.ok
								? __( '(done)', 'techvenza-core' )
								: __( '(to do)', 'techvenza-core' )
						)
					)
				)
			)
		);
	}

	function WorkflowPanel() {
		const info = useSelect( ( s ) => {
			const ed = s( 'core/editor' );
			return {
				id: ed.getCurrentPostId(),
				status: ed.getEditedPostAttribute( 'status' ),
				dirty: ed.isEditedPostDirty(),
				saving: ed.isSavingPost(),
			};
		}, [] );
		const items = useChecklist();
		const [ busy, setBusy ] = useState( false );
		const [ note, setNote ] = useState( '' );
		const { savePost } = useDispatch( 'core/editor' );
		const { createNotice } = useDispatch( 'core/notices' );
		const label = cfg.statuses[ info.status ] || info.status;
		const canSubmit =
			[ 'draft', 'auto-draft', 'changes_requested', 'rejected' ].indexOf(
				info.status
			) !== -1;
		const allOk = items.every( ( i ) => i.ok );
		const action =
			info.status === 'changes_requested' ? 'resubmit' : 'submit';

		const submit = async () => {
			setBusy( true );
			try {
				if ( info.dirty || info.status === 'auto-draft' ) {
					await savePost();
				}
				await wp.apiFetch( {
					path: '/techvenza/v1/posts/' + info.id + '/transition',
					method: 'POST',
					data: { action, note },
				} );
				createNotice(
					'success',
					__(
						'Submitted for editorial review. Editing is locked until an editor responds.',
						'techvenza-core'
					),
					{ type: 'snackbar' }
				);
				window.setTimeout( () => window.location.reload(), 900 );
			} catch ( e ) {
				createNotice(
					'error',
					( e && e.message ) ||
						__( 'Could not submit.', 'techvenza-core' )
				);
				setBusy( false );
			}
		};
		return el(
			PluginDocumentSettingPanel,
			{
				name: 'techvenza-workflow',
				title: __( 'Editorial workflow', 'techvenza-core' ),
				initialOpen: true,
			},
			el(
				'p',
				null,
				__( 'Status:', 'techvenza-core' ) + ' ',
				el( 'strong', null, label )
			),
			canSubmit
				? el(
						Fragment,
						null,
						el( Checklist, { items } ),
						action === 'resubmit' &&
							el( C.TextareaControl, {
								label: __(
									'Notes for your editor',
									'techvenza-core'
								),
								value: note,
								rows: 3,
								onChange: setNote,
							} ),
						el(
							C.Button,
							{
								variant: 'primary',
								isBusy: busy,
								disabled: busy || ! allOk || info.saving,
								onClick: submit,
								__next40pxDefaultSize: true,
							},
							action === 'resubmit'
								? __( 'Resubmit for review', 'techvenza-core' )
								: __( 'Submit for review', 'techvenza-core' )
						),
						! allOk &&
							el(
								'p',
								{ style: { fontSize: 12, color: '#757575' } },
								__(
									'Complete the checklist to submit.',
									'techvenza-core'
								)
							)
				  )
				: el(
						'p',
						{ style: { fontSize: 12, color: '#757575' } },
						__(
							'This article is with the editorial team. Review actions are in Techvenza → Review queue.',
							'techvenza-core'
						)
				  ),
			el(
				'p',
				null,
				el(
					'a',
					{ href: cfg.portalUrl },
					__( 'Open the author portal →', 'techvenza-core' )
				)
			)
		);
	}

	function PrePublishChecklist() {
		const items = useChecklist();
		return el(
			PluginPrePublishPanel,
			{
				title: __( 'Techvenza checklist', 'techvenza-core' ),
				initialOpen: true,
			},
			el( Checklist, { items } )
		);
	}

	wp.plugins.registerPlugin( 'techvenza-article', {
		render: () =>
			el(
				Fragment,
				null,
				Object.keys( cfg.statuses || {} ).length
					? el( WorkflowPanel )
					: null,
				el( ArticlePanel ),
				el( DisclosurePanel ),
				el( SeoPanel ),
				PluginPrePublishPanel ? el( PrePublishChecklist ) : null
			),
	} );
} )( window.wp, window.techvenzaEditor );
