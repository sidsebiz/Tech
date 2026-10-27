<?php
/**
 * Roles & permissions: editable capability matrix for the Techvenza roles, with reset to defaults.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Workflow\Roles;

/**
 * Roles screen.
 */
final class RolesScreen {

	/**
	 * Editable roles (administrators always keep every capability).
	 *
	 * @return array<int,string>
	 */
	private static function roles(): array {
		return array( 'subscriber', 'tv_applicant', 'tv_author', 'tv_editor', 'tv_managing_editor' );
	}

	/**
	 * Capabilities granted together with a matrix row's primary capability.
	 *
	 * @param string $cap Primary capability.
	 * @return array<int,string>
	 */
	public static function bundle( string $cap ): array {
		$bundles = array(
			'edit_posts'        => array( 'edit_posts', 'delete_posts', 'upload_files' ),
			'tv_review_posts'   => array( 'tv_review_posts', 'edit_others_posts', 'edit_published_posts', 'read_private_posts', 'edit_private_posts', 'moderate_comments' ),
			'tv_publish_posts'  => array( 'tv_publish_posts', 'publish_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts' ),
			'manage_categories' => array( 'manage_categories', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages' ),
		);
		return $bundles[ $cap ] ?? array( $cap );
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$labels = Roles::labels();
		$counts = count_users();
		$head   = '';
		foreach ( array_merge( self::roles(), array( 'administrator' ) ) as $role ) {
			$head .= sprintf(
				'<th scope="col" style="text-align:center">%s<div class="meta">%s</div></th>',
				esc_html( $labels[ $role ] ?? $role ),
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user', '%d users', (int) ( $counts['avail_roles'][ $role ] ?? 0 ), 'techvenza-core' ), (int) ( $counts['avail_roles'][ $role ] ?? 0 ) ) )
			);
		}
		$rows = '';
		foreach ( Roles::matrix() as [ $label, $cap ] ) {
			$cells = '';
			foreach ( self::roles() as $role ) {
				$obj    = get_role( $role );
				$on     = $obj && $obj->has_cap( $cap );
				$locked = 'read' === $cap;
				$cells .= sprintf(
					'<td style="text-align:center"><input type="checkbox" name="caps[%1$s][]" value="%2$s" aria-label="%3$s"%4$s%5$s>%6$s</td>',
					esc_attr( $role ),
					esc_attr( $cap ),
					/* translators: 1: capability, 2: role */
					esc_attr( sprintf( __( '%1$s — %2$s', 'techvenza-core' ), $label, $labels[ $role ] ?? $role ) ),
					checked( $on, true, false ),
					$locked ? ' disabled' : '',
					$locked && $on ? '<input type="hidden" name="caps[' . esc_attr( $role ) . '][]" value="read">' : ''
				);
			}
			$cells .= '<td style="text-align:center"><input type="checkbox" checked disabled aria-label="' . esc_attr( $label . ' — ' . ( $labels['administrator'] ?? 'Administrator' ) ) . '"></td>';
			$rows  .= sprintf( '<tr><th scope="row">%s<div class="meta"><code>%s</code></div></th>%s</tr>', esc_html( $label ), esc_html( implode( ', ', self::bundle( $cap ) ) ), $cells );
		}
		$form = static fn( string $op, string $inner, string $extra = '' ): string => sprintf(
			'<form method="post" action="%s"%s>%s<input type="hidden" name="action" value="techvenza_roles"><input type="hidden" name="op" value="%s">%s</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$extra,
			wp_nonce_field( 'techvenza_roles', '_wpnonce', true, false ),
			esc_attr( $op ),
			$inner
		);
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Roles & permissions', 'techvenza-core' ), '' ),
			),
			__( 'Roles & permissions', 'techvenza-core' ),
			esc_html__( 'What each role can do. Each row grants the related WordPress capabilities shown beneath it. Administrators always have every permission.', 'techvenza-core' ),
			$form( 'reset', '<button type="submit" class="button">' . esc_html__( 'Reset to defaults', 'techvenza-core' ) . '</button>', ' data-confirm="' . esc_attr__( 'Reset every Techvenza role to its default capabilities?', 'techvenza-core' ) . '"' )
		) . $form(
			'save',
			sprintf(
				'<div class="table-wrap"><table class="widefat striped matrix"><thead><tr><th scope="col">%s</th>%s</tr></thead><tbody>%s</tbody></table></div><p><button type="submit" class="button button-primary">%s</button></p>',
				esc_html__( 'Permission', 'techvenza-core' ),
				$head,
				$rows,
				esc_html__( 'Save permissions', 'techvenza-core' )
			)
		);
	}

	/**
	 * Save (admin-post).
	 */
	public static function handle(): void {
		check_admin_referer( 'techvenza_roles' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit roles.', 'techvenza-core' ), 403 );
		}
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( 'reset' === $op ) {
			Roles::install();
			Admin::back( 'reset' );
		}
		$posted = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? map_deep( wp_unslash( $_POST['caps'] ), 'sanitize_key' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$known  = array_map( static fn( $row ) => $row[1], Roles::matrix() );
		foreach ( self::roles() as $role ) {
			$obj = get_role( $role );
			if ( ! $obj ) {
				continue;
			}
			$want = array_intersect( (array) ( $posted[ $role ] ?? array() ), $known );
			foreach ( $known as $cap ) {
				if ( 'read' === $cap ) {
					continue;
				}
				foreach ( self::bundle( $cap ) as $c ) {
					if ( in_array( $cap, $want, true ) ) {
						$obj->add_cap( $c );
					} elseif ( $obj->has_cap( $c ) && ! self::kept_by_other( $c, $want, $cap ) ) {
						$obj->remove_cap( $c );
					}
				}
			}
		}
		Admin::back( 'roles' );
	}

	/**
	 * Whether a bundled capability is still required by another granted row.
	 *
	 * @param string            $c    Capability.
	 * @param array<int,string> $want Granted rows.
	 * @param string            $current Row being removed.
	 */
	private static function kept_by_other( string $c, array $want, string $current ): bool {
		foreach ( $want as $row ) {
			if ( $row !== $current && in_array( $c, self::bundle( $row ), true ) ) {
				return true;
			}
		}
		return false;
	}
}
