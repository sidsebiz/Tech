<?php
/**
 * Demo content seeder: imports the prototype's categories, authors, articles, workflow items,
 * applications, subscribers, campaigns, redirects, notifications, pages and menus.
 *
 * Every created object is flagged (post/term/user meta `tv_seed`, subscribers.is_seed) so
 * `wp techvenza reset --yes` removes exactly what was seeded.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Cli;

use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Database\History;
use Techvenza\Core\Database\Notifications;
use Techvenza\Core\Database\Schema;
use Techvenza\Core\Database\Views;
use Techvenza\Core\Notifications\Notifier;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Workflow\Roles;
use Techvenza\Core\Workflow\Workflow;

/**
 * Seeder.
 */
final class Seeder {

	/**
	 * Logger callback (WP-CLI line/log).
	 *
	 * @var callable
	 */
	private $log;

	/**
	 * Seed data.
	 *
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * Created users by slug.
	 *
	 * @var array<string,int>
	 */
	private array $users = array();

	/**
	 * Attachment IDs by image key.
	 *
	 * @var array<string,int>
	 */
	private array $images = array();

	/**
	 * Post IDs by prototype slug.
	 *
	 * @var array<string,int>
	 */
	private array $posts = array();

	/**
	 * Demo credentials to print once.
	 *
	 * @var array<int,array<string,string>>
	 */
	public array $credentials = array();


	/**
	 * Constructor.
	 *
	 * @param callable|null $log Logger.
	 */
	public function __construct( ?callable $log = null ) {
		$this->log  = $log ? $log : static function ( string $m ): void {};
		$json       = (string) file_get_contents( TECHVENZA_CORE_DIR . 'data/seed.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->data = (array) json_decode( $json, true );
	}

	/**
	 * Log helper.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		( $this->log )( $message );
	}

	/**
	 * Run everything.
	 *
	 * @param array<string,bool> $opts skip-images.
	 */
	public function run( array $opts = array() ): void {
		Notifier::$muted = true;
		Schema::install();
		Roles::install();
		$this->settings();
		$this->categories();
		$this->authors();
		if ( empty( $opts['skip-images'] ) ) {
			$this->images();
		}
		$this->articles();
		$this->workflow();
		$this->applications();
		$this->subscribers();
		$this->campaigns();
		$this->redirects();
		$pages = $this->pages();
		$this->menus( $pages );
		$this->notifications();
		$this->demo_users();
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules();
		Views::aggregate();
		wp_cache_flush();
		Notifier::$muted = false;
		$this->log( 'Seed complete.' );
	}

	/**
	 * Flag helper for posts.
	 *
	 * @param int $id Post ID.
	 */
	private function flag_post( int $id ): void {
		update_post_meta( $id, 'tv_seed', 1 );
	}

	/**
	 * Sensible default settings for a fresh install.
	 */
	private function settings(): void {
		if ( 'techvenza' !== get_stylesheet() && wp_get_theme( 'techvenza' )->exists() ) {
			switch_theme( 'techvenza' );
		}
		update_option( 'blogname', 'Techvenza' );
		update_option( 'date_format', 'j M Y' );
		update_option( 'posts_per_page', 9 );
		$raw = Options::raw();
		if ( empty( $raw['social_linkedin'] ) ) {
			Options::update( array( 'social_rss' => true ) );
		}
	}

	/**
	 * Categories with meta and subcategories.
	 */
	private function categories(): void {
		$home = array( 'ai', 'business', 'software', 'manufacturing', 'cybersecurity', 'cloud', 'data', 'startups' );
		foreach ( (array) $this->data['categories'] as $i => $c ) {
			$term = get_term_by( 'slug', $c['slug'], 'category' );
			$id   = $term ? $term->term_id : 0;
			if ( ! $id ) {
				$res = wp_insert_term(
					$c['name'],
					'category',
					array(
						'slug'        => $c['slug'],
						'description' => $c['intro'],
					)
				);
				if ( is_wp_error( $res ) ) {
					continue;
				}
				$id = (int) $res['term_id'];
				update_term_meta( $id, 'tv_seed', 1 );
			}
			$meta = array(
				'tv_short'           => $c['short'],
				'tv_color'           => $c['color'],
				'tv_ink'             => $c['ink'],
				'tv_soft'            => $c['soft'],
				'tv_icon'            => $c['icon'],
				'tv_intro'           => $c['intro'],
				'tv_watch'           => $c['watch'],
				'tv_seo_title'       => $c['name'] . ' — Techvenza',
				'tv_seo_description' => $c['intro'],
				'tv_show_on_home'    => in_array( $c['slug'], $home, true ) ? 1 : 0,
				'tv_order'           => $i + 1,
			);
			foreach ( $meta as $k => $v ) {
				update_term_meta( $id, $k, $v );
			}
			foreach ( (array) $c['sub'] as $sub ) {
				$slug = sanitize_title( $c['slug'] . '-' . $sub );
				if ( ! get_term_by( 'slug', $slug, 'category' ) ) {
					$res = wp_insert_term(
						$sub,
						'category',
						array(
							'slug'   => $slug,
							'parent' => $id,
						)
					);
					if ( ! is_wp_error( $res ) ) {
						update_term_meta( (int) $res['term_id'], 'tv_seed', 1 );
					}
				}
			}
		}
		// Remove WordPress's sample post and page on fresh installs.
		foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as [ $slug, $type ] ) {
			$sample = get_page_by_path( $slug, OBJECT, $type );
			if ( $sample && ! get_post_meta( $sample->ID, 'tv_seed', true ) ) {
				wp_delete_post( $sample->ID, true );
			}
		}
		// Rename "Uncategorized" so the default category reads well.
		$default = get_term( (int) get_option( 'default_category' ), 'category' );
		if ( $default instanceof \WP_Term && 'uncategorized' === $default->slug ) {
			wp_update_term(
				$default->term_id,
				'category',
				array(
					'name' => 'General',
					'slug' => 'general',
				)
			);
		}
		$this->log( 'Categories: ' . count( (array) $this->data['categories'] ) );
	}

	/**
	 * Create or update a user.
	 *
	 * @param string              $login    Login.
	 * @param string              $email    Email.
	 * @param string              $name     Display name.
	 * @param string              $role     Role.
	 * @param array<string,mixed> $meta     Meta.
	 * @param string              $bio      Bio.
	 * @param bool                $remember Print credentials.
	 */
	private function user( string $login, string $email, string $name, string $role, array $meta = array(), string $bio = '', bool $remember = false ): int {
		$existing = get_user_by( 'login', $login );
		$parts    = explode( ' ', $name, 2 );
		$password = wp_generate_password( 20, true, false );
		$args     = array(
			'user_login'   => $login,
			'user_email'   => $email,
			'display_name' => $name,
			'nickname'     => $name,
			'first_name'   => $parts[0],
			'last_name'    => $parts[1] ?? '',
			'description'  => $bio,
			'role'         => $role,
		);
		if ( $existing ) {
			$args['ID'] = $existing->ID;
			$id         = wp_update_user( $args );
			if ( $remember ) {
				wp_set_password( $password, $existing->ID );
			}
		} else {
			$args['user_pass'] = $password;
			$id                = wp_insert_user( $args );
		}
		if ( is_wp_error( $id ) ) {
			$this->log( 'User error ' . $login . ': ' . $id->get_error_message() );
			return 0;
		}
		update_user_meta( (int) $id, 'tv_seed', 1 );
		foreach ( $meta as $k => $v ) {
			update_user_meta( (int) $id, $k, $v );
		}
		if ( $remember ) {
			$this->credentials[] = array(
				'role'     => Roles::labels()[ $role ] ?? $role,
				'login'    => $login,
				'email'    => $email,
				'password' => $password,
			);
		}
		return (int) $id;
	}

