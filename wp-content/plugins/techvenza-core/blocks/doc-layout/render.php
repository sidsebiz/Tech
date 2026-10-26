<?php
/**
 * Document layout (guidelines, policies): sticky section nav built from H2s + prose body.
 *
 * @package Techvenza\Core
 * @var array  $attributes
 * @var string $content
 */

use Techvenza\Core\Support\Render as R;

$tv_html  = (string) $content;
$tv_heads = R::headings( $tv_html, array( 'h2' ) );
$tv_nav   = implode( '', array_map( static fn( $h ) => sprintf( '<a href="#%s">%s</a>', esc_attr( $h['id'] ), esc_html( $h['text'] ) ), $tv_heads ) );
$tv_draft = '';
if ( ! empty( $attributes['draftBanner'] ) ) {
	$tv_draft = '<div class="form-note warn mb-6">' . R::icon( 'alert' ) . '<span><b>' . esc_html__( 'Draft for legal review.', 'techvenza-core' ) . '</b> ' . esc_html__( 'This text is a starting point and must be reviewed by qualified counsel before it is relied on.', 'techvenza-core' ) . '</span></div>';
}
$tv_inner = sprintf(
	'<div class="doc">%s<div class="prose" style="--fs:1.0625rem;max-width:var(--container-narrow)">%s%s</div></div>',
	$tv_nav ? '<nav class="doc-nav" aria-label="' . esc_attr__( 'Sections', 'techvenza-core' ) . '" data-toc>' . $tv_nav . '</nav>' : '',
	$tv_draft,
	$tv_html
);
echo R::section( $tv_inner, array_merge( $attributes, array( 'spacing' => 'none-top' === ( $attributes['spacing'] ?? '' ) || 'default' === ( $attributes['spacing'] ?? 'default' ) ? 'none-top' : $attributes['spacing'] ) ), 'tight' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
