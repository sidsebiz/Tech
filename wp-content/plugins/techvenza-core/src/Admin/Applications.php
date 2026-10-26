<?php
/**
 * Author applications: list by status and decision screen (approve → Author role and portal
 * access, request information, reject), each with an editable template email.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Support\Render as R;

/**
 * Applications screens.
 */
final class Applications {

	/**
	 * Status tabs.
	 *
	 * @return array<string,string>
	 */
	private static function tabs(): array {
		return array(
			'pending'        => __( 'Pending', 'techvenza-core' ),
			'info_requested' => __( 'Info requested', 'techvenza-core' ),
			'approved'       => __( 'Approved', 'techvenza-core' ),
			'rejected'       => __( 'Rejected', 'techvenza-core' ),
			'all'            => __( 'All', 'techvenza-core' ),
		);
	}

	/**
	 * Applications by status.
	 *
	 * @param string $status Status or 'all'.
	 * @return array<int,\WP_Post>
	 */
	public static function find( string $status = 'all' ): array {
		$args = array(
			'post_type'      => PostTypes::APPLICATION,
			'post_status'    => 'any',
			'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( 'all' !== $status ) {
			$args['meta_key']   = 'tv_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $status; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		return array_values( array_filter( get_posts( $args ), static fn( $p ) => $p instanceof \WP_Post ) );
	}

	/**
	 * Pending count (menu bubble).
	 */
	public static function pending_count(): int {
		$cached = wp_cache_get( 'tv_apps_pending', 'techvenza' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$n = count( self::find( 'pending' ) );
		wp_cache_set( 'tv_apps_pending', $n, 'techvenza', 5 * MINUTE_IN_SECONDS );
		return $n;
	}

	/**
	 * Meta helper.
	 *
	 * @param int    $id  Application.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	private static function m( int $id, string $key ) {
		return get_post_meta( $id, $key, true );
	}

	/**
	 * Route.
	 */
	public static function render(): string {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $id ? self::detail( $id ) : self::index();
	}

	/**
	 * List.
	 */
	private static function index(): string {
		$tabs   = self::tabs();
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $tabs[ $status ] ) ? $status : 'pending';
		$nav    = '';
		foreach ( $tabs as $k => $l ) {
			$nav .= sprintf( '<a class="tab" href="%s"%s>%s<span class="count">%d</span></a>', esc_url( Admin::url( 'techvenza-applications', array( 'status' => $k ) ) ), $k === $status ? ' aria-current="page"' : '', esc_html( $l ), count( self::find( $k ) ) );
		}
		$rows = '';
		foreach ( self::find( $status ) as $a ) {
			$cats  = implode( ' ', array_map( static fn( $s ) => R::chip( CategoryMeta::get( (string) $s ), false ), (array) self::m( $a->ID, 'tv_categories' ) ) );
			$rows .= sprintf(
				'<tr><td><strong><a href="%1$s">%2$s</a></strong><div class="meta">%3$s</div></td><td>%4$s</td><td>%5$d</td><td>%6$s</td><td>%7$s</td><td><a class="button button-small" href="%1$s">%8$s</a></td></tr>',
				esc_url( Admin::url( 'techvenza-applications', array( 'id' => $a->ID ) ) ),
				esc_html( $a->post_title ),
				esc_html( (string) self::m( $a->ID, 'tv_title' ) ),
				$cats,
				count( (array) self::m( $a->ID, 'tv_samples' ) ),
				esc_html( R::date( get_the_date( 'Y-m-d', $a ) ) ),
				R::badge( (string) self::m( $a->ID, 'tv_status' ) ),
				esc_html__( 'Review', 'techvenza-core' )
			);
		}
		$table = $rows
			? sprintf(
				'<table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col"><span class="screen-reader-text">%s</span></th></tr></thead><tbody>%s</tbody></table>',
				esc_html__( 'Applicant', 'techvenza-core' ),
				esc_html__( 'Categories', 'techvenza-core' ),
				esc_html__( 'Samples', 'techvenza-core' ),
				esc_html__( 'Submitted', 'techvenza-core' ),
				esc_html__( 'Status', 'techvenza-core' ),
				esc_html__( 'Actions', 'techvenza-core' ),
				$rows
			)
			: R::empty_state( 'users', __( 'No applications here', 'techvenza-core' ), __( 'Applications in this status will appear here.', 'techvenza-core' ) );
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Author applications', 'techvenza-core' ), '' ),
			),
			__( 'Author applications', 'techvenza-core' ),
			esc_html__( 'Review applicants, writing samples and expertise.', 'techvenza-core' )
		) . sprintf( '<div class="table-wrap"><nav class="tabs" aria-label="%s">%s</nav>%s</div>', esc_attr__( 'Application status', 'techvenza-core' ), $nav, $table );
	}

	/**
	 * Decision screen.
	 *
	 * @param int $id Application.
	 */
	private static function detail( int $id ): string {
		$a = get_post( $id );
		if ( ! $a || PostTypes::APPLICATION !== $a->post_type ) {
			return R::empty_state( 'users', __( 'Application not found', 'techvenza-core' ), __( 'It may have been deleted.', 'techvenza-core' ) );
		}
		$status  = (string) self::m( $id, 'tv_status' );
		$banners = array(
			'approved'       => array( 'notice-success', __( 'Approved — this applicant has Author access.', 'techvenza-core' ) ),
			'rejected'       => array( 'notice-error', __( 'Rejected — the applicant has been notified.', 'techvenza-core' ) ),
			'info_requested' => array( 'notice-warning', __( 'More information requested from the applicant.', 'techvenza-core' ) ),
			'pending'        => array( 'notice-info', __( 'Awaiting a decision.', 'techvenza-core' ) ),
		);
		$banner  = $banners[ $status ] ?? $banners['pending'];
		$samples = array_filter( (array) self::m( $id, 'tv_samples' ) );
		$reason  = (string) self::m( $id, 'tv_reason' );
		$sample  = '';
		foreach ( $samples as $s ) {
			$sample .= sprintf( '<li><span class="act-ico">%s</span><a href="%s" target="_blank" rel="noopener nofollow">%s <span class="screen-reader-text">%s</span></a></li>', R::icon( 'file' ), esc_url( (string) $s ), esc_html( (string) $s ), esc_html__( '(opens in a new tab)', 'techvenza-core' ) );
		}
		$history = '';
		foreach ( (array) self::m( $id, 'tv_history' ) as $h ) {
			if ( is_array( $h ) ) {
				$who      = get_userdata( (int) ( $h['user'] ?? 0 ) );
				$history .= sprintf( '<li style="--c:#2563EB"><b>%s</b> %s<div class="when">%s</div>%s</li>', esc_html( ucfirst( str_replace( '_', ' ', (string) ( $h['status'] ?? '' ) ) ) ), $who ? '<span class="meta">' . esc_html( $who->display_name ) . '</span>' : '', esc_html( R::date( substr( (string) ( $h['at'] ?? '' ), 0, 10 ) ) ), ! empty( $h['note'] ) ? '<p class="meta">' . esc_html( (string) $h['note'] ) . '</p>' : '' );
			}
		}
		$form = static function ( string $decision, string $label, string $class, bool $note ) use ( $id, $status ): string {
			return sprintf(
				'<form method="post" action="%1$s" style="display:grid;gap:6px">%2$s<input type="hidden" name="action" value="techvenza_application"><input type="hidden" name="id" value="%3$d"><input type="hidden" name="decision" value="%4$s">%5$s<button class="%6$s" type="submit"%7$s style="width:100%%">%8$s</button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( 'techvenza_application_' . $id, '_wpnonce', true, false ),
				$id,
				esc_attr( $decision ),
				$note ? sprintf( '<label class="screen-reader-text" for="note-%1$s">%2$s</label><textarea id="note-%1$s" name="note" rows="3" placeholder="%3$s"%4$s></textarea>', esc_attr( $decision ), esc_html__( 'Message to the applicant', 'techvenza-core' ), esc_attr__( 'Message to the applicant (included in the email)', 'techvenza-core' ), 'info_requested' === $decision ? ' required' : '' ) : '<textarea name="note" rows="2" placeholder="' . esc_attr__( 'Optional welcome note', 'techvenza-core' ) . '"></textarea>',
				esc_attr( $class ),
				$status === $decision ? ' disabled' : '',
				esc_html( $label )
			);
		};
		$cats = implode( ', ', array_map( static fn( $s ) => (string) ( CategoryMeta::get( (string) $s )['short'] ?? $s ), (array) self::m( $id, 'tv_categories' ) ) );
		$exp  = self::m( $id, 'tv_expertise' );
		$exp  = is_array( $exp ) ? implode( ', ', $exp ) : (string) $exp;
		$link = (string) self::m( $id, 'tv_link' );
		$uid  = (int) self::m( $id, 'tv_user_id' );
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Applications', 'techvenza-core' ), Admin::url( 'techvenza-applications' ) ),
				array( $a->post_title, '' ),
			),
			$a->post_title,
			esc_html( trim( (string) self::m( $id, 'tv_title' ) . ( self::m( $id, 'tv_org' ) ? ' · ' . self::m( $id, 'tv_org' ) : '' ) ) ),
			R::badge( $status )
		) . sprintf( '<div class="notice %s inline" style="margin:0 0 16px"><p><strong>%s</strong></p></div>', esc_attr( $banner[0] ), esc_html( $banner[1] ) )
		. sprintf(
			'<div class="review-grid"><div><section class="panel"><h2 class="panel-title">%1$s</h2><p>%2$s</p></section><section class="panel"><h2 class="panel-title">%3$s</h2><p>%4$s</p>%5$s</section><section class="panel"><h2 class="panel-title">%6$s</h2>%7$s</section>%8$s</div>
			<aside><section class="panel"><h2 class="panel-title">%9$s</h2><div style="display:grid;gap:14px">%10$s%11$s%12$s</div></section><section class="panel"><h2 class="panel-title">%13$s</h2><dl class="kv"><dt>%14$s</dt><dd><a href="mailto:%15$s">%15$s</a></dd><dt>%16$s</dt><dd>%17$s</dd><dt>%18$s</dt><dd>%19$s</dd><dt>%20$s</dt><dd>%21$s</dd><dt>%22$s</dt><dd>%23$s</dd><dt>%24$s</dt><dd>%25$s</dd><dt>%26$s</dt><dd>%27$s</dd></dl></section></aside></div>',
			esc_html__( 'Biography', 'techvenza-core' ),
			esc_html( (string) self::m( $id, 'tv_bio' ) ),
			esc_html__( 'Why they want to write', 'techvenza-core' ),
			esc_html( $reason ),
			preg_match( '/backlink|link building|seo link/i', $reason ) ? '<div class="notice notice-warning inline"><p>' . esc_html__( 'Mentions backlinks — this conflicts with the link policy.', 'techvenza-core' ) . '</p></div>' : '',
			esc_html__( 'Writing samples', 'techvenza-core' ),
			$sample ? '<ul class="activity">' . $sample . '</ul>' : R::empty_state( 'file', __( 'No samples provided', 'techvenza-core' ), __( 'Consider requesting writing samples before deciding.', 'techvenza-core' ) ),
			$history ? '<section class="panel"><h2 class="panel-title">' . esc_html__( 'Decision history', 'techvenza-core' ) . '</h2><ol class="timeline">' . $history . '</ol></section>' : '',
			esc_html__( 'Decision', 'techvenza-core' ),
			$form( 'approved', __( 'Approve application', 'techvenza-core' ), 'button button-primary', false ),
			$form( 'info_requested', __( 'Request more information', 'techvenza-core' ), 'button', true ),
			$form( 'rejected', __( 'Reject application', 'techvenza-core' ), 'button tva-danger', true ),
			esc_html__( 'Details', 'techvenza-core' ),
			esc_html__( 'Email', 'techvenza-core' ),
			esc_html( (string) self::m( $id, 'tv_email' ) ),
			esc_html__( 'Submitted', 'techvenza-core' ),
			esc_html( R::date( get_the_date( 'Y-m-d', $a ) ) ),
			esc_html__( 'Reference', 'techvenza-core' ),
			esc_html( (string) self::m( $id, 'tv_reference' ) ),
			esc_html__( 'Expertise', 'techvenza-core' ),
			esc_html( $exp ),
			esc_html__( 'Categories', 'techvenza-core' ),
			esc_html( $cats ),
			esc_html__( 'Portfolio', 'techvenza-core' ),
			$link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener nofollow">' . esc_html( (string) preg_replace( '#^https?://#', '', $link ) ) . '</a>' : '—',
			esc_html__( 'Account', 'techvenza-core' ),
			$uid && get_userdata( $uid ) ? '<a href="' . esc_url( get_edit_user_link( $uid ) ) . '">' . esc_html( get_userdata( $uid )->user_login ) . '</a>' : esc_html__( 'Not linked yet', 'techvenza-core' )
		);
	}

	/**
	 * Apply a decision (admin-post).
	 */
	public static function handle(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'techvenza_application_' . $id );
		if ( ! current_user_can( 'tv_manage_applications' ) ) {
			wp_die( esc_html__( 'You do not have permission to decide applications.', 'techvenza-core' ), 403 );
		}
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$result   = self::decide( $id, $decision, $note );
		if ( is_wp_error( $result ) ) {
			Admin::back( 'error', $result->get_error_message() );
		}
		Admin::back( 'decided' );
	}

	/**
	 * Decide an application (also used by tests and WP-CLI).
	 *
	 * @param int    $id       Application.
	 * @param string $decision approved|info_requested|rejected.
	 * @param string $note     Message to the applicant.
	 * @return true|\WP_Error
	 */
	public static function decide( int $id, string $decision, string $note = '' ) {
		$app = get_post( $id );
		if ( ! $app || PostTypes::APPLICATION !== $app->post_type || ! in_array( $decision, array( 'approved', 'info_requested', 'rejected' ), true ) ) {
			return new \WP_Error( 'tv_invalid', __( 'Unknown application or decision.', 'techvenza-core' ) );
		}
		if ( 'info_requested' === $decision && '' === trim( $note ) ) {
			return new \WP_Error( 'tv_note', __( 'Add the question you want the applicant to answer.', 'techvenza-core' ) );
		}
		$email = (string) get_post_meta( $id, 'tv_email', true );
		$first = (string) strtok( $app->post_title, ' ' );
		$vars  = array(
			'applicant_first_name' => $first ? $first : $app->post_title,
			'note'                 => $note,
			'reference'            => (string) get_post_meta( $id, 'tv_reference', true ),
		);
		if ( 'approved' === $decision ) {
			$user = get_user_by( 'email', $email );
			$line = '';
			if ( ! $user ) {
				$login = sanitize_user( strtolower( strtok( $email, '@' ) ), true );
				$base  = $login ? $login : 'author';
				$n     = 1;
				while ( '' === $login || username_exists( $login ) ) {
					$login = $base . ( ++$n );
				}
				$parts = explode( ' ', $app->post_title, 2 );
				$uid   = wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => $email,
						'user_pass'    => wp_generate_password( 24 ),
						'display_name' => $app->post_title,
						'first_name'   => $parts[0],
						'last_name'    => $parts[1] ?? '',
						'description'  => (string) get_post_meta( $id, 'tv_bio', true ),
						'role'         => 'tv_author',
					)
				);
				if ( is_wp_error( $uid ) ) {
					return $uid;
				}
				$user = get_userdata( (int) $uid );
				if ( $user ) {
					$key  = get_password_reset_key( $user );
					$page = get_page_by_path( 'reset-password' );
					if ( ! is_wp_error( $key ) ) {
						$url = $page ? add_query_arg(
							array(
								'key'   => $key,
								'login' => rawurlencode( $user->user_login ),
							),
							(string) get_permalink( $page )
						) : network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
						/* translators: %s: link */
						$line = sprintf( __( 'Choose your password here: %s', 'techvenza-core' ), $url );
					}
				}
			} elseif ( ! user_can( $user, 'tv_use_portal' ) ) {
				$user->set_role( 'tv_author' );
			}
			if ( $user ) {
				update_user_meta( $user->ID, 'tv_email_verified', '1' );
				foreach ( array( 'tv_title', 'tv_expertise', 'tv_categories' ) as $k ) {
					$v = get_post_meta( $id, $k, true );
					if ( $v && ! get_user_meta( $user->ID, $k, true ) ) {
						update_user_meta( $user->ID, $k, 'tv_expertise' === $k && is_string( $v ) ? array_filter( array_map( 'trim', explode( ',', $v ) ) ) : $v );
					}
				}
				if ( ! get_user_meta( $user->ID, 'tv_website', true ) ) {
					update_user_meta( $user->ID, 'tv_website', (string) get_post_meta( $id, 'tv_link', true ) );
				}
				update_post_meta( $id, 'tv_user_id', $user->ID );
				\Techvenza\Core\Content\UserMeta::flush( $user->ID );
			}
			$portal                    = get_page_by_path( 'author-portal' );
			$vars['portal_url']        = $portal ? (string) get_permalink( $portal ) : admin_url();
			$vars['set_password_line'] = $line;
		}
		update_post_meta( $id, 'tv_status', $decision );
		update_post_meta( $id, 'tv_decision_note', $note );
		$history   = (array) get_post_meta( $id, 'tv_history', true );
		$history[] = array(
			'status' => $decision,
			'user'   => get_current_user_id(),
			'note'   => $note,
			'at'     => current_time( 'mysql' ),
		);
		update_post_meta( $id, 'tv_history', array_values( array_filter( $history ) ) );
		wp_cache_delete( 'tv_apps_pending', 'techvenza' );
		$event = array(
			'approved'       => 'application_approved',
			'rejected'       => 'application_rejected',
			'info_requested' => 'application_info',
		)[ $decision ];
		if ( $email ) {
			Mailer::send_template( $event, $email, $vars );
		}
		/**
		 * Fires after an application decision.
		 *
		 * @param int    $id       Application ID.
		 * @param string $decision Decision.
		 */
		do_action( 'techvenza/application/decided', $id, $decision );
		return true;
	}
}
