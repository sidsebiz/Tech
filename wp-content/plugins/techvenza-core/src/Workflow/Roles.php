<?php
/**
 * Roles and capabilities (matches the prototype's permission matrix).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Workflow;

/**
 * Installs and versions Techvenza roles.
 */
final class Roles {

	private const VERSION = '1.0.0';
	private const OPTION  = 'techvenza_roles_version';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_install' ), 2 );
	}

	/**
	 * Re-sync roles when the definition version changes.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Role labels used in the UI.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			'subscriber'         => __( 'Reader', 'techvenza-core' ),
			'tv_applicant'       => __( 'Contributor Applicant', 'techvenza-core' ),
			'tv_author'          => __( 'Author', 'techvenza-core' ),
			'tv_editor'          => __( 'Editor', 'techvenza-core' ),
			'tv_managing_editor' => __( 'Managing Editor', 'techvenza-core' ),
			'administrator'      => __( 'Administrator', 'techvenza-core' ),
		);
	}

	/**
	 * Capability sets per role.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function caps(): array {
		$reader    = array(
			'read'           => true,
			'tv_manage_self' => true,
		);
		$applicant = $reader + array( 'tv_apply' => true );
		$author    = $reader + array(
			'edit_posts'    => true,
			'delete_posts'  => true,
			'upload_files'  => true,
			'tv_use_portal' => true,
		);
		$editor    = $author + array(
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'read_private_posts'     => true,
			'edit_private_posts'     => true,
			'moderate_comments'      => true,
			'tv_review_posts'        => true,
			'tv_approve_posts'       => true,
			'tv_manage_applications' => true,
			'tv_manage_authors'      => true,
			'tv_view_analytics'      => true,
			'list_users'             => true,
		);
		$managing  = $editor + array(
			'publish_posts'          => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'delete_private_posts'   => true,
			'manage_categories'      => true,
			'tv_publish_posts'       => true,
			'tv_manage_newsletter'   => true,
			'tv_manage_seo'          => true,
			'tv_manage_messages'     => true,
			'edit_pages'             => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'publish_pages'          => true,
		);
		return array(
			'tv_applicant'       => $applicant,
			'tv_author'          => $author,
			'tv_editor'          => $editor,
			'tv_managing_editor' => $managing,
		);
	}

	/**
	 * All Techvenza custom capabilities (granted to administrators).
	 *
	 * @return array<int,string>
	 */
	public static function custom_caps(): array {
		return array( 'tv_apply', 'tv_manage_self', 'tv_use_portal', 'tv_review_posts', 'tv_approve_posts', 'tv_publish_posts', 'tv_manage_applications', 'tv_manage_authors', 'tv_view_analytics', 'tv_manage_newsletter', 'tv_manage_seo', 'tv_manage_messages', 'tv_manage_settings' );
	}

	/**
	 * Create/update roles.
	 */
	public static function install(): void {
		$labels = self::labels();
		foreach ( self::caps() as $role => $caps ) {
			remove_role( $role );
			add_role( $role, $labels[ $role ], $caps );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::custom_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		$subscriber = get_role( 'subscriber' );
		if ( $subscriber ) {
			$subscriber->add_cap( 'tv_apply' );
			$subscriber->add_cap( 'tv_manage_self' );
		}
		update_option( self::OPTION, self::VERSION, true );
	}

	/**
	 * Highest Techvenza role label for a user.
	 *
	 * @param \WP_User $user User.
	 */
	public static function label_for( \WP_User $user ): string {
		$labels = self::labels();
		foreach ( array_reverse( array_keys( $labels ) ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return $labels[ $role ];
			}
		}
		$roles = (array) $user->roles;
		return $roles ? ucfirst( str_replace( '_', ' ', $roles[0] ) ) : '';
	}

	/**
	 * Permission matrix for the Roles screen (capability label → per-role booleans).
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function matrix(): array {
		return array(
			array( __( 'Read and bookmark articles', 'techvenza-core' ), 'read' ),
			array( __( 'Apply to become an author', 'techvenza-core' ), 'tv_apply' ),
			array( __( 'Create and edit own drafts', 'techvenza-core' ), 'edit_posts' ),
			array( __( 'Submit articles for review', 'techvenza-core' ), 'tv_use_portal' ),
			array( __( 'Review and request changes', 'techvenza-core' ), 'tv_review_posts' ),
			array( __( 'Approve articles', 'techvenza-core' ), 'tv_approve_posts' ),
			array( __( 'Publish and schedule', 'techvenza-core' ), 'tv_publish_posts' ),
			array( __( 'Manage author applications', 'techvenza-core' ), 'tv_manage_applications' ),
			array( __( 'Manage categories, tags and media', 'techvenza-core' ), 'manage_categories' ),
			array( __( 'Manage newsletter campaigns', 'techvenza-core' ), 'tv_manage_newsletter' ),
			array( __( 'Manage users, roles and settings', 'techvenza-core' ), 'tv_manage_settings' ),
		);
	}
}
