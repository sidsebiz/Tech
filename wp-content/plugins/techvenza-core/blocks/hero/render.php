<?php
/**
 * Homepage hero.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( ! Options::section_enabled( 'hero' ) ) {
	return;
}
$tv_a     = $attributes;
$tv_stats = array();
if ( 'auto' === $tv_a['statsMode'] ) {
	$tv_count  = (int) wp_count_posts( 'post' )->publish;
	$tv_topics = count( CategoryMeta::all() );
	$tv_stats  = array(
		/* translators: %d: number of topics */
		array( (string) $tv_count, sprintf( _n( 'in-depth story across %d topics', 'in-depth stories across %d topics', $tv_count, 'techvenza-core' ), $tv_topics ) ),
		array( (string) count( Query::authors() ), __( 'contributing experts and editors', 'techvenza-core' ) ),
		array( '1', __( 'weekly brief, curated by our editors', 'techvenza-core' ) ),
	);
} elseif ( 'manual' === $tv_a['statsMode'] ) {
	foreach ( (array) $tv_a['stats'] as $tv_s ) {
		$tv_stats[] = array( (string) ( $tv_s['value'] ?? '' ), (string) ( $tv_s['label'] ?? '' ) );
	}
}
$tv_id = wp_unique_id( 'heroTitle-' );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'hero tv-block' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="<?php echo esc_attr( $tv_id ); ?>"><div class="container">
	<div class="hero-inner">
		<div>
			<?php
			if ( $tv_a['eyebrow'] ) :
				?>
				<span class="eyebrow"><?php echo esc_html( $tv_a['eyebrow'] ); ?></span><?php endif; ?>
			<h1 class="display mt-5" id="<?php echo esc_attr( $tv_id ); ?>"><?php echo esc_html( $tv_a['heading'] ); ?>
				<?php
				if ( $tv_a['gradientText'] ) :
					?>
					<span class="gradient-text"><?php echo esc_html( $tv_a['gradientText'] ); ?></span><?php endif; ?></h1>
			<?php
			if ( $tv_a['lead'] ) :
				?>
				<p class="lead"><?php echo esc_html( $tv_a['lead'] ); ?></p><?php endif; ?>
			<div class="hero-actions">
				<?php
				if ( $tv_a['primaryLabel'] ) :
					?>
					<a class="btn btn-primary btn-lg" href="<?php echo esc_url( R::url( $tv_a['primaryUrl'] ) ); ?>"><?php echo esc_html( $tv_a['primaryLabel'] ); ?> <?php echo R::icon( 'arrowRight' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
				<?php
				if ( $tv_a['secondaryLabel'] ) :
					?>
					<a class="btn btn-secondary btn-lg" href="<?php echo esc_url( R::url( $tv_a['secondaryUrl'] ) ); ?>"><?php echo esc_html( $tv_a['secondaryLabel'] ); ?></a><?php endif; ?>
			</div>
		</div>
		<?php if ( $tv_stats ) : ?>
		<div class="hero-side">
			<?php foreach ( $tv_stats as [ $tv_v, $tv_l ] ) : ?>
				<div class="hero-stat"><strong><?php echo esc_html( $tv_v ); ?></strong><span><?php echo esc_html( $tv_l ); ?></span></div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $tv_a['showTopics'] ) && $tv_a['topics'] ) : ?>
		<div class="hero-topics" aria-label="<?php esc_attr_e( 'Popular topics', 'techvenza-core' ); ?>">
			<?php
			foreach ( (array) $tv_a['topics'] as $tv_slug ) {
				echo R::chip( CategoryMeta::get( (string) $tv_slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
	<?php endif; ?>
</div></section>
