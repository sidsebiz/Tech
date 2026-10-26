/**
 * Techvenza — generic editor UI for all server-rendered blocks.
 *
 * Each block's inspector controls are declared in its block.json under `tvControls`
 * (passed in as window.techvenzaBlocks). Previews use ServerSideRender so the editor
 * shows exactly what visitors see. Plain ES2019 against wp.* globals — no build step.
 */
( function ( wp, defs, icons ) {
	if ( ! wp || ! wp.blocks || ! defs ) {
		return;
	}
	const el = wp.element.createElement;
	const { Fragment, useState, useMemo } = wp.element;
	const { __ } = wp.i18n;
	const { useSelect } = wp.data;
	const {
		useBlockProps,
		InspectorControls,
		InnerBlocks,
		MediaUpload,
		MediaUploadCheck,
	} = wp.blockEditor;
	const C = wp.components;
	const ServerSideRender = wp.serverSideRender;

	/* ---------- Data hooks ---------- */
	function useCategories() {
		return useSelect(
			( s ) =>
				s( 'core' ).getEntityRecords( 'taxonomy', 'category', {
					per_page: 100,
					_fields: 'id,slug,name,parent',
				} ) || [],
			[]
		);
	}
	function usePosts( search, include ) {
		return useSelect(
			( s ) => {
				const q = {
					per_page: 20,
					_fields: 'id,title',
					status: 'publish',
				};
				if ( include && include.length ) {
					q.include = include;
				} else if ( search ) {
					q.search = search;
				}
				return (
					s( 'core' ).getEntityRecords( 'postType', 'post', q ) || []
				);
			},
			[ search, ( include || [] ).join( ',' ) ]
		);
	}

	/* ---------- Controls ---------- */
	function CategoryControl( { c, value, onChange } ) {
		const cats = useCategories();
		const opts = [
			{ value: '', label: __( '— Select —', 'techvenza-core' ) },
		].concat(
			cats.map( ( t ) => ( {
				value: t.slug,
				label: ( t.parent ? '— ' : '' ) + t.name,
			} ) )
		);
		return el( C.SelectControl, {
			label: c.label,
			help: c.help,
			value: value || '',
			options: opts,
			onChange,
		} );
	}

	function CategoriesControl( { c, value, onChange } ) {
		const cats = useCategories().filter( ( t ) => ! t.parent );
		const list = Array.isArray( value ) ? value : [];
		return el(
			C.BaseControl,
			{ label: c.label, help: c.help, id: 'tv-cats' },
			el(
				'div',
				{
					style: {
						maxHeight: 220,
						overflow: 'auto',
						padding: '4px 0',
					},
				},
				cats.map( ( t ) =>
					el( C.CheckboxControl, {
						key: t.slug,
						label: t.name,
						checked: list.indexOf( t.slug ) !== -1,
						onChange: ( on ) =>
							onChange(
								on
									? list.concat( [ t.slug ] )
									: list.filter( ( x ) => x !== t.slug )
							),
					} )
				)
			)
		);
	}

	function PostsControl( { c, value, onChange, single } ) {
		const ids = single ? ( value ? [ value ] : [] ) : value || [];
		const [ search, setSearch ] = useState( '' );
		const selected = usePosts( '', ids );
		const found = usePosts( search );
		const titleOf = ( p ) =>
			( p.title && ( p.title.rendered || p.title.raw ) ) || '#' + p.id;
		const set = ( list ) => onChange( single ? list[ 0 ] || 0 : list );
		return el(
			C.BaseControl,
			{ label: c.label, help: c.help, id: 'tv-posts' },
			el(
				'ul',
				{
					style: {
						margin: '4px 0 8px',
						paddingLeft: 0,
						listStyle: 'none',
					},
				},
				ids.map( ( id, i ) => {
					const p = selected.find( ( x ) => x.id === id );
					return el(
						'li',
						{
							key: id,
							style: {
								display: 'flex',
								gap: 4,
								alignItems: 'center',
								marginBottom: 4,
							},
						},
						el(
							'span',
							{ style: { flex: 1, fontSize: 12 } },
							p ? titleOf( p ) : '#' + id
						),
						! single &&
							i > 0 &&
							el( C.Button, {
								icon: 'arrow-up-alt2',
								label: __( 'Move up', 'techvenza-core' ),
								size: 'small',
								onClick: () => {
									const n = ids.slice();
									n.splice( i - 1, 0, n.splice( i, 1 )[ 0 ] );
									set( n );
								},
							} ),
						el( C.Button, {
							icon: 'no-alt',
							label: __( 'Remove', 'techvenza-core' ),
							size: 'small',
							isDestructive: true,
							onClick: () =>
								set( ids.filter( ( x ) => x !== id ) ),
						} )
					);
				} )
			),
			( ! single || ! ids.length ) &&
				el(
					Fragment,
					null,
					el( C.TextControl, {
						placeholder: __( 'Search posts…', 'techvenza-core' ),
						value: search,
						onChange: setSearch,
					} ),
					search &&
						el(
							'div',
							{ style: { maxHeight: 180, overflow: 'auto' } },
							found
								.filter( ( p ) => ids.indexOf( p.id ) === -1 )
								.map( ( p ) =>
									el(
										C.Button,
										{
											key: p.id,
											variant: 'tertiary',
											style: {
												display: 'block',
												textAlign: 'left',
												height: 'auto',
												whiteSpace: 'normal',
											},
											onClick: () => {
												set( ids.concat( [ p.id ] ) );
												setSearch( '' );
											},
										},
										titleOf( p )
									)
								)
						)
				)
		);
	}

	function ImageControl( { c, value, onChange } ) {
		const media = useSelect(
			( s ) => ( value ? s( 'core' ).getMedia( value ) : null ),
			[ value ]
		);
		return el(
			C.BaseControl,
			{ label: c.label, help: c.help, id: 'tv-img' },
			media &&
				el( 'img', {
					src: media.source_url,
					alt: '',
					style: {
						maxWidth: '100%',
						borderRadius: 6,
						display: 'block',
						marginBottom: 8,
					},
				} ),
			el(
				MediaUploadCheck,
				null,
				el( MediaUpload, {
					allowedTypes: [ 'image' ],
					value,
					onSelect: ( m ) => onChange( m.id ),
					render: ( { open } ) =>
						el(
							C.Button,
							{ variant: 'secondary', onClick: open },
							value
								? __( 'Replace image', 'techvenza-core' )
								: __( 'Choose image', 'techvenza-core' )
						),
				} )
			),
			value &&
				el(
					C.Button,
					{
						variant: 'link',
						isDestructive: true,
						onClick: () => onChange( 0 ),
					},
					__( 'Remove', 'techvenza-core' )
				)
		);
	}

	function RepeaterControl( { c, value, onChange } ) {
		const list = Array.isArray( value ) ? value : [];
		const fields = c.fields || [
			{ key: 'text', label: __( 'Text', 'techvenza-core' ) },
		];
		const scalar = !! c.scalar;
		const update = ( i, patch ) =>
			onChange(
				list.map( ( it, j ) =>
					j === i
						? scalar
							? patch
							: Object.assign( {}, it, patch )
						: it
				)
			);
		const move = ( i, d ) => {
			const n = list.slice();
			const t = n[ i + d ];
			n[ i + d ] = n[ i ];
			n[ i ] = t;
			onChange( n );
		};
		return el(
			C.BaseControl,
			{ label: c.label, help: c.help, id: 'tv-rep' },
			list.map( ( item, i ) =>
				el(
					C.Card,
					{ key: i, size: 'small', style: { marginBottom: 8 } },
					el(
						C.CardBody,
						null,
						scalar
							? el( C.TextareaControl, {
									value: item || '',
									rows: 2,
									onChange: ( v ) => update( i, v ),
							  } )
							: fields.map( ( f ) =>
									renderControl(
										f,
										item[ f.key ],
										( v ) => update( i, { [ f.key ]: v } ),
										f.key
									)
							  ),
						el(
							C.Flex,
							{ justify: 'flex-end' },
							i > 0 &&
								el( C.Button, {
									size: 'small',
									icon: 'arrow-up-alt2',
									label: __( 'Move up', 'techvenza-core' ),
									onClick: () => move( i, -1 ),
								} ),
							i < list.length - 1 &&
								el( C.Button, {
									size: 'small',
									icon: 'arrow-down-alt2',
									label: __( 'Move down', 'techvenza-core' ),
									onClick: () => move( i, 1 ),
								} ),
							el( C.Button, {
								size: 'small',
								isDestructive: true,
								icon: 'trash',
								label: __( 'Remove', 'techvenza-core' ),
								onClick: () =>
									onChange(
										list.filter( ( _, j ) => j !== i )
									),
							} )
						)
					)
				)
			),
			el(
				C.Button,
				{
					variant: 'secondary',
					onClick: () =>
						onChange( list.concat( [ scalar ? '' : {} ] ) ),
				},
				c.addLabel || __( 'Add item', 'techvenza-core' )
			)
		);
	}

	function renderControl( c, value, onChange, key ) {
		const common = { key: key || c.key, label: c.label, help: c.help };
		switch ( c.type ) {
			case 'textarea':
				return el(
					C.TextareaControl,
					Object.assign( common, {
						value: value || '',
						rows: c.rows || 3,
						onChange,
					} )
				);
			case 'toggle':
				return el(
					C.ToggleControl,
					Object.assign( common, { checked: !! value, onChange } )
				);
			case 'number':
				return el(
					C.RangeControl,
					Object.assign( common, {
						value: value || 0,
						min: c.min || 0,
						max: c.max || 24,
						onChange,
					} )
				);
			case 'select':
				return el(
					C.SelectControl,
					Object.assign( common, {
						value: value || '',
						options: c.options,
						onChange,
					} )
				);
			case 'color':
				return el(
					C.BaseControl,
					{
						key: common.key,
						label: c.label,
						help: c.help,
						id: 'tv-col-' + common.key,
					},
					el( C.ColorPalette, {
						value,
						onChange: ( v ) => onChange( v || '' ),
					} )
				);
			case 'icon':
				return el(
					C.SelectControl,
					Object.assign( common, {
						value: value || '',
						options: [ { value: '', label: '—' } ].concat(
							( icons || [] ).map( ( n ) => ( {
								value: n,
								label: n,
							} ) )
						),
						onChange,
					} )
				);
			case 'category':
				return el( CategoryControl, {
					key: common.key,
					c,
					value,
					onChange,
				} );
			case 'categories':
				return el( CategoriesControl, {
					key: common.key,
					c,
					value,
					onChange,
				} );
			case 'post':
				return el( PostsControl, {
					key: common.key,
					c,
					value,
					onChange,
					single: true,
				} );
			case 'posts':
				return el( PostsControl, {
					key: common.key,
					c,
					value,
					onChange,
				} );
			case 'image':
				return el( ImageControl, {
					key: common.key,
					c,
					value,
					onChange,
				} );
			case 'repeater':
				return el( RepeaterControl, {
					key: common.key,
					c,
					value,
					onChange,
				} );
			case 'list':
				return el(
					C.TextControl,
					Object.assign( common, {
						value: ( value || [] ).join( ', ' ),
						onChange: ( v ) =>
							onChange(
								v
									.split( ',' )
									.map( ( x ) => x.trim() )
									.filter( Boolean )
							),
					} )
				);
			case 'url':
				return el(
					C.TextControl,
					Object.assign( common, {
						type: 'text',
						value: value || '',
						placeholder: '/page/ or https://…',
						onChange,
					} )
				);
			case 'datetime':
				return el(
					C.TextControl,
					Object.assign( common, {
						type: 'datetime-local',
						value: value || '',
						onChange,
					} )
				);
			default:
				return el(
					C.TextControl,
					Object.assign( common, { value: value || '', onChange } )
				);
		}
	}

	/* ---------- Block edit component ---------- */
	function makeEdit( name, def ) {
		return function Edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps( {
				className: 'tv-editor-block',
			} );
			const groups = useMemo( () => {
				const out = {};
				( def.controls || [] ).forEach( ( c ) => {
					const g = c.panel || __( 'Settings', 'techvenza-core' );
					( out[ g ] = out[ g ] || [] ).push( c );
				} );
				return out;
			}, [] );
			const inspector = el(
				InspectorControls,
				null,
				def.hint &&
					el(
						C.PanelBody,
						{
							title: __( 'About this block', 'techvenza-core' ),
							initialOpen: false,
						},
						el( 'p', null, def.hint )
					),
				Object.keys( groups ).map( ( g, gi ) =>
					el(
						C.PanelBody,
						{ key: g, title: g, initialOpen: gi === 0 },
						groups[ g ]
							.filter(
								( c ) =>
									! c.showIf ||
									Object.keys( c.showIf ).every(
										( k ) =>
											[]
												.concat( c.showIf[ k ] )
												.indexOf( attributes[ k ] ) !==
											-1
									)
							)
							.map( ( c ) =>
								renderControl(
									c,
									attributes[ c.key ],
									( v ) => setAttributes( { [ c.key ]: v } ),
									c.key
								)
							)
					)
				)
			);
			const title =
				( wp.blocks.getBlockType( name ) || {} ).title || name;

			if ( def.inner ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						'div',
						{
							className: 'tv-editor-placeholder',
							style: { marginBottom: 8 },
						},
						title +
							' — ' +
							( def.inner.label ||
								__( 'content below', 'techvenza-core' ) )
					),
					el( InnerBlocks, {
						template: def.inner.template,
						templateLock: def.inner.lock || false,
					} )
				);
			}
			if ( def.preview === 'placeholder' ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						'div',
						{ className: 'tv-editor-placeholder' },
						el( 'strong', null, title ),
						def.hint ? el( 'div', null, def.hint ) : null
					)
				);
			}
			return el(
				'div',
				blockProps,
				inspector,
				el(
					C.Disabled,
					null,
					el( ServerSideRender, {
						block: name,
						attributes,
						httpMethod: 'POST',
						EmptyResponsePlaceholder: () =>
							el(
								'div',
								{ className: 'tv-editor-placeholder' },
								title +
									': ' +
									__(
										'nothing to show yet (it may depend on content, the current page or settings).',
										'techvenza-core'
									)
							),
					} )
				)
			);
		};
	}

	Object.keys( defs ).forEach( ( name ) => {
		const def = defs[ name ];
		if (
			wp.blocks.getBlockType( name ) &&
			wp.blocks.getBlockType( name ).edit
		) {
			return;
		}
		wp.blocks.registerBlockType( name, {
			edit: makeEdit( name, def ),
			save: def.inner ? () => el( InnerBlocks.Content ) : () => null,
		} );
	} );
} )( window.wp, window.techvenzaBlocks, window.techvenzaIcons );
