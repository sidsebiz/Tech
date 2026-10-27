<?php
/**
 * Contact messages inbox: list by status/type, read a message, mark handled, erase.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Forms\Forms;
use Techvenza\Core\Support\Render as R;

/**
 * Messages screen.
 */
final class Messages {

	public const SLUG = 'techvenza-messages';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_techvenza_message', array( self::class, 'handle' ) );
	}

	/**
	 * Unhandled count (menu badge).
	 */
	public static function unread(): int {
		$cached = wp_cache_get( 'tv_messages_unread', 'techvenza' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$n = count(
			get_posts(
				array(
					'post_type'      => PostTypes::MESSAGE,
					'post_status'    => 'private',
					'fields'         => 'ids',
					'posts_per_page' => 200,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => 'tv_handled',
							'value' => '0',
						),
					),
				)
			)
		);
		wp_cache_set( 'tv_messages_unread', $n, 'techvenza', 5 * MINUTE_IN_SECONDS );
		return $n;
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view.
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'open';
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		// phpcs:enable
		$head = Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Messages', 'techvenza-core' ), $id ? Admin::url( self::SLUG ) : '' ),
			),
			__( 'Contact messages', 'techvenza-core' ),
			esc_html__( 'Messages from the contact form, routed by inquiry type. Reply from your email client; messages are deleted automatically after the retention period in Settings → Forms & Spam.', 'techvenza-core' )
		);
		if ( $id && PostTypes::MESSAGE === get_post_type( $id ) ) {
			return $head . self::detail( $id );
		}
		$meta = array(
			array(
				'key'   => 'tv_handled',
				'value' => 'handled' === $view ? '1' : '0',
			),
		);
		if ( $type && isset( Forms::types()[ $type ] ) ) {
			$meta[] = array(
				'key'   => 'tv_type',
				'value' => $type,
			);
		}
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::MESSAGE,
				'post_status'    => 'private',
				'posts_per_page' => 100,
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$types = Forms::types();
		$nav   = '';
		foreach ( array(
			'open'    => __( 'Open', 'techvenza-core' ),
			'handled' => __( 'Handled', 'techvenza-core' ),
		) as $k => $label ) {
			$nav .= sprintf( '<a class="tab" href="%s"%s>%s%s</a>', esc_url( Admin::url( self::SLUG, array( 'view' => $k ) ) ), $k === $view ? ' aria-current="page"' : '', esc_html( $label ), 'open' === $k && self::unread() ? '<span class="count">' . (int) self::unread() . '</span>' : '' );
		}
		$rows = '';
		foreach ( $posts as $p ) {
			$t     = (string) get_post_meta( $p->ID, 'tv_type', true );
			$rows .= sprintf(
				'<tr><td><a href="%s"><strong>%s</strong></a><div class="meta">%s</div></td><td>%s<div class="meta">%s</div></td><td>%s</td><td>%s</td></tr>',
				esc_url( Admin::url( self::SLUG, array( 'id' => $p->ID ) ) ),
				esc_html( get_the_title( $p ) ),
				esc_html( wp_trim_words( $p->post_content, 16 ) ),
				esc_html( (string) get_post_meta( $p->ID, 'tv_name', true ) ),
				esc_html( (string) get_post_meta( $p->ID, 'tv_email', true ) ),
				esc_html( explode( ' —', $types[ $t ] ?? $t )[0] ),
				esc_html( R::date( get_the_date( 'Y-m-d', $p ) ) )
			);
		}
		$opts = '<option value="">' . esc_html__( 'All types', 'techvenza-core' ) . '</option>';
		foreach ( $types as $k => $label ) {
			$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $type, $k, false ), esc_html( explode( ' —', $label )[0] ) );
		}
		$filter = sprintf( '<form method="get" class="bulk"><input type="hidden" name="page" value="%s"><input type="hidden" name="view" value="%s"><label class="sr-only" for="tvMsgType">%s</label><select id="tvMsgType" name="type">%s</select><button class="button" type="submit">%s</button></form>', esc_attr( self::SLUG ), esc_attr( $view ), esc_html__( 'Inquiry type', 'techvenza-core' ), $opts, esc_html__( 'Filter', 'techvenza-core' ) );
		$table  = $rows
			? sprintf( '<table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table>', esc_html__( 'Subject', 'techvenza-core' ), esc_html__( 'From', 'techvenza-core' ), esc_html__( 'Type', 'techvenza-core' ), esc_html__( 'Received', 'techvenza-core' ), $rows )
			: R::empty_state( 'mail', 'handled' === $view ? __( 'No handled messages', 'techvenza-core' ) : __( 'Inbox zero', 'techvenza-core' ), __( 'New contact form messages appear here.', 'techvenza-core' ) );
		return $head . sprintf( '<div class="table-wrap"><nav class="tabs" aria-label="%s">%s</nav>%s%s</div>', esc_attr__( 'Message status', 'techvenza-core' ), $nav, $filter, $table );
	}

	/**
	 * Single message.
	 *
	 * @param int $id Message.
	 */
	private static function detail( int $id ): string {
		$p       = get_post( $id );
		$m       = static fn( string $k ): string => (string) get_post_meta( $id, $k, true );
		$types   = Forms::types();
		$handled = '1' === $m( 'tv_handled' );
		$button  = static fn( string $op, string $label, string $cls, string $confirm = '' ): string => sprintf(
			'<form method="post" action="%1$s"%2$s>%3$s<input type="hidden" name="action" value="techvenza_message"><input type="hidden" name="id" value="%4$d"><input type="hidden" name="op" value="%5$s"><button class="%6$s" type="submit" style="width:100%%">%7$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '',
			wp_nonce_field( 'techvenza_message_' . $id, '_wpnonce', true, false ),
			$id,
			esc_attr( $op ),
			esc_attr( $cls ),
			esc_html( $label )
		);
		return sprintf(
			'<div class="review-grid"><section class="panel"><h2 class="panel-title">%1$s</h2><p class="meta">%2$s · %3$s</p><div style="white-space:pre-wrap;font-size:14px;line-height:1.6;margin-top:16px">%4$s</div></section>
			<aside><section class="panel"><h2 class="panel-title">%5$s</h2><p><strong>%6$s</strong><br><a href="%7$s">%8$s</a></p><p class="meta">%9$s<br>%10$s</p><div style="display:grid;gap:8px"><a class="button button-primary" style="text-align:center" href="%7$s">%11$s</a>%12$s%13$s</div></section></aside></div>',
			esc_html( get_the_title( $p ) ),
			esc_html( explode( ' —', $types[ $m( 'tv_type' ) ] ?? $m( 'tv_type' ) )[0] ),
			esc_html( R::date( get_the_date( 'Y-m-d', $p ), true ) . ' ' . get_the_date( 'H:i', $p ) ),
			esc_html( (string) ( $p ? $p->post_content : '' ) ),
			esc_html__( 'Sender', 'techvenza-core' ),
			esc_html( $m( 'tv_name' ) ),
			esc_url( 'mailto:' . $m( 'tv_email' ) . '?subject=' . rawurlencode( 'Re: ' . get_the_title( $p ) ) ),
			esc_html( $m( 'tv_email' ) ),
			/* translators: %s: email */
			esc_html( sprintf( __( 'Routed to %s', 'techvenza-core' ), $m( 'tv_routed_to' ) ) ),
			/* translators: %s: datetime */
			esc_html( sprintf( __( 'Consent given %s', 'techvenza-core' ), $m( 'tv_consent_at' ) ) ),
			esc_html__( 'Reply by email', 'techvenza-core' ),
			$button( $handled ? 'reopen' : 'handled', $handled ? __( 'Reopen', 'techvenza-core' ) : __( 'Mark as handled', 'techvenza-core' ), 'button' ),
			$button( 'delete', __( 'Erase message', 'techvenza-core' ), 'button tva-danger', __( 'Permanently erase this message?', 'techvenza-core' ) )
		);
	}

	/**
	 * Actions (admin-post).
	 */
	public static function handle(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'techvenza_message_' . $id );
		if ( ! current_user_can( 'tv_manage_messages' ) || PostTypes::MESSAGE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'You do not have permission to manage messages.', 'techvenza-core' ), 403 );
		}
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		wp_cache_delete( 'tv_messages_unread', 'techvenza' );
		if ( 'delete' === $op ) {
			wp_delete_post( $id, true );
			Admin::back( 'done', __( 'Message erased.', 'techvenza-core' ), Admin::url( self::SLUG ) );
		}
		update_post_meta( $id, 'tv_handled', 'handled' === $op ? '1' : '0' );
		Admin::back( 'done', '', Admin::url( self::SLUG ) );
	}

	/**
	 * Delete messages older than the retention period (cron).
	 */
	public static function purge(): int {
		$days = max( 1, (int) \Techvenza\Core\Settings\Options::get( 'contact_retention_days', 90 ) );
		$old  = get_posts(
			array(
				'post_type'      => PostTypes::MESSAGE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'date_query'     => array( array( 'before' => $days . ' days ago' ) ),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
		return count( $old );
	}
}
