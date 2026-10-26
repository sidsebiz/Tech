<?php
/**
 * Author / reader profile meta, profile screen fields and a normalised author accessor.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * User meta registration and helpers.
 */
final class UserMeta {

	/**
	 * Public profile keys editable by the user and by editors.
	 */
	private const PROFILE = array(
		'tv_title'      => 'string',
		'tv_expertise'  => 'array',
		'tv_categories' => 'array',
		'tv_website'    => 'string',
		'tv_linkedin'   => 'string',
		'tv_x'          => 'string',
		'tv_photo'      => 'integer',
	);

	/**
	 * Keys only editors / admins can change.
	 */
	private const EDITORIAL = array(
		'tv_staff'          => 'boolean',
		'tv_verified'       => 'string',
		'tv_visible'        => 'boolean',
		'tv_featured'       => 'boolean',
		'tv_account_status' => 'string',
	);

	/**
	 * Private reader preferences (exposed only through Techvenza REST routes).
	 */
	private const PRIVATE_KEYS = array( 'tv_bookmarks', 'tv_topics', 'tv_email_prefs', 'tv_nl_frequency' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'show_user_profile', array( self::class, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( self::class, 'profile_fields' ) );
		add_action( 'personal_options_update', array( self::class, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save_profile' ) );
		add_filter( 'pre_get_avatar_data', array( self::class, 'avatar' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ) );
		add_action( 'profile_update', array( self::class, 'flush' ) );
		add_action( 'updated_user_meta', array( self::class, 'flush_meta' ), 10, 2 );
	}

	/**
	 * Register meta for REST (profile fields only).
	 */
	public static function register_meta(): void {
		foreach ( self::PROFILE + self::EDITORIAL as $key => $type ) {
			$editorial = isset( self::EDITORIAL[ $key ] );
			register_meta(
				'user',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => 'array' === $type ? array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					) : true,
					'sanitize_callback' => static fn( $v ) => self::sanitize( $key, $v ),
					'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) use ( $editorial ) {
						if ( $editorial ) {
							return current_user_can( 'tv_manage_authors' ) || current_user_can( 'edit_users' );
						}
						return get_current_user_id() === (int) $user_id || current_user_can( 'edit_user', (int) $user_id );
					},
				)
			);
		}
	}

	/**
	 * Sanitise one key.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( string $key, $value ) {
		$type = self::PROFILE[ $key ] ?? self::EDITORIAL[ $key ] ?? 'string';
		if ( 'array' === $type ) {
			if ( is_string( $value ) ) {
				$value = explode( ',', $value );
			}
			$value = array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', (array) $value ) ) ) );
			return 'tv_categories' === $key ? array_map( 'sanitize_title', $value ) : array_slice( $value, 0, 8 );
		}
		if ( 'boolean' === $type ) {
			return (bool) $value;
		}
		if ( 'integer' === $type ) {
			return absint( $value );
		}
		if ( in_array( $key, array( 'tv_website', 'tv_linkedin', 'tv_x' ), true ) ) {
			return esc_url_raw( trim( (string) $value ) );
		}
		if ( 'tv_account_status' === $key ) {
			return in_array( $value, array( 'active', 'suspended', 'pending' ), true ) ? $value : 'active';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Initials from a display name.
	 *
	 * @param string $name Name.
	 */
	public static function initials( string $name ): string {
		$parts = preg_split( '/\s+/', trim( $name ) );
		$parts = $parts ? $parts : array( '?' );
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * Normalised author data (mirrors the prototype AUTHORS objects).
	 *
	 * @param \WP_User|int|string $user User, ID or nicename.
	 * @return array<string,mixed>|null
	 */
	public static function author( $user ): ?array {
		if ( is_string( $user ) && ! is_numeric( $user ) ) {
			$user = get_user_by( 'slug', $user );
		} elseif ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', (int) $user );
		}
		if ( ! $user instanceof \WP_User ) {
			return null;
		}
		$cached = wp_cache_get( 'author_' . $user->ID, 'techvenza' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$m       = static fn( string $k ) => get_user_meta( $user->ID, $k, true );
		$cats    = array_values( array_filter( (array) $m( 'tv_categories' ) ) );
		$palette = array( array( '#7C3AED', '#2563EB' ), array( '#0D9488', '#059669' ), array( '#F59E0B', '#EA580C' ), array( '#E11D48', '#7C3AED' ), array( '#2563EB', '#DB2777' ), array( '#4F46E5', '#0284C7' ) );
		$pair    = $palette[ $user->ID % count( $palette ) ];
		if ( $cats ) {
			$c1 = CategoryMeta::get( $cats[0] );
			$c2 = isset( $cats[1] ) ? CategoryMeta::get( $cats[1] ) : null;
			if ( $c1 ) {
				$pair = array( $c1['color'], $c2 ? $c2['color'] : $pair[1] );
			}
		}
		$photo   = (int) $m( 'tv_photo' );
		$visible = $m( 'tv_visible' );
		$roles   = (array) $user->roles;
		$data    = array(
			'id'        => $user->ID,
			'slug'      => $user->user_nicename,
			'name'      => $user->display_name,
			'first'     => $user->first_name ? $user->first_name : strtok( $user->display_name, ' ' ),
			'title'     => (string) $m( 'tv_title' ),
			'bio'       => (string) $user->description,
			'initials'  => self::initials( $user->display_name ),
			'a1'        => $pair[0],
			'a2'        => $pair[1],
			'cats'      => $cats,
			'expertise' => array_values( array_filter( (array) $m( 'tv_expertise' ) ) ),
			'staff'     => (bool) $m( 'tv_staff' ),
			'verified'  => (string) $m( 'tv_verified' ),
			'website'   => (string) ( $m( 'tv_website' ) ? $m( 'tv_website' ) : $user->user_url ),
			'linkedin'  => (string) $m( 'tv_linkedin' ),
			'x'         => (string) $m( 'tv_x' ),
			'photo'     => $photo ? (string) wp_get_attachment_image_url( $photo, 'techvenza-square' ) : '',
			'visible'   => metadata_exists( 'user', $user->ID, 'tv_visible' ) ? (bool) $visible : true,
			'featured'  => (bool) $m( 'tv_featured' ),
			'status'    => $m( 'tv_account_status' ) ? (string) $m( 'tv_account_status' ) : 'active',
			'joined'    => substr( $user->user_registered, 0, 10 ),
			'role'      => $roles ? $roles[0] : '',
			'url'       => get_author_posts_url( $user->ID, $user->user_nicename ),
		);
		wp_cache_set( 'author_' . $user->ID, $data, 'techvenza', HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Clear accessor cache.
	 *
	 * @param int $user_id User ID.
	 */
	public static function flush( int $user_id ): void {
		wp_cache_delete( 'author_' . $user_id, 'techvenza' );
	}

	/**
	 * Clear cache when meta changes.
	 *
	 * @param int $meta_id Meta ID.
	 * @param int $user_id User ID.
	 */
	public static function flush_meta( $meta_id, $user_id ): void {
		self::flush( (int) $user_id );
	}

	/**
	 * Use the uploaded profile photo instead of Gravatar.
	 *
	 * @param array<string,mixed> $args        Avatar args.
	 * @param mixed               $id_or_email User identifier.
	 * @return array<string,mixed>
	 */
	public static function avatar( array $args, $id_or_email ): array {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof \WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof \WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof \WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		}
		$photo = $user_id ? (int) get_user_meta( $user_id, 'tv_photo', true ) : 0;
		if ( $photo ) {
			$url = wp_get_attachment_image_url( $photo, array( (int) $args['size'], (int) $args['size'] ) );
			if ( $url ) {
				$args['url']          = $url;
				$args['found_avatar'] = true;
			}
		}
		return $args;
	}

	/**
	 * Media picker on profile screens.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function admin_assets( string $hook ): void {
		if ( in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'techvenza-term-meta', TECHVENZA_CORE_URL . 'assets/js/admin-term.js', array( 'jquery' ), TECHVENZA_CORE_VERSION, true );
			wp_localize_script( 'techvenza-term-meta', 'techvenzaTerm', array( 'choose' => __( 'Choose photo', 'techvenza-core' ) ) );
		}
	}

	/**
	 * Profile screen section.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public static function profile_fields( \WP_User $user ): void {
		$m         = static fn( string $k ) => get_user_meta( $user->ID, $k, true );
		$editorial = current_user_can( 'tv_manage_authors' ) || current_user_can( 'edit_users' );
		$photo     = (int) $m( 'tv_photo' );
		$img       = $photo ? (string) wp_get_attachment_image_url( $photo, 'thumbnail' ) : '';
		wp_nonce_field( 'techvenza_profile', 'techvenza_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'Techvenza author profile', 'techvenza-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="tv_title"><?php esc_html_e( 'Professional title', 'techvenza-core' ); ?></label></th>
				<td><input type="text" class="regular-text" id="tv_title" name="tv_title" value="<?php echo esc_attr( (string) $m( 'tv_title' ) ); ?>"></td></tr>
			<tr><th><label for="tv_expertise"><?php esc_html_e( 'Expertise', 'techvenza-core' ); ?></label></th>
				<td><input type="text" class="regular-text" id="tv_expertise" name="tv_expertise" value="<?php echo esc_attr( implode( ', ', (array) $m( 'tv_expertise' ) ) ); ?>"><p class="description"><?php esc_html_e( 'Comma-separated, up to 8.', 'techvenza-core' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Preferred categories', 'techvenza-core' ); ?></th>
				<td><fieldset>
				<?php
				foreach ( CategoryMeta::all() as $cat ) {
					printf(
						'<label style="display:inline-block;min-width:220px;margin:0 8px 6px 0"><input type="checkbox" name="tv_categories[]" value="%1$s" %2$s> %3$s</label>',
						esc_attr( $cat['slug'] ),
						checked( in_array( $cat['slug'], (array) $m( 'tv_categories' ), true ), true, false ),
						esc_html( $cat['name'] )
					);
				}
				?>
				<input type="hidden" name="tv_categories[]" value=""></fieldset></td></tr>
			<tr><th><label for="tv_website"><?php esc_html_e( 'Website', 'techvenza-core' ); ?></label></th>
				<td><input type="url" class="regular-text" id="tv_website" name="tv_website" value="<?php echo esc_attr( (string) $m( 'tv_website' ) ); ?>"></td></tr>
			<tr><th><label for="tv_linkedin">LinkedIn</label></th>
				<td><input type="url" class="regular-text" id="tv_linkedin" name="tv_linkedin" value="<?php echo esc_attr( (string) $m( 'tv_linkedin' ) ); ?>"></td></tr>
			<tr><th><label for="tv_x">X / Twitter</label></th>
				<td><input type="url" class="regular-text" id="tv_x" name="tv_x" value="<?php echo esc_attr( (string) $m( 'tv_x' ) ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Profile photo', 'techvenza-core' ); ?></th>
				<td><div class="tv-media-field"><input type="hidden" name="tv_photo" value="<?php echo esc_attr( (string) $photo ); ?>">
					<img src="<?php echo esc_url( $img ); ?>" alt="" style="max-width:96px;border-radius:50%;<?php echo $img ? 'display:block' : 'display:none'; ?>">
					<p><button type="button" class="button tv-media-pick"><?php esc_html_e( 'Choose photo', 'techvenza-core' ); ?></button> <button type="button" class="button-link tv-media-clear"><?php esc_html_e( 'Remove', 'techvenza-core' ); ?></button></p></div></td></tr>
			<?php if ( $editorial ) : ?>
			<tr><th><?php esc_html_e( 'Editorial settings', 'techvenza-core' ); ?></th>
				<td><fieldset>
					<input type="hidden" name="tv_editorial_present" value="1">
					<label><input type="checkbox" name="tv_staff" value="1" <?php checked( (bool) $m( 'tv_staff' ) ); ?>> <?php esc_html_e( 'Staff (otherwise contributor)', 'techvenza-core' ); ?></label><br>
					<label><input type="checkbox" name="tv_featured" value="1" <?php checked( (bool) $m( 'tv_featured' ) ); ?>> <?php esc_html_e( 'Featured author', 'techvenza-core' ); ?></label><br>
					<label><input type="checkbox" name="tv_visible" value="1" <?php checked( '' === $m( 'tv_visible' ) || (bool) $m( 'tv_visible' ) ); ?>> <?php esc_html_e( 'Public profile visible', 'techvenza-core' ); ?></label><br>
					<label><?php esc_html_e( 'Account status', 'techvenza-core' ); ?>
						<select name="tv_account_status">
							<?php
							foreach ( array(
								'active'    => __( 'Active', 'techvenza-core' ),
								'suspended' => __( 'Suspended', 'techvenza-core' ),
							) as $k => $l ) :
								?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m( 'tv_account_status' ) ? $m( 'tv_account_status' ) : 'active', $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select></label>
					<p><label for="tv_verified"><?php esc_html_e( 'Verified label (only when actually verified)', 'techvenza-core' ); ?></label><br>
					<input type="text" class="regular-text" id="tv_verified" name="tv_verified" value="<?php echo esc_attr( (string) $m( 'tv_verified' ) ); ?>"></p>
				</fieldset></td></tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Save profile fields.
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_profile( int $user_id ): void {
		if ( ! isset( $_POST['techvenza_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['techvenza_profile_nonce'] ) ), 'techvenza_profile' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		foreach ( array_keys( self::PROFILE ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_user_meta( $user_id, $key, self::sanitize( $key, wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by self::sanitize().
			}
		}
		if ( isset( $_POST['tv_editorial_present'] ) && ( current_user_can( 'tv_manage_authors' ) || current_user_can( 'edit_users' ) ) ) {
			foreach ( array( 'tv_staff', 'tv_featured', 'tv_visible' ) as $key ) {
				update_user_meta( $user_id, $key, ! empty( $_POST[ $key ] ) );
			}
			update_user_meta( $user_id, 'tv_account_status', self::sanitize( 'tv_account_status', wp_unslash( $_POST['tv_account_status'] ?? 'active' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_user_meta( $user_id, 'tv_verified', sanitize_text_field( wp_unslash( $_POST['tv_verified'] ?? '' ) ) );
		}
		self::flush( $user_id );
	}

	/**
	 * Private list helpers (bookmarks, topics).
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     tv_bookmarks|tv_topics.
	 * @return array<int,mixed>
	 */
	public static function get_list( int $user_id, string $key ): array {
		if ( ! in_array( $key, self::PRIVATE_KEYS, true ) ) {
			return array();
		}
		$value = get_user_meta( $user_id, $key, true );
		return is_array( $value ) ? array_values( $value ) : array();
	}
}
