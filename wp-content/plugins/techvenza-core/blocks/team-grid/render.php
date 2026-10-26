<?php
/**
 * Team grid (manual members; optionally linked to author profiles).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Support\Render as R;

$tv_cards = '';
foreach ( (array) $attributes['members'] as $tv_m ) {
	if ( empty( $tv_m['name'] ) ) {
		continue;
	}
	$tv_person = array(
		'initials' => UserMeta::initials( $tv_m['name'] ),
		'a1'       => $tv_m['a1'] ?? '#111827',
		'a2'       => $tv_m['a2'] ?? '#7C3AED',
	);
	if ( ! empty( $tv_m['image'] ) ) {
		$tv_person['photo'] = wp_get_attachment_image_url( (int) $tv_m['image'], 'thumbnail' );
	} elseif ( ! empty( $tv_m['user'] ) && ( $tv_au = UserMeta::author( (int) $tv_m['user'] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		$tv_person = $tv_au;
	}
	$tv_name   = esc_html( $tv_m['name'] );
	$tv_url    = ! empty( $tv_m['url'] ) ? R::url( (string) $tv_m['url'] ) : '';
	$tv_cards .= sprintf(
		'<div class="team-card reveal">%s<div><h3>%s</h3><p class="muted" style="font-size:var(--t-meta)">%s</p></div></div>',
		R::avatar( $tv_person, 'lg' ),
		$tv_url ? '<a href="' . esc_url( $tv_url ) . '">' . $tv_name . '</a>' : $tv_name,
		esc_html( $tv_m['role'] ?? '' )
	);
}
if ( ! $tv_cards ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'desc'    => $attributes['description'],
		'accent'  => 'var(--teal)',
	)
);
echo R::section( $tv_head . '<div class="team">' . $tv_cards . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
