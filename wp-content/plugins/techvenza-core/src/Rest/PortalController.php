<?php
/**
 * REST: author portal actions (profile, photo upload, draft deletion).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Support\Render as R;

/**
 * Portal endpoints.
 */
final class PortalController {

	/**
	 * Routes.
	 */
	public static function routes(): void {
		$portal = static fn(): bool => current_user_can( 'tv_use_portal' );
		register_rest_route(
			Routes::NS,
			'/portal/profile',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'profile' ),
				'permission_callback' => $portal,
			)
		);
		register_rest_route(
			Routes::NS,
			'/portal/photo',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'photo' ),
				'permission_callback' => static fn(): bool => current_user_can( 'tv_use_portal' ) && current_user_can( 'upload_files' ),
			)
		);
		register_rest_route(
			Routes::NS,
			'/portal/drafts/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'delete_draft' ),
				'permission_callback' => static fn( \WP_REST_Request $r ): bool => current_user_can( 'delete_post', (int) $r['id'] ),
			)
		);
	}

	/**
	 * Save the public author profile.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function profile( \WP_REST_Request $request ) {
		$user   = get_current_user_id();
		$name   = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$title  = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$bio    = sanitize_textarea_field( (string) $request->get_param( 'bio' ) );
		$exp    = array_slice( array_values( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $request->get_param( 'expertise' ) ) ) ) ) ), 0, 5 );
		$errors = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Enter your name.', 'techvenza-core' );
		}
		if ( '' === $title ) {
			$errors['title'] = __( 'Add your professional title.', 'techvenza-core' );
		}
		if ( mb_strlen( $bio ) < 80 || mb_strlen( $bio ) > 600 ) {
			$errors['bio'] = __( 'Add a biography between 80 and 600 characters.', 'techvenza-core' );
		}
		if ( ! $exp ) {
			$errors['expertise'] = __( 'Add at least one area of expertise.', 'techvenza-core' );
		}
		$urls = array();
		foreach ( array(
			'website'  => 'tv_website',
			'linkedin' => 'tv_linkedin',
			'x'        => 'tv_x',
		) as $field => $key ) {
			$v = trim( (string) $request->get_param( $field ) );
			if ( '' !== $v && ! wp_http_validate_url( $v ) ) {
				$errors[ $field ] = __( 'Enter a full URL starting with https://', 'techvenza-core' );
			}
			$urls[ $key ] = esc_url_raw( $v );
		}
		if ( $errors ) {
			return new \WP_Error(
				'tv_invalid',
				__( 'Please fix the highlighted fields.', 'techvenza-core' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}
		wp_update_user(
			array(
				'ID'           => $user,
				'display_name' => $name,
				'description'  => $bio,
			)
		);
		update_user_meta( $user, 'tv_title', $title );
		update_user_meta( $user, 'tv_expertise', $exp );
		foreach ( $urls as $k => $v ) {
			update_user_meta( $user, $k, $v );
		}
		$photo = absint( $request->get_param( 'photo' ) );
		if ( $photo && 'attachment' === get_post_type( $photo ) && (int) get_post_field( 'post_author', $photo ) === $user ) {
			update_user_meta( $user, 'tv_photo', $photo );
		}
		UserMeta::flush( $user );
		$au = UserMeta::author( $user );
		return rest_ensure_response(
			array(
				'message' => __( 'Profile saved. Your public author page is updated.', 'techvenza-core' ),
				'preview' => $au ? R::author_card( $au ) : '',
			)
		);
	}

	/**
	 * Upload a profile photo (validated type and size).
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function photo( \WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['photo'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'tv_invalid', __( 'Choose an image to upload.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] );
		if ( ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) || (int) $file['size'] > 3 * MB_IN_BYTES ) {
			return new \WP_Error( 'tv_invalid', __( 'Use a JPG, PNG or WebP under 3 MB.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload( $file, 0, wp_get_current_user()->display_name );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', '' );
		update_user_meta( get_current_user_id(), 'tv_photo', $id );
		UserMeta::flush( get_current_user_id() );
		return rest_ensure_response(
			array(
				'id'  => $id,
				'url' => wp_get_attachment_image_url( $id, 'thumbnail' ),
			)
		);
	}

	/**
	 * Delete one of the author's drafts (moves it to the trash).
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_draft( \WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'post' !== $post->post_type || 'draft' !== $post->post_status ) {
			return new \WP_Error( 'tv_invalid', __( 'Only drafts can be deleted.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		wp_trash_post( $post->ID );
		return rest_ensure_response( array( 'message' => __( 'Draft deleted.', 'techvenza-core' ) ) );
	}
}
