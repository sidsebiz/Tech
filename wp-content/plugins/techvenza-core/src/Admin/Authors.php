<?php
/**
 * Authors: roles, account status (suspend / reactivate), profile visibility, featured and staff flags.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\Query;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Roles;

/**
 * Authors screen.
 */
final class Authors {

	/**
	 * Flags editable from the list.
	 *
	 * @return array<string,string>
	 */
	private static function flags(): array {
		return array(
			'tv_visible'  => __( 'Profile visible', 'techvenza-core' ),
			'tv_featured' => __( 'Featured', 'techvenza-core' ),
			'tv_staff'    => __( 'Staff', 'techvenza-core' ),
		);
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$users = get_users(
			array(
				'role__in' => array( 'tv_author', 'tv_editor', 'tv_managing_editor', 'author', 'editor', 'administrator' ),
				'orderby'  => 'display_name',
				'number'   => 300,
			)
		);
		$rows  = '';
		foreach ( $users as $u ) {
			$au        = UserMeta::author( $u );
			$suspended = 'suspended' === get_user_meta( $u->ID, 'tv_account_status', true );
			$toggles   = '';
			foreach ( self::flags() as $key => $label ) {
				$on       = 'tv_visible' === $key ? ( $au['visible'] ?? true ) : (bool) get_user_meta( $u->ID, $key, true );
				$toggles .= self::button(
					$u->ID,
					'toggle',
					$key,
					/* translators: 1: flag label, 2: state */
					sprintf( '%1$s: %2$s', $label, $on ? __( 'on', 'techvenza-core' ) : __( 'off', 'techvenza-core' ) ),
					$on ? 'button button-small button-primary' : 'button button-small',
					/* translators: 1: flag, 2: name */
					sprintf( __( 'Toggle %1$s for %2$s', 'techvenza-core' ), $label, $u->display_name )
				);
			}
			$rows .= sprintf(
				'<tr><td><div class="row" style="flex-wrap:nowrap">%1$s<span><strong><a href="%2$s">%3$s</a></strong><div class="meta">%4$s</div></span></div></td><td>%5$s</td><td>%6$d</td><td>%7$s</td><td><div class="row" style="gap:4px">%8$s</div></td><td><div class="row" style="gap:4px"><a class="button button-small" href="%9$s">%10$s</a>%11$s</div></td></tr>',
				R::avatar( $au, 'sm' ),
				esc_url( get_edit_user_link( $u->ID ) ),
				esc_html( $u->display_name ),
				esc_html( (string) ( $au['title'] ?? '' ) ),
				esc_html( Roles::label_for( $u ) ),
				Query::author_count( $u->ID ),
				R::badge( $suspended ? 'suspended' : 'active' ),
				$toggles,
				esc_url( get_author_posts_url( $u->ID ) ),
				esc_html__( 'Profile', 'techvenza-core' ),
				get_current_user_id() === $u->ID || user_can( $u, 'manage_options' ) ? '' : self::button(
					$u->ID,
					$suspended ? 'reactivate' : 'suspend',
					'',
					$suspended ? __( 'Reactivate', 'techvenza-core' ) : __( 'Suspend', 'techvenza-core' ),
					$suspended ? 'button button-small' : 'button button-small tva-danger',
					/* translators: %s: name */
					sprintf( $suspended ? __( 'Reactivate %s', 'techvenza-core' ) : __( 'Suspend %s', 'techvenza-core' ), $u->display_name ),
					! $suspended
				)
			);
		}
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Authors', 'techvenza-core' ), '' ),
			),
			__( 'Authors', 'techvenza-core' ),
			esc_html__( 'Roles, account status and profile visibility for contributors and staff. Suspended accounts keep their published articles but lose portal access.', 'techvenza-core' ),
			sprintf( '<a class="button" href="%s">%s</a><a class="button" href="%s">%s</a>', esc_url( Admin::url( 'techvenza-applications' ) ), esc_html__( 'Applications', 'techvenza-core' ), esc_url( admin_url( 'user-new.php' ) ), esc_html__( 'Add user', 'techvenza-core' ) )
		) . sprintf(
			'<div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table></div>',
			esc_html__( 'Author', 'techvenza-core' ),
			esc_html__( 'Role', 'techvenza-core' ),
			esc_html__( 'Published', 'techvenza-core' ),
			esc_html__( 'Account', 'techvenza-core' ),
			esc_html__( 'Flags', 'techvenza-core' ),
			esc_html__( 'Actions', 'techvenza-core' ),
			$rows
		);
	}

	/**
	 * One-click action form.
	 *
	 * @param int    $user    User.
	 * @param string $op      toggle|suspend|reactivate.
	 * @param string $flag    Meta key for toggles.
	 * @param string $label   Visible label.
	 * @param string $class   Button class.
	 * @param string $aria    Accessible label.
	 * @param bool   $confirm Ask for confirmation (admin.js).
	 */
	private static function button( int $user, string $op, string $flag, string $label, string $class, string $aria, bool $confirm = false ): string {
		return sprintf(
			'<form method="post" action="%1$s" style="display:inline"%2$s>%3$s<input type="hidden" name="action" value="techvenza_author"><input type="hidden" name="user" value="%4$d"><input type="hidden" name="op" value="%5$s"><input type="hidden" name="flag" value="%6$s"><button type="submit" class="%7$s" aria-label="%8$s">%9$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$confirm ? ' data-confirm="' . esc_attr__( 'Suspend this account? Published articles stay live; portal access is removed.', 'techvenza-core' ) . '"' : '',
			wp_nonce_field( 'techvenza_author_' . $user, '_wpnonce', true, false ),
			$user,
			esc_attr( $op ),
			esc_attr( $flag ),
			esc_attr( $class ),
			esc_attr( $aria . ' — ' . $label ),
			esc_html( $label )
		);
	}

	/**
	 * Handle an action (admin-post).
	 */
	public static function handle(): void {
		$user = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		check_admin_referer( 'techvenza_author_' . $user );
		if ( ! current_user_can( 'tv_manage_authors' ) || ! get_userdata( $user ) ) {
			wp_die( esc_html__( 'You do not have permission to manage authors.', 'techvenza-core' ), 403 );
		}
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$flag = isset( $_POST['flag'] ) ? sanitize_key( wp_unslash( $_POST['flag'] ) ) : '';
		if ( 'toggle' === $op && isset( self::flags()[ $flag ] ) ) {
			$au  = UserMeta::author( $user );
			$cur = 'tv_visible' === $flag ? (bool) ( $au['visible'] ?? true ) : (bool) get_user_meta( $user, $flag, true );
			update_user_meta( $user, $flag, $cur ? '0' : '1' );
		} elseif ( in_array( $op, array( 'suspend', 'reactivate' ), true ) && ! user_can( $user, 'manage_options' ) && get_current_user_id() !== $user ) {
			if ( 'suspend' === $op ) {
				update_user_meta( $user, 'tv_account_status', 'suspended' );
				\WP_Session_Tokens::get_instance( $user )->destroy_all();
			} else {
				delete_user_meta( $user, 'tv_account_status' );
			}
		}
		UserMeta::flush( $user );
		Admin::back( 'author' );
	}
}