	/**
	 * The eight illustrative authors.
	 */
	private function authors(): void {
		foreach ( (array) $this->data['authors'] as $a ) {
			$role                      = 'Editor' === $a['role'] ? 'tv_editor' : 'tv_author';
			$this->users[ $a['slug'] ] = $this->user(
				$a['slug'],
				$a['slug'] . '@example.com',
				$a['name'],
				$role,
				array(
					'tv_title'      => $a['title'],
					'tv_expertise'  => $a['expertise'],
					'tv_categories' => $a['cats'],
					'tv_staff'      => (bool) $a['staff'],
					'tv_verified'   => (string) ( $a['verified'] ?? '' ),
					'tv_website'    => $a['website'],
					'tv_linkedin'   => $a['linkedin'],
					'tv_featured'   => (bool) $a['featured'],
					'tv_visible'    => true,
				),
				$a['bio'],
				in_array( $a['slug'], array( 'priya-natarajan', 'ananya-rao' ), true )
			);
			if ( $this->users[ $a['slug'] ] ) {
				wp_update_user(
					array(
						'ID'              => $this->users[ $a['slug'] ],
						'user_registered' => $a['joined'] . ' 09:00:00',
						'user_nicename'   => $a['slug'],
					)
				);
			}
		}
		$this->log( 'Authors: ' . count( $this->users ) );
	}

	/**
	 * Remaining demo accounts (reader, applicant, managing editor, administrator).
	 */
	private function demo_users(): void {
		$this->user(
			'demo-reader',
			'reader@example.com',
			'Jordan Lee',
			'subscriber',
			array(
				'tv_topics'    => array( 'ai', 'software', 'manufacturing' ),
				'tv_bookmarks' => array_values( array_filter( array( $this->posts['the-agentic-enterprise'] ?? 0, $this->posts['platform-engineering-paved-roads'] ?? 0, $this->posts['zero-trust-12-month-roadmap'] ?? 0 ) ) ),
			),
			'',
			true
		);
		$reader = get_user_by( 'login', 'demo-reader' );
		if ( $reader ) {
			foreach ( array( 'lights-out-factory-myth', 'leading-through-ambiguity', 'evaluating-llm-applications' ) as $slug ) {
				if ( isset( $this->posts[ $slug ] ) ) {
					History::touch( $reader->ID, $this->posts[ $slug ] );
				}
			}
		}
		$this->user( 'demo-applicant', 'applicant@example.com', 'Ravi Menon', 'tv_applicant', array(), '', true );
		$this->user(
			'sofia-lindqvist',
			'sofia-lindqvist@example.com',
			'Sofia Lindqvist',
			'tv_managing_editor',
			array(
				'tv_title' => 'Managing Editor',
				'tv_staff' => true,
			),
			'Sofia leads Techvenza’s editorial team (illustrative profile).',
			true
		);
		$this->user(
			'james-whitaker',
			'james-whitaker@example.com',
			'James Whitaker',
			'tv_editor',
			array(
				'tv_title'   => 'Standards Editor',
				'tv_staff'   => true,
				'tv_visible' => false,
			),
			'James oversees standards and corrections (illustrative profile).'
		);
		$this->user( 'demo-admin', 'admin-demo@example.com', 'Demo Administrator', 'administrator', array(), '', true );
	}

	/**
	 * Sideload cover images from the prototype's Unsplash URLs.
	 */
	private function images(): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$alts = array();
		foreach ( (array) $this->data['articles'] as $a ) {
			$alts[ $a['image'] ] = $a['imageAlt'];
		}
		foreach ( (array) $this->data['images'] as $key => $id ) {
			$existing = get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'meta_key'    => 'tv_image_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'      => 'ids',
				)
			);
			if ( $existing ) {
				$this->images[ $key ] = (int) $existing[0];
				continue;
			}
			$url = 'https://images.unsplash.com/photo-' . $id . '?auto=format&fit=crop&w=1800&q=75&fm=jpg';
			$tmp = download_url( $url, 30 );
			if ( is_wp_error( $tmp ) ) {
				$this->log( "Image {$key}: " . $tmp->get_error_message() . ' (continuing with gradient placeholder)' );
				continue;
			}
			$att = media_handle_sideload(
				array(
					'name'     => sanitize_title( $key ) . '.jpg',
					'tmp_name' => $tmp,
				),
				0,
				$alts[ $key ] ?? ''
			);
			if ( is_wp_error( $att ) ) {
				wp_delete_file( $tmp );
				$this->log( "Image {$key}: " . $att->get_error_message() );
				continue;
			}
			update_post_meta( $att, '_wp_attachment_image_alt', $alts[ $key ] ?? ucfirst( $key ) );
			update_post_meta( $att, 'tv_image_key', $key );
			wp_update_post(
				array(
					'ID'           => $att,
					'post_excerpt' => __( 'Photo: Unsplash (illustrative).', 'techvenza-core' ),
				)
			);
			$this->flag_post( (int) $att );
			$this->images[ $key ] = (int) $att;
		}
		$this->log( 'Images: ' . count( $this->images ) );
	}

	/**
	 * Generated article body from key points (port of the prototype's genBody()).
	 *
	 * @param array<string,mixed> $a Article seed.
	 */
	private function gen_body( array $a ): string {
		$c      = $this->category( $a['category'] );
		$au     = $this->author_seed( $a['author'] );
		$first  = strtok( $au['name'], ' ' );
		$points = (array) $a['points'];
		$tags   = implode(
			', ',
			array_map(
				static fn( $t ) => sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/explore/?tag=' . sanitize_title( $t ) ) ), esc_html( $t ) ),
				(array) $a['tags']
			)
		);
		$html   = '<p>' . esc_html( $a['excerpt'] ) . '</p>'
			. '<p>' . esc_html( $c['intro'] ) . ' In this piece, ' . esc_html( $first ) . ' looks at what teams are learning in practice and where the common pitfalls lie.</p>'
			. '<h2 id="why-it-matters">Why it matters</h2><p>' . esc_html( $c['watch'] ) . ' For practitioners, the implication is straightforward: decisions made early — about scope, ownership and measurement — shape outcomes far more than tooling choices made later.</p>'
			. '<h2 id="key-takeaways">Key takeaways</h2><ul>' . implode( '', array_map( static fn( $p ) => '<li>' . esc_html( $p ) . '</li>', $points ) ) . '</ul>'
			. '<blockquote>' . esc_html( $points[0] ?? $a['excerpt'] ) . '<cite>— ' . esc_html( $au['name'] . ', ' . $au['title'] ) . '</cite></blockquote>'
			. '<h3 id="in-practice">Putting it into practice</h3><p>Start small. Pick one team, one workflow or one decision where the change can be measured within a quarter, and define in advance what success looks like. Related topics worth exploring: ' . $tags . '.</p>'
			. '<h2 id="what-to-watch">What to watch next</h2><p>Expect the practices described here to mature quickly. The organisations that benefit most will be those that treat them as ongoing capabilities rather than one-off projects.</p>';
		return BlockConverter::convert( $html );
	}

	/**
	 * Category seed by slug.
	 *
	 * @param string $slug Slug.
	 * @return array<string,mixed>
	 */
	private function category( string $slug ): array {
		foreach ( (array) $this->data['categories'] as $c ) {
			if ( $c['slug'] === $slug ) {
				return $c;
			}
		}
		return array(
			'intro' => '',
			'watch' => '',
			'name'  => $slug,
		);
	}

	/**
	 * Author seed by slug.
	 *
	 * @param string $slug Slug.
	 * @return array<string,mixed>
	 */
	private function author_seed( string $slug ): array {
		foreach ( (array) $this->data['authors'] as $a ) {
			if ( $a['slug'] === $slug ) {
				return $a;
			}
		}
		return array(
			'name'  => $slug,
			'title' => '',
		);
	}

	/**
	 * Insert or update an article.
	 *
	 * @param array<string,mixed> $a       Seed.
	 * @param string              $status  Post status.
	 * @param string              $content Block content.
	 * @param string              $date    Y-m-d.
	 */
	private function upsert_post( array $a, string $status, string $content, string $date ): int {
		$existing = get_page_by_path( $a['slug'], OBJECT, 'post' );
		$args     = array(
			'post_type'     => 'post',
			'post_name'     => $a['slug'],
			'post_title'    => $a['title'],
			'post_excerpt'  => $a['excerpt'],
			'post_content'  => $content,
			'post_status'   => $status,
			'post_author'   => $this->users[ $a['author'] ] ?? 1,
			'post_date'     => $date . ' 09:00:00',
			'post_date_gmt' => get_gmt_from_date( $date . ' 09:00:00' ),
			'edit_date'     => true,
		);
		$cat      = get_term_by( 'slug', $a['category'], 'category' );
		if ( $cat ) {
			$args['post_category'] = array( $cat->term_id );
		}
		if ( $existing ) {
			$args['ID'] = $existing->ID;
		}
		// Bypass the status guard (seeding sets final states directly).
		$id = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $id ) ) {
			$this->log( 'Post error ' . $a['slug'] . ': ' . $id->get_error_message() );
			return 0;
		}
		global $wpdb;
		// Force the exact status (wp_insert_post_data guard may have kept a workflow status).
		$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$this->flag_post( (int) $id );
		if ( ! empty( $a['tags'] ) ) {
			wp_set_post_tags( (int) $id, (array) $a['tags'] );
		}
		if ( ! empty( $a['image'] ) && isset( $this->images[ $a['image'] ] ) ) {
			set_post_thumbnail( (int) $id, $this->images[ $a['image'] ] );
		}
		return (int) $id;
	}

	/**
	 * The 26 published articles.
	 */
	private function articles(): void {
		$bodies = (array) $this->data['bodies'];
		foreach ( (array) $this->data['articles'] as $a ) {
			$content = isset( $bodies[ $a['slug'] ] ) ? BlockConverter::convert( $bodies[ $a['slug'] ] ) : $this->gen_body( $a );
			$id      = $this->upsert_post( $a, 'publish', $content, $a['publishedAt'] );
			if ( ! $id ) {
				continue;
			}
			$this->posts[ $a['slug'] ] = $id;
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_modified'     => $a['updatedAt'] . ' 10:00:00',
					'post_modified_gmt' => get_gmt_from_date( $a['updatedAt'] . ' 10:00:00' ),
				),
				array( 'ID' => $id )
			);
			$meta = array(
				'tv_article_type'        => $a['type'],
				'tv_guide_level'         => $a['level'],
				'tv_key_points'          => $a['points'],
				'tv_is_featured'         => $a['featured'],
				'tv_is_trending_manual'  => false,
				'tv_is_editors_pick'     => $a['pick'],
				'tv_reading_time'        => $a['readingTime'],
				'tv_reading_time_manual' => true,
				'tv_seo_title'           => $a['title'],
				'tv_seo_description'     => $a['excerpt'],
			);
			foreach ( $meta as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
			$this->views( $id, (int) $a['views'], (bool) $a['trending'] );
			Workflow::log( $id, 'draft', 'submitted', 'submit', $this->users[ $a['author'] ] ?? 0, '', '', $a['publishedAt'] . ' 08:00:00' );
			Workflow::log( $id, 'approved', 'publish', 'publish', $this->users['ananya-rao'] ?? 0, '', '', $a['publishedAt'] . ' 09:00:00' );
		}
		$this->log( 'Published articles: ' . count( $this->posts ) );
	}

	/**
	 * Synthesise 30 days of daily views so trending periods differ realistically.
	 *
	 * @param int  $post_id  Post.
	 * @param int  $total    Total views from the prototype.
	 * @param bool $trending Prototype "trending" flag (boost recent days).
	 */
	private function views( int $post_id, int $total, bool $trending ): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'post_views' ), array( 'post_id' => $post_id ), array( '%d' ) );
		$recent = (int) round( $total * ( $trending ? 0.45 : 0.18 ) );
		$older  = $total - $recent;
		$seed   = $post_id * 7919;
		for ( $d = 0; $d < 30; $d++ ) {
			$weight = $d < 7 ? $recent / 7 : $older / 23;
			$jitter = 0.75 + ( ( ( $seed + $d * 104729 ) % 50 ) / 100 );
			$date   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$d} days" ) );
			Views::set_day( $post_id, $date, max( 1, (int) round( $weight * $jitter ) ) );
		}
		update_post_meta( $post_id, 'tv_view_count', $total );
	}

	/**
	 * The 11 workflow items in their statuses, with feedback.
	 */
	private function workflow(): void {
		$map      = array(
			'scheduled' => 'future',
		);
		$feedback = (array) $this->data['feedback'];
		$editors  = array(
			'Ananya Rao'      => 'ananya-rao',
			'Sofia Lindqvist' => 'sofia-lindqvist',
			'James Whitaker'  => 'james-whitaker',
		);
		foreach ( (array) $this->data['workflow'] as $w ) {
			$status = $map[ $w['status'] ] ?? $w['status'];
			$a      = array_merge(
				$w,
				array(
					'tags'   => array(),
					'points' => array(),
				)
			);
			$date   = 'future' === $status && $w['scheduledFor'] ? $w['scheduledFor'] : $w['date'];
			$body   = BlockConverter::convert( '<p>' . esc_html( $w['excerpt'] ) . '</p><h2>Introduction</h2><p>This is an illustrative submission body used to demonstrate the editorial workflow. The author would continue the article here with evidence, examples and practical guidance for readers.</p>' );
			$id     = $this->upsert_post( $a, $status, $body, $date );
			if ( ! $id ) {
				continue;
			}
			$this->posts[ $w['slug'] ] = $id;
			if ( $w['editor'] ) {
				$editor = get_user_by( 'login', $editors[ $w['editor'] ] ?? '' );
				if ( $editor ) {
					update_post_meta( $id, 'tv_assigned_editor', $editor->ID );
				}
			}
			update_post_meta( $id, 'tv_article_type', 'analysis' );
			update_post_meta( $id, 'tv_submitted_at', $w['date'] . ' 09:00:00' );
			delete_post_meta( $id, 'tv_timeline' );
			$author = $this->users[ $w['author'] ] ?? 0;
			if ( 'draft' !== $status ) {
				Workflow::log( $id, 'draft', 'submitted', 'submit', $author, '', '', $w['date'] . ' 09:00:00' );
			}
			foreach ( (array) ( $feedback[ $w['slug'] ] ?? array() ) as $f ) {
				$is_author = 'Author' === $f['by'];
				$user      = $is_author ? $author : (int) ( get_user_by( 'login', $editors[ $f['by'] ] ?? '' )->ID ?? 0 );
				Workflow::log( $id, $is_author ? 'changes_requested' : 'in_review', $is_author ? 'resubmitted' : ( 'rejected' === $status ? 'rejected' : 'changes_requested' ), $is_author ? 'resubmit' : ( 'rejected' === $status ? 'reject' : 'request' ), $user, $f['text'], '', $f['at'] . ' 11:00:00' );
			}
			if ( in_array( $status, array( 'in_review', 'approved', 'future' ), true ) ) {
				Workflow::log( $id, 'submitted', 'in_review', 'start', (int) get_post_meta( $id, 'tv_assigned_editor', true ), '', '', $w['date'] . ' 12:00:00' );
			}
			if ( in_array( $status, array( 'approved', 'future' ), true ) ) {
				Workflow::log( $id, 'in_review', 'approved', 'approve', (int) get_post_meta( $id, 'tv_assigned_editor', true ), '', '', $w['date'] . ' 15:00:00' );
			}
			if ( 'future' === $status ) {
				Workflow::log( $id, 'approved', 'future', 'schedule', (int) ( get_user_by( 'login', 'sofia-lindqvist' )->ID ?? 0 ), '', $date, $w['date'] . ' 16:00:00' );
			}
		}
		$this->log( 'Workflow items: ' . count( (array) $this->data['workflow'] ) );
	}

	/**
	 * Author applications.
	 */
	private function applications(): void {
		foreach ( (array) $this->data['applications'] as $app ) {
			$existing = get_posts(
				array(
					'post_type'   => PostTypes::APPLICATION,
					'post_status' => 'any',
					'meta_key'    => 'tv_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $app['email'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'      => 'ids',
				)
			);
			$id       = wp_insert_post(
				array(
					'ID'          => $existing ? $existing[0] : 0,
					'post_type'   => PostTypes::APPLICATION,
					'post_status' => 'private',
					'post_title'  => $app['name'],
					'post_date'   => $app['submitted'] . ' 10:00:00',
				)
			);
			if ( ! $id ) {
				continue;
			}
			$meta = array(
				'tv_email'      => $app['email'],
				'tv_title'      => $app['title'],
				'tv_org'        => $app['org'],
				'tv_expertise'  => $app['expertise'],
				'tv_categories' => $app['cats'],
				'tv_bio'        => $app['bio'],
				'tv_link'       => $app['links'][0] ?? '',
				'tv_samples'    => $app['samples'],
				'tv_reason'     => $app['reason'],
				'tv_status'     => $app['status'],
				'tv_reference'  => 'TV-' . strtoupper( substr( md5( $app['email'] ), 0, 6 ) ),
				'tv_consent_at' => $app['submitted'] . ' 10:00:00',
			);
			foreach ( $meta as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
			$this->flag_post( $id );
		}
		$this->log( 'Applications: ' . count( (array) $this->data['applications'] ) );
	}

	/**
	 * Sample subscribers (example.com addresses).
	 */
	private function subscribers(): void {
		global $wpdb;
		$table = Schema::table( 'subscribers' );
		foreach ( (array) $this->data['subscribers'] as $s ) {
			$wpdb->delete( $table, array( 'email' => $s['email'] ), array( '%s' ) );
			$wpdb->insert(
				$table,
				array(
					'email'             => $s['email'],
					'topics'            => wp_json_encode( $s['topics'] ),
					'frequency'         => 'weekly',
					'status'            => 'active' === $s['status'] ? 'confirmed' : 'unsubscribed',
					'consent_at'        => $s['joined'] . ' 09:00:00',
					'consent_text'      => 'Seeded demo subscriber.',
					'confirmed_at'      => $s['joined'] . ' 09:05:00',
					'unsubscribed_at'   => 'active' === $s['status'] ? null : $s['joined'] . ' 12:00:00',
					'confirm_token'     => wp_generate_password( 40, false ),
					'unsubscribe_token' => wp_generate_password( 40, false ),
					'source'            => 'seed',
					'is_seed'           => 1,
					'created_at'        => $s['joined'] . ' 09:00:00',
					'updated_at'        => $s['joined'] . ' 09:05:00',
				)
			);
		}
		$this->log( 'Subscribers: ' . count( (array) $this->data['subscribers'] ) );
	}

	/**
	 * Newsletter campaigns.
	 */
	private function campaigns(): void {
		$posts = array_values( array_filter( array( $this->posts['the-agentic-enterprise'] ?? 0, $this->posts['platform-engineering-paved-roads'] ?? 0, $this->posts['zero-trust-12-month-roadmap'] ?? 0 ) ) );
		foreach ( (array) $this->data['campaigns'] as $c ) {
			$existing = get_posts(
				array(
					'post_type'   => PostTypes::CAMPAIGN,
					'post_status' => 'any',
					'title'       => $c['name'],
					'fields'      => 'ids',
				)
			);
			$id       = wp_insert_post(
				array(
					'ID'          => $existing ? (int) $existing[0] : 0,
					'post_type'   => PostTypes::CAMPAIGN,
					'post_status' => 'private',
					'post_title'  => $c['name'],
				)
			);
			if ( ! $id ) {
				continue;
			}
			update_post_meta( $id, 'tv_subject', $c['subject'] );
			update_post_meta( $id, 'tv_preheader', 'Three stories our editors think you should read this week.' );
			update_post_meta( $id, 'tv_intro', 'This week we look at how software that takes actions changes enterprise design, and what platform teams can learn from product thinking.' );
			update_post_meta( $id, 'tv_posts', $posts );
			update_post_meta( $id, 'tv_audience', 'All subscribers' === $c['audience'] ? 'all' : 'ai' );
			update_post_meta( $id, 'tv_status', $c['status'] );
			update_post_meta( $id, 'tv_send_at', '—' === $c['date'] ? '' : $c['date'] . ' 08:00:00' );
			if ( 'sent' === $c['status'] ) {
				update_post_meta( $id, 'tv_sent_at', $c['date'] . ' 08:00:00' );
				update_post_meta( $id, 'tv_stats', array( 'note' => 'Illustrative seeded campaign — no email was sent.' ) );
			}
			$this->flag_post( $id );
		}
	}

	/**
	 * Redirects (built-in CPT only when the Redirection plugin is not active).
	 */
	private function redirects(): void {
		if ( PostTypes::redirection_active() ) {
			$this->log( 'Redirection plugin active — add redirects there.' );
			return;
		}
		foreach ( (array) $this->data['redirects'] as $r ) {
			$id = wp_insert_post(
				array(
					'post_type'   => PostTypes::REDIRECT,
					'post_status' => 'publish',
					'post_title'  => $r['from'] . ' → ' . $r['to'],
				)
			);
			if ( $id ) {
				update_post_meta( $id, 'tv_from', $r['from'] );
				update_post_meta( $id, 'tv_to', $r['to'] );
				update_post_meta( $id, 'tv_code', (int) $r['type'] );
				update_post_meta( $id, 'tv_hits', 0 );
				$this->flag_post( $id );
			}
		}
	}

	/**
	 * In-app notifications for the demo author (Priya).
	 */
	private function notifications(): void {
		$user = $this->users['priya-natarajan'] ?? 0;
		if ( ! $user ) {
			return;
		}
		Notifications::delete_user( $user );
		$portal = home_url( '/author-portal/' );
		foreach ( array_reverse( (array) $this->data['notifications'] ) as $n ) {
			$link = $n['link'];
			if ( preg_match( '#/portal/submission/(\d+)#', $link, $m ) ) {
				$slug = '';
				foreach ( (array) $this->data['workflow'] as $w ) {
					if ( (int) $w['id'] === (int) $m[1] ) {
						$slug = $w['slug'];
					}
				}
				$link = add_query_arg(
					array(
						'view' => 'submission',
						'id'   => $this->posts[ $slug ] ?? 0,
					),
					$portal
				);
			} elseif ( preg_match( '#/article/([a-z0-9-]+)#', $link, $m ) ) {
				$link = get_permalink( $this->posts[ $m[1] ] ?? 0 );
			} elseif ( str_contains( $link, '/portal/profile' ) ) {
				$link = add_query_arg( 'view', 'profile', $portal );
			} else {
				$link = home_url( '/editorial-guidelines/' );
			}
			$id = Notifications::add( $user, $n['type'], $n['title'], $n['text'], (string) $link, 0, $n['at'] . ' 10:00:00' );
			if ( ! $n['unread'] ) {
				Notifications::mark_read( $user, $id );
			}
		}
	}

	/**
	 * Block markup helpers for page content.
	 *
	 * @param string              $name  Block name (techvenza/… or core/…).
	 * @param array<string,mixed> $attrs Attributes.
	 * @param string              $inner Inner blocks markup (for container blocks).
	 */
	private static function b( string $name, array $attrs = array(), string $inner = '' ): string {
		$name = str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
		$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
		return '' === $inner ? "<!-- wp:{$name}{$json} /-->" : "<!-- wp:{$name}{$json} -->\n{$inner}\n<!-- /wp:{$name} -->";
	}

	/**
	 * Panel group with an eyebrow label and a statement.
	 *
	 * @param string $label Eyebrow.
	 * @param string $text  Statement.
	 */
	private static function panel( string $label, string $text ): string {
		return self::b( 'core/group', array( 'className' => 'is-style-panel' ), '<div class="wp-block-group is-style-panel">' . self::b( 'core/paragraph', array( 'className' => 'eyebrow' ), '<p class="eyebrow">' . esc_html( $label ) . '</p>' ) . self::b( 'core/paragraph', array(), '<p>' . esc_html( $text ) . '</p>' ) . '</div>' );
	}

	/**
	 * Core buttons.
	 *
	 * @param array<int,array{0:string,1:string,2:string}> $items Label, path, style (fill|secondary).
	 */
	private static function buttons( array $items ): string {
		$html = '';
		foreach ( $items as [ $label, $url, $style ] ) {
			$cls   = 'fill' === $style ? '' : 'is-style-' . $style;
			$html .= self::b( 'core/button', $cls ? array( 'className' => $cls ) : array(), sprintf( '<div class="wp-block-button%s"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div>', $cls ? ' ' . $cls : '', esc_url( $url ), esc_html( $label ) ) );
		}
		return self::b( 'core/buttons', array(), '<div class="wp-block-buttons">' . $html . '</div>' );
	}

	/**
	 * FAQ items shared by Become an Author and the guidelines.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	private static function author_faq(): array {
		return array(
			array(
				'q' => 'Who can write for Techvenza?',
				'a' => 'Practitioners with first-hand experience in a field we cover: engineers, operators, researchers, product and business leaders. You do not need prior publishing experience — our editors help with structure and clarity.',
			),
			array(
				'q' => 'Is publication guaranteed once I am accepted?',
				'a' => 'No. Acceptance gives you access to the author portal. Every article is reviewed individually, and publication is always subject to editorial approval.',
			),
			array(
				'q' => 'Are contributors paid?',
				'a' => 'The current contributor programme is unpaid. If paid commissions are introduced, terms will be published on this page and agreed in writing before work begins.',
			),
			array(
				'q' => 'Can I include links to my company or product?',
				'a' => 'Links must serve the reader. Relevant, non-promotional references may be included where they support the article. We do not sell links, and promotional links will be removed.',
			),
			array(
				'q' => 'Can I use AI tools when writing?',
				'a' => 'You may use AI tools for research support or editing, but you must disclose material use and remain responsible for accuracy and originality. See the editorial guidelines for details.',
			),
			array(
				'q' => 'How long does review take?',
				'a' => 'Typically one to two weeks for a first response. Timelines vary with volume and the revisions needed.',
			),
		);
	}

	/**
	 * Document page body (H2 sections) for guidelines / legal pages.
	 *
	 * @param array<int,array{0:string,1:string,2:string}> $sections id, title, HTML.
	 */
	private static function doc( array $sections ): string {
		$html = '';
		foreach ( $sections as [ $id, $title, $body ] ) {
			$html .= '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>' . $body;
		}
		return BlockConverter::convert( $html );
	}

	/**
	 * Create all pages.
	 *
	 * @return array<string,int> slug → page ID.
	 */
	private function pages(): array {
		$b    = array( self::class, 'b' );
		$cats = array_map( static fn( $c ) => $c['name'], (array) $this->data['categories'] );
		$p    = fn( string $slug ) => $this->posts[ $slug ] ?? 0;

		$pages = array(
			'explore'              => array(
				'Explore',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Explore every story',
						'lead'  => 'Search and filter our full archive by category, topic and format.',
					)
				) . $b( 'techvenza/explore' ),
			),
			'trending'             => array( 'Trending', 'page-wide', $b( 'techvenza/trending-page' ) . $b( 'techvenza/category-rankings', array( 'surface' => 'alt' ) ) ),
			'editors-picks'        => array(
				"Editor's Picks",
				'page-wide',
				$b( 'techvenza/picks-hero' ) . $b(
					'techvenza/editor-note',
					array(
						'text'        => '“We pick stories that change how you think about a problem — not just ones that are popular. If a piece has a framework you will reuse, it belongs here.”',
						'attribution' => '— Sofia Lindqvist, Managing Editor (illustrative)',
						'spacing'     => 'tight',
					)
				) . $b(
					'techvenza/editors-picks',
					array(
						'eyebrow' => 'The selection',
						'heading' => 'Curated this month',
						'filter'  => true,
						'count'   => 12,
						'spacing' => 'none-top',
					)
				) . $b( 'techvenza/format-columns', array( 'surface' => 'alt' ) ) . $b(
					'techvenza/series-cards',
					array(
						'mode'  => 'manual',
						'items' => array(
							array(
								'title'    => 'Building with AI Agents',
								'parts'    => '4-part series',
								'text'     => 'From permissions to evaluation: a practical sequence for teams moving agents into production.',
								'category' => 'ai',
								'url'      => '/?s=AI+Agents',
							),
							array(
								'title'    => 'The Modern Factory',
								'parts'    => '3-part series',
								'text'     => 'Connected equipment, digital twins and cobots — what is working on real shop floors.',
								'category' => 'manufacturing',
								'url'      => '/?s=Industry+4.0',
							),
							array(
								'title'    => 'Security Fundamentals for Leaders',
								'parts'    => '3-part series',
								'text'     => 'Zero trust, identity and authentication explained for decision-makers.',
								'category' => 'cybersecurity',
								'url'      => '/?s=Zero+Trust',
							),
						),
					)
				),
			),
			'guides'               => array(
				'Guides & Resources',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Guides & Resources',
						'lead'  => 'Practical, step-by-step material for learning a topic properly — from first principles to production practice.',
						'tint'  => '#ECFEFF',
					)
				) . $b( 'techvenza/guides-grid' ) . $b(
					'techvenza/learning-paths',
					array(
						'surface' => 'alt',
						'paths'   => array(
							array(
								'title'    => 'AI for product teams',
								'level'    => 'Beginner → Intermediate',
								'category' => 'ai',
								'posts'    => array_values( array_filter( array( $p( 'rag-explained' ), $p( 'evaluating-llm-applications' ), $p( 'the-agentic-enterprise' ), $p( 'small-language-models-at-the-edge' ) ) ) ),
							),
							array(
								'title'    => 'Modern engineering foundations',
								'level'    => 'Intermediate',
								'category' => 'software',
								'posts'    => array_values( array_filter( array( $p( 'platform-engineering-paved-roads' ), $p( 'finops-for-engineers' ), $p( 'typescript-at-scale' ), $p( 'multi-cloud-vs-cloud-smart' ) ) ) ),
							),
							array(
								'title'    => 'Security essentials',
								'level'    => 'Beginner → Intermediate',
								'category' => 'cybersecurity',
								'posts'    => array_values( array_filter( array( $p( 'passkeys-login-flow' ), $p( 'zero-trust-12-month-roadmap' ) ) ) ),
							),
						),
					)
				) . $b(
					'techvenza/format-columns',
					array(
						'count'   => 4,
						'columns' => array(
							array(
								'title'   => 'Industry explainers',
								'icon'    => 'chart',
								'types'   => array( 'analysis' ),
								'orderby' => 'date',
							),
							array(
								'title'      => 'Technical tutorials',
								'icon'       => 'code',
								'types'      => array( 'guide' ),
								'categories' => array( 'ai', 'software', 'cloud', 'data', 'cybersecurity' ),
								'orderby'    => 'date',
							),
						),
					)
				) . $b(
					'techvenza/category-tiles',
					array(
						'mode'      => 'all',
						'countType' => 'guide',
						'eyebrow'   => '',
						'heading'   => 'Resource collections by topic',
						'linkLabel' => '',
						'count'     => 17,
						'surface'   => 'alt',
					)
				),
			),
			'authors'              => array(
				'Authors',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'The people behind the analysis',
						'lead'  => 'Staff editors and contributing experts across AI, engineering, business and industry.',
					)
				) . $b( 'techvenza/author-directory' ),
			),
			'about'                => array(
				'About',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title'   => 'Clarity for a world built on technology.',
						'lead'    => 'Techvenza exists to help professionals understand the technologies reshaping their work — through rigorous, readable journalism written by people with real expertise.',
						'display' => true,
					)
				)
				. $b( 'techvenza/section', array( 'surface' => 'alt' ), $b( 'core/columns', array( 'verticalAlignment' => 'center' ), '<div class="wp-block-columns are-vertically-aligned-center">' . $b( 'core/column', array(), '<div class="wp-block-column">' . $b( 'core/paragraph', array( 'className' => 'eyebrow' ), '<p class="eyebrow">Our story</p>' ) . $b( 'core/heading', array( 'className' => 'h2' ), '<h2 class="wp-block-heading h2">Built because good explanations were hard to find.</h2>' ) . $b( 'core/paragraph', array(), '<p>Technology coverage tends to split into two extremes: breathless news or impenetrable documentation. Techvenza was founded to sit in between — practical, evidence-based writing that respects the reader’s time and intelligence.</p>' ) . $b( 'core/paragraph', array(), '<p>We pair practitioners who have done the work with editors who help them explain it. The result is analysis you can act on.</p>' ) . '</div>' ) . $b( 'core/column', array(), '<div class="wp-block-column">' . self::panel( 'Mission', 'Make complex technology understandable and useful for the people making decisions about it.' ) . self::panel( 'Vision', 'A trusted, independent reference where expertise is shared openly and edited with care.' ) . '</div>' ) . '</div>' ) )
				. $b(
					'techvenza/category-tiles',
					array(
						'mode'      => 'all',
						'eyebrow'   => 'Coverage',
						'heading'   => 'What we cover',
						'count'     => 12,
						'compact'   => true,
						'linkLabel' => 'Explore all topics',
						'spacing'   => 'default',
					)
				)
				. $b(
					'techvenza/feature-items',
					array(
						'eyebrow' => 'Editorial philosophy',
						'heading' => 'How we work',
						'lead'    => '',
						'align'   => 'left',
						'surface' => 'alt',
						'items'   => array(
							array(
								'icon'  => 'checkCircle',
								'title' => 'Accuracy before speed',
								'text'  => 'We would rather publish a day later than publish something wrong.',
							),
							array(
								'icon'  => 'book',
								'title' => 'Depth with clarity',
								'text'  => 'Expert-level substance, written so that a smart non-specialist can follow.',
							),
							array(
								'icon'  => 'users',
								'title' => 'Practitioner voices',
								'text'  => 'First-hand experience is the core of every story we publish.',
							),
							array(
								'icon'  => 'shield',
								'title' => 'Editorial independence',
								'text'  => 'Advertisers and partners never influence what we cover or conclude.',
							),
							array(
								'icon'  => 'refresh',
								'title' => 'Open corrections',
								'text'  => 'Errors are corrected promptly and transparently with a dated note.',
							),
							array(
								'icon'  => 'file',
								'title' => 'Clear disclosure',
								'text'  => 'Conflicts of interest, sponsorship and AI assistance are always disclosed.',
							),
						),
					)
				)
				. $b(
					'techvenza/team-grid',
					array(
						'description' => 'Names and roles are illustrative sample content — replace with your real editorial team.',
						'members'     => array(
							array(
								'name' => 'Sofia Lindqvist',
								'role' => 'Managing Editor',
								'a1'   => '#111827',
								'a2'   => '#7C3AED',
							),
							array(
								'name' => 'Ananya Rao',
								'role' => 'AI Research Editor',
								'url'  => '/author/ananya-rao/',
								'a1'   => '#7C3AED',
								'a2'   => '#2563EB',
							),
							array(
								'name' => 'James Whitaker',
								'role' => 'Standards Editor',
								'a1'   => '#0D9488',
								'a2'   => '#2563EB',
							),
							array(
								'name' => 'Marcus Bell',
								'role' => 'Senior Writer',
								'url'  => '/author/marcus-bell/',
								'a1'   => '#0D9488',
								'a2'   => '#059669',
							),
						),
					)
				)
				. $b( 'techvenza/section', array(), $b( 'core/columns', array( 'verticalAlignment' => 'center' ), '<div class="wp-block-columns are-vertically-aligned-center">' . $b( 'core/column', array(), '<div class="wp-block-column">' . $b( 'core/paragraph', array( 'className' => 'eyebrow' ), '<p class="eyebrow">Trust &amp; transparency</p>' ) . $b( 'core/heading', array( 'className' => 'h2' ), '<h2 class="wp-block-heading h2">What you can expect from us.</h2>' ) . $b( 'core/list', array(), '<ul class="wp-block-list">' . $b( 'core/list-item', array(), '<li>Every article is reviewed by an editor before publication.</li>' ) . $b( 'core/list-item', array(), '<li>Authors disclose relevant affiliations and conflicts of interest.</li>' ) . $b( 'core/list-item', array(), '<li>Sponsored content, if ever published, is clearly labelled.</li>' ) . $b( 'core/list-item', array(), '<li>Corrections are noted at the end of the affected article.</li>' ) . '</ul>' ) . self::buttons( array( array( 'Read our editorial guidelines', '/editorial-guidelines/', 'secondary' ) ) ) . '</div>' ) . $b( 'core/column', array(), '<div class="wp-block-column">' . $b( 'core/group', array( 'className' => 'is-style-cta-band' ), '<div class="wp-block-group is-style-cta-band">' . $b( 'core/heading', array( 'className' => 'h2' ), '<h2 class="wp-block-heading h2">Join the contributor community</h2>' ) . $b( 'core/paragraph', array(), '<p>Share what you know with readers who value expertise.</p>' ) . self::buttons( array( array( 'Become an author', '/become-an-author/', 'fill' ), array( 'Contact us', '/contact/', 'secondary' ) ) ) . '</div>' ) . '</div>' ) . '</div>' ) ),
			),
			'contact'              => array(
				'Contact',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Get in touch',
						'lead'  => 'Questions about a story, partnership ideas or help with the author portal — choose a topic and we will route your message to the right team.',
					)
				) . $b( 'techvenza/contact-form' ),
			),
			'become-an-author'     => array(
				'Become an Author',
				'page-wide',
				$b( 'techvenza/split-hero' )
				. $b(
					'techvenza/feature-items',
					array(
						'eyebrow' => 'Why contribute',
						'heading' => 'Reasons to write with us',
						'lead'    => '',
						'align'   => 'left',
						'surface' => 'alt',
						'items'   => array(
							array(
								'icon'  => 'users',
								'title' => 'A thoughtful audience',
								'text'  => 'Readers come for depth and practical insight, not headlines.',
								'soft'  => '#EFF6FF',
								'ink'   => '#1D4ED8',
							),
							array(
								'icon'  => 'edit',
								'title' => 'Real editorial support',
								'text'  => 'An assigned editor helps with structure, clarity and sourcing.',
								'soft'  => '#F3F0FF',
								'ink'   => '#6D28D9',
							),
							array(
								'icon'  => 'award',
								'title' => 'A lasting byline',
								'text'  => 'Your author profile collects your work and expertise in one place.',
								'soft'  => '#FFFBEB',
								'ink'   => '#B45309',
							),
							array(
								'icon'  => 'globe',
								'title' => 'Shape the conversation',
								'text'  => 'Contribute perspectives the industry is missing.',
								'soft'  => '#ECFDF5',
								'ink'   => '#047857',
							),
							array(
								'icon'  => 'book',
								'title' => 'Sharpen your thinking',
								'text'  => 'Writing for an editor makes ideas clearer and more useful.',
								'soft'  => '#FFF1F2',
								'ink'   => '#BE123C',
							),
							array(
								'icon'  => 'shield',
								'title' => 'Independent platform',
								'text'  => 'No pay-to-publish. Sponsored content is always labelled.',
								'soft'  => '#F0F9FF',
								'ink'   => '#0369A1',
							),
						),
					)
				)
				. $b(
					'techvenza/section',
					array(),
					$b(
						'core/columns',
						array(),
						'<div class="wp-block-columns">' . $b(
							'core/column',
							array(),
							'<div class="wp-block-column">' . $b( 'core/heading', array( 'className' => 'h2' ), '<h2 class="wp-block-heading h2">Who can apply</h2>' ) . $b( 'core/list', array(), '<ul class="wp-block-list">' . $b( 'core/list-item', array(), '<li>Practitioners with hands-on experience in a field we cover</li>' ) . $b( 'core/list-item', array(), '<li>Researchers and academics writing for a professional audience</li>' ) . $b( 'core/list-item', array(), '<li>Founders, product and business leaders sharing lessons learned</li>' ) . $b( 'core/list-item', array(), '<li>Consultants and analysts with independent, evidence-based views</li>' ) . '</ul>' ) . $b(
								'core/heading',
								array(
									'level'     => 3,
									'className' => 'h3 mt-7',
								),
								'<h3 class="wp-block-heading h3 mt-7">Accepted categories</h3>'
							) . $b(
								'core/paragraph',
								array(),
								'<p>' . esc_html( implode( ' · ', $cats ) ) . '</p>'
							) . '</div>'
						) . $b(
							'core/column',
							array(),
							'<div class="wp-block-column">' . $b( 'core/heading', array( 'className' => 'h2' ), '<h2 class="wp-block-heading h2">Contributor benefits</h2>' ) . $b( 'core/list', array(), '<ul class="wp-block-list">' . $b( 'core/list-item', array(), '<li>A public author profile with your bio, expertise and links</li>' ) . $b( 'core/list-item', array(), '<li>Editorial feedback on every submission</li>' ) . $b( 'core/list-item', array(), '<li>Access to the author portal to manage drafts and track reviews</li>' ) . $b( 'core/list-item', array(), '<li>Article performance summaries for your published work</li>' ) . '</ul>' ) . $b( 'core/paragraph', array( 'className' => 'form-note warn' ), '<p class="form-note warn">We do not offer payment, guaranteed publication or paid backlinks. Any future changes to these terms will be published here.</p>' ) . $b(
								'core/heading',
								array(
									'level'     => 3,
									'className' => 'h3 mt-7',
								),
								'<h3 class="wp-block-heading h3 mt-7">Editorial quality standards</h3>'
							) . $b(
								'core/paragraph',
								array(),
								'<p>Original work, accurate and sourced claims, clear disclosure of conflicts of interest and AI-assisted writing, and a focus on reader value over promotion. <a class="link" href="/editorial-guidelines/">Full guidelines</a>.</p>'
							) . '</div>'
						) . '</div>'
					)
				)
				. $b( 'techvenza/process-steps', array( 'surface' => 'alt' ) )
				. $b(
					'techvenza/feature-items',
					array(
						'eyebrow' => 'Formats',
						'heading' => 'Article formats we publish',
						'lead'    => '',
						'align'   => 'left',
						'style'   => 'panel',
						'columns' => 4,
						'items'   => array(
							array(
								'label' => '2,000–3,500 words',
								'title' => 'Deep dive',
								'text'  => 'A thorough examination of a topic with evidence and examples.',
								'ink'   => '#7C3AED',
							),
							array(
								'label' => '1,200–2,500 words',
								'title' => 'How-to guide',
								'text'  => 'Step-by-step practical instruction readers can follow.',
								'ink'   => '#7C3AED',
							),
							array(
								'label' => '1,000–2,000 words',
								'title' => 'Analysis',
								'text'  => 'An informed take on a trend, decision or development.',
								'ink'   => '#7C3AED',
							),
							array(
								'label' => '1,200–2,500 words',
								'title' => 'Case study',
								'text'  => 'What happened, what worked, and what you would change.',
								'ink'   => '#7C3AED',
							),
						),
					)
				)
				. $b(
					'techvenza/faq',
					array(
						'items'   => self::author_faq(),
						'surface' => 'alt',
					)
				)
				. $b(
					'techvenza/cta-band',
					array(
						'layout'         => 'center',
						'heading'        => 'Ready to apply?',
						'text'           => 'It takes about ten minutes. We review every application.',
						'primaryLabel'   => 'Start your application',
						'primaryUrl'     => '/apply/',
						'secondaryLabel' => '',
					)
				),
			),
			'apply'                => array(
				'Apply to write',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Apply to write',
						'lead'  => 'Tell us about your expertise. Fields marked * are required.',
					)
				) . $b( 'techvenza/apply-form' ),
			),
			'newsletter'           => array( 'Newsletter', 'page-wide', $b( 'techvenza/newsletter-signup' ) ),
			'editorial-guidelines' => array(
				'Editorial Guidelines',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Editorial guidelines',
						'lead'  => 'The standards every Techvenza article meets before publication. Please read them before submitting.',
					)
				) . $b(
					'techvenza/doc-layout',
					array(),
					self::doc(
						array(
							array( 'content-types', 'Accepted content types', '<p>We publish deep dives, how-to guides, analysis, case studies and perspectives. We do not publish press releases, product announcements, listicles without substance, or promotional content presented as editorial.</p>' ),
							array( 'originality', 'Originality requirements', '<p>Submissions must be your original work and not published elsewhere, including on your own blog, without prior agreement. Republishing after Techvenza publication is welcome with a canonical link back to the original.</p>' ),
							array( 'fact-checking', 'Fact-checking expectations', '<p>Every factual claim must be accurate and verifiable. Editors may ask for sources, data or clarification. Figures should include the date and context in which they apply.</p>' ),
							array( 'citations', 'Citation and sourcing standards', '<ul><li>Link to primary sources — original research, official documentation, filings — wherever possible.</li><li>Attribute quotes accurately and in context.</li><li>Distinguish clearly between evidence, experience and opinion.</li></ul>' ),
							array( 'ai-disclosure', 'AI-assisted writing disclosure', '<p>You may use AI tools for research support, outlining or editing. You must disclose any material use of AI in your submission notes. You remain fully responsible for accuracy, originality and the absence of fabricated sources. Articles substantially generated by AI without meaningful human expertise will not be accepted.</p>' ),
							array( 'images', 'Image rights and attribution', '<p>Only submit images you have the right to use, with attribution and licence details. Screenshots should not expose personal or confidential data. Every informative image needs descriptive alt text.</p>' ),
							array( 'links', 'Link policy', '<p>Links must help the reader. Relevant references to your own work or organisation are acceptable when disclosed and non-promotional. We do not sell links; paid or unrelated links are removed and may lead to account review.</p>' ),
							array( 'formatting', 'Content formatting', '<ul><li>Use descriptive H2 and H3 headings in logical order.</li><li>Keep paragraphs short and front-load key ideas.</li><li>Use tables and code blocks for structured information.</li><li>Provide an excerpt of 1–2 sentences and a suggested SEO title.</li></ul>' ),
							array( 'review', 'Editorial review process', '<ol><li>Submission enters the review queue.</li><li>An editor assesses fit, accuracy, clarity and originality.</li><li>You receive approval, requested changes or a decision not to publish.</li><li>Approved articles are scheduled; final edits are shared before publication.</li></ol>' ),
							array( 'revisions', 'Revision expectations', '<p>Most articles go through at least one round of revisions. Please respond to feedback within 14 days or let your editor know if you need more time.</p>' ),
							array( 'corrections', 'Corrections policy', '<p>We correct errors promptly. Substantive corrections are noted at the end of the article with the date and nature of the change.</p>' ),
							array( 'conflicts', 'Conflicts of interest and sponsored content', '<p>Disclose employment, investments, client relationships or other interests related to your topic. Sponsored content, if published, is clearly labelled and kept separate from editorial coverage.</p>' ),
						)
					)
				) . $b(
					'techvenza/faq',
					array(
						'items'   => array_slice( self::author_faq(), 1 ),
						'spacing' => 'none-top',
					)
				),
			),
			'privacy-policy'       => array(
				'Privacy Policy',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Privacy policy',
						'lead'  => 'How Techvenza collects, uses and protects personal information. Last revised: draft.',
					)
				) . $b(
					'techvenza/doc-layout',
					array( 'draftBanner' => true ),
					self::doc(
						array(
							array( 'who-we-are', 'Who we are', '<p>Techvenza (“we”, “us”) operates this publication. Contact details for privacy requests will be provided here once confirmed.</p>' ),
							array( 'data-we-collect', 'Information we collect', '<ul><li><strong>Account data:</strong> name, email address and profile details you provide.</li><li><strong>Contributor data:</strong> application details, biography, writing samples and submitted articles.</li><li><strong>Newsletter data:</strong> email address, topic preferences and consent records.</li><li><strong>Usage data:</strong> aggregated view counts; analytics only with your consent where required.</li></ul>' ),
							array( 'how-we-use', 'How we use information', '<p>To provide accounts and newsletters, review contributor applications, publish approved articles, respond to inquiries, maintain security and improve the service.</p>' ),
							array( 'legal-bases', 'Legal bases', '<p>Depending on jurisdiction, processing may rely on consent, contract, legitimate interests or legal obligations. This section must be completed for each applicable law.</p>' ),
							array( 'cookies', 'Cookies and local storage', '<p>Essential cookies keep you signed in and protect forms. With your permission we store preferences and load analytics. You can change your choice at any time using “Cookie preferences” in the footer.</p>' ),
							array( 'sharing', 'Sharing and processors', '<p>List service providers (hosting, email delivery, newsletter provider, analytics) and safeguards for international transfers.</p>' ),
							array( 'retention', 'Retention', '<p>Contact messages are deleted automatically after the period set by the site owner. State retention periods for every other category of data.</p>' ),
							array( 'rights', 'Your rights', '<p>Explain rights of access, correction, deletion, objection and portability as applicable, and how to exercise them.</p>' ),
							array( 'changes', 'Changes to this policy', '<p>Explain how users will be notified of material changes.</p>' ),
						)
					)
				),
			),
			'terms'                => array(
				'Terms and Conditions',
				'page-wide',
				$b(
					'techvenza/page-hero',
					array(
						'title' => 'Terms and conditions',
						'lead'  => 'Rules for using Techvenza and contributing content. Last revised: draft.',
					)
				) . $b(
					'techvenza/doc-layout',
					array( 'draftBanner' => true ),
					self::doc(
						array(
							array( 'acceptance', 'Acceptance of terms', '<p>By using Techvenza you agree to these terms. If you do not agree, please do not use the service.</p>' ),
							array( 'accounts', 'Accounts', '<p>You are responsible for the accuracy of your account information and for keeping your credentials secure. Notify us of any unauthorised use.</p>' ),
							array( 'submissions', 'Contributor submissions', '<p>Submitting an article does not guarantee publication. Techvenza may edit submissions for clarity, length, accuracy and style, and will share substantive changes before publication.</p>' ),
							array( 'content-rights', 'Content rights and licence', '<p>Define ownership and licence terms for contributed content (for example, contributors retain copyright and grant Techvenza a licence to publish). This must be finalised with counsel.</p>' ),
							array( 'moderation', 'Moderation', '<p>We may remove content or comments that violate these terms or the editorial guidelines.</p>' ),
							array( 'acceptable-use', 'Acceptable use', '<ul><li>No unlawful, infringing, defamatory or misleading content.</li><li>No undisclosed promotional content or paid links.</li><li>No attempts to disrupt or gain unauthorised access to the service.</li></ul>' ),
							array( 'termination', 'Account suspension and termination', '<p>We may suspend or terminate accounts that breach these terms, with notice where appropriate. You may close your account at any time.</p>' ),
							array( 'liability', 'Disclaimers and liability', '<p>Content is provided for information and does not constitute professional advice. Liability limitations must be drafted for the applicable jurisdiction.</p>' ),
							array( 'law', 'Governing law', '<p>To be determined with legal counsel.</p>' ),
						)
					)
				),
			),
			'login'                => array( 'Log in', 'page-wide', $b( 'techvenza/login-form' ) ),
			'register'             => array( 'Create an account', 'page-wide', $b( 'techvenza/register-form' ) ),
			'forgot-password'      => array( 'Forgot password', 'page-wide', $b( 'techvenza/forgot-form' ) ),
			'reset-password'       => array( 'Reset password', 'page-wide', $b( 'techvenza/reset-form' ) ),
			'dashboard'            => array( 'Reader Dashboard', 'page-app', $b( 'techvenza/reader-dashboard' ) ),
			'author-portal'        => array( 'Author Portal', 'page-app', $b( 'techvenza/author-portal' ) ),
		);

		$ids = array();
		foreach ( $pages as $slug => [ $title, $template, $content ] ) {
			$existing = get_page_by_path( $slug );
			$id       = wp_insert_post(
				array(
					'ID'           => $existing ? $existing->ID : 0,
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => $content,
					'post_author'  => 1,
				)
			);
			if ( ! $id ) {
				continue;
			}
			update_post_meta( $id, '_wp_page_template', $template );
			$this->flag_post( $id );
			$ids[ $slug ] = (int) $id;
		}
		if ( isset( $ids['privacy-policy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy-policy'] );
		}
		update_option( 'show_on_front', 'posts' );
		$this->log( 'Pages: ' . count( $ids ) );
		return $ids;
	}

	/**
	 * Menus and locations.
	 *
	 * @param array<string,int> $pages Page IDs.
	 */
	private function menus( array $pages ): void {
		$page = static fn( string $slug, string $title = '', string $desc = '', string $classes = '' ) => array( 'page', $slug, $title, $desc, $classes );
		$url  = static fn( string $path, string $title, string $desc = '', string $classes = '' ) => array( 'custom', $path, $title, $desc, $classes );
		$cat  = static fn( string $slug ) => array( 'category', $slug, '', '', '' );

		$menus = array(
			'primary'  => array(
				'Primary',
				array(
					$url( '/', 'Home' ),
					array(
						'item'     => $page( 'explore', 'Explore' ),
						'children' => array(
							$page( 'explore', 'Latest Stories', 'Everything, newest first', 'tv-icon-layers' ),
							$page( 'trending', 'Trending', 'What readers are opening now', 'tv-icon-trend' ),
							$page( 'editors-picks', "Editor's Picks", 'Curated by our editors', 'tv-icon-star' ),
							$url( '/explore/?type=deep-dive', 'Deep Dives', 'Long-form analysis', 'tv-icon-book' ),
							$url( '/explore/?type=analysis', 'Industry Insights', 'Expert perspectives', 'tv-icon-chart' ),
							$page( 'guides', 'Guides & Resources', 'Learn step by step', 'tv-icon-compass' ),
						),
					),
					$url( '#', 'Categories', '', 'tv-mega-categories' ),
					$page( 'authors', 'Authors' ),
					$page( 'about', 'About' ),
				),
			),
			'footer-1' => array( 'Read', array( $page( 'explore', 'Latest stories' ), $page( 'trending', 'Trending' ), $page( 'editors-picks', "Editor's picks" ), $page( 'guides', 'Guides & resources' ), $url( '/?s=', 'Search' ) ) ),
			'footer-2' => array( 'Topics', array( $cat( 'ai' ), $cat( 'business' ), $cat( 'software' ), $cat( 'manufacturing' ), $cat( 'cybersecurity' ), $cat( 'data' ) ) ),
			'footer-3' => array( 'Contribute', array( $page( 'become-an-author', 'Become an author' ), $page( 'apply', 'Apply to write' ), $page( 'editorial-guidelines', 'Editorial guidelines' ), $page( 'authors', 'Our authors' ), $page( 'author-portal', 'Author portal' ) ) ),
			'footer-4' => array( 'Company', array( $page( 'about', 'About Techvenza' ), $page( 'contact', 'Contact' ), $page( 'newsletter', 'Newsletter' ) ) ),
			'legal'    => array( 'Legal', array( $page( 'privacy-policy', 'Privacy' ), $page( 'terms', 'Terms' ) ) ),
		);

		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		foreach ( $menus as $location => [ $name, $items ] ) {
			$existing = wp_get_nav_menu_object( $name );
			if ( $existing ) {
				wp_delete_nav_menu( $existing->term_id );
			}
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				continue;
			}
			update_term_meta( $menu_id, 'tv_seed', 1 );
			$add = function ( array $it, int $parent_item = 0 ) use ( $menu_id, $pages ): int {
				[ $type, $ref, $title, $desc, $classes ] = $it;
				$args                                    = array(
					'menu-item-status'      => 'publish',
					'menu-item-parent-id'   => $parent_item,
					'menu-item-title'       => $title,
					'menu-item-description' => $desc,
					'menu-item-classes'     => $classes,
				);
				if ( 'page' === $type && isset( $pages[ $ref ] ) ) {
					$args += array(
						'menu-item-object-id' => $pages[ $ref ],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
					);
				} elseif ( 'category' === $type ) {
					$term = get_term_by( 'slug', $ref, 'category' );
					if ( ! $term ) {
						return 0;
					}
					$args += array(
						'menu-item-object-id' => $term->term_id,
						'menu-item-object'    => 'category',
						'menu-item-type'      => 'taxonomy',
					);
				} else {
					$args += array(
						'menu-item-type' => 'custom',
						'menu-item-url'  => '#' === $ref ? '#' : home_url( $ref ),
					);
				}
				$id = wp_update_nav_menu_item( $menu_id, 0, $args );
				return is_wp_error( $id ) ? 0 : (int) $id;
			};
			foreach ( $items as $it ) {
				if ( isset( $it['item'] ) ) {
					$parent = $add( $it['item'] );
					foreach ( $it['children'] as $child ) {
						$add( $child, $parent );
					}
				} else {
					$add( $it );
				}
			}
			$locations[ $location ] = $menu_id;
			if ( 'primary' === $location ) {
				$locations['mobile'] = $menu_id;
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		$this->log( 'Menus: ' . count( $menus ) );
	}

	/**
	 * Remove everything the seeder created.
	 */
	public function reset(): void {
		Notifier::$muted = true;
		global $wpdb;
		$ids         = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'tv_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'tv_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( array_merge( $ids, $attachments ) as $id ) {
			$wpdb->delete( Schema::table( 'post_views' ), array( 'post_id' => $id ), array( '%d' ) );
			wp_delete_post( (int) $id, true );
		}
		$this->log( 'Deleted posts/pages/attachments: ' . ( count( $ids ) + count( $attachments ) ) );

		$terms = get_terms(
			array(
				'taxonomy'   => array( 'category', 'nav_menu' ),
				'hide_empty' => false,
				'meta_key'   => 'tv_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			wp_delete_term( $t->term_id, $t->taxonomy );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$users = get_users(
			array(
				'meta_key' => 'tv_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'   => 'ID',
			)
		);
		foreach ( $users as $uid ) {
			if ( get_current_user_id() === (int) $uid ) {
				continue;
			}
			Notifications::delete_user( (int) $uid );
			History::clear( (int) $uid );
			wp_delete_user( (int) $uid, 1 );
		}
		$wpdb->delete( Schema::table( 'subscribers' ), array( 'is_seed' => 1 ), array( '%d' ) );
		wp_cache_flush();
		Notifier::$muted = false;
		$this->log( 'Reset complete.' );
	}
}
