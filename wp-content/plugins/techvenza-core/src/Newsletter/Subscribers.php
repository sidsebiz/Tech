<?php
/**
 * Subscribers repository: double opt-in sign-ups, confirmation, unsubscribe, preference changes,
 * listing and export. Every change is mirrored to the configured email service provider.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Newsletter;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table named by Schema::table(); every value goes through $wpdb->prepare().

use Techvenza\Core\Database\Schema;
use Techvenza\Core\Rest\Routes;

/**
 * Subscriber data access.
 */
final class Subscribers {

	public const STATUSES = array( 'pending', 'confirmed', 'unsubscribed' );

	/**
	 * Table name.
	 */
	private static function table(): string {
		return Schema::table( 'subscribers' );
	}

	/**
	 * Normalise a row.
	 *
	 * @param object|array<string,mixed>|null $row DB row.
	 * @return array<string,mixed>|null
	 */
	private static function hydrate( $row ): ?array {
		if ( ! $row ) {
			return null;
		}
		$r           = (array) $row;
		$r['id']     = (int) $r['id'];
		$r['topics'] = array_values( array_filter( array_map( 'strval', (array) json_decode( (string) ( $r['topics'] ?? '[]' ), true ) ) ) );
		return $r;
	}

	/**
	 * Find by email.
	 *
	 * @param string $email Email.
	 * @return array<string,mixed>|null
	 */
	public static function find( string $email ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', strtolower( trim( $email ) ) ) ) );
	}

	/**
	 * Find by id.
	 *
	 * @param int $id ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) );
	}

	/**
	 * Find by token.
	 *
	 * @param string $column confirm_token|unsubscribe_token.
	 * @param string $token  Token.
	 * @return array<string,mixed>|null
	 */
	public static function by_token( string $column, string $token ): ?array {
		global $wpdb;
		if ( ! in_array( $column, array( 'confirm_token', 'unsubscribe_token' ), true ) || ! preg_match( '/^[A-Za-z0-9]{40}$/', $token ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE {$column} = %s", $token ) ) );
	}

	/**
	 * Allowed topic slugs (top-level categories).
	 *
	 * @return array<string,string> slug => name
	 */
	public static function topics(): array {
		$out = array();
		foreach ( \Techvenza\Core\Content\CategoryMeta::all() as $c ) {
			$out[ (string) $c['slug'] ] = (string) $c['name'];
		}
		return $out;
	}

	/**
	 * Create or refresh a sign-up. New and previously unsubscribed addresses become "pending" and
	 * receive a confirmation email; already-confirmed addresses only get their preferences updated
	 * (without revealing to the visitor whether the address was already subscribed).
	 *
	 * @param string            $email     Email.
	 * @param array<int,string> $topics    Topic slugs.
	 * @param string            $frequency weekly|monthly.
	 * @param string            $source    Form identifier.
	 * @param string            $consent   Consent wording shown to the visitor.
	 * @return array{status:string,id:int}|\WP_Error
	 */
	public static function subscribe( string $email, array $topics, string $frequency, string $source, string $consent ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'tv_email', __( 'Enter a valid email address.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$topics    = array_values( array_intersect( array_map( 'sanitize_key', $topics ), array_keys( self::topics() ) ) );
		$frequency = in_array( $frequency, array( 'weekly', 'monthly' ), true ) ? $frequency : 'weekly';
		$now       = current_time( 'mysql' );
		$existing  = self::find( $email );
		$user      = get_user_by( 'email', $email );
		$data      = array(
			'topics'     => wp_json_encode( $topics ),
			'frequency'  => $frequency,
			'source'     => substr( sanitize_key( $source ), 0, 50 ),
			'user_id'    => $user ? $user->ID : 0,
			'updated_at' => $now,
		);

		if ( $existing && 'confirmed' === $existing['status'] ) {
			$wpdb->update( self::table(), $data, array( 'id' => $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Esp::sync( (int) $existing['id'] );
			return array(
				'status' => 'updated',
				'id'     => (int) $existing['id'],
			);
		}

		$data += array(
			'status'            => 'pending',
			'consent_at'        => $now,
			'consent_ip_hash'   => Routes::ip_hash(),
			'consent_text'      => wp_strip_all_tags( $consent ),
			'confirm_token'     => wp_generate_password( 40, false ),
			'unsubscribe_token' => $existing ? (string) $existing['unsubscribe_token'] : wp_generate_password( 40, false ),
			'unsubscribed_at'   => null,
		);
		if ( $existing ) {
			// Throttle repeat confirmation emails to the same address.
			if ( 'pending' === $existing['status'] && strtotime( (string) $existing['updated_at'] ) > strtotime( $now ) - 10 * MINUTE_IN_SECONDS ) {
				return array(
					'status' => 'pending',
					'id'     => (int) $existing['id'],
				);
			}
			$wpdb->update( self::table(), $data, array( 'id' => $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$id = (int) $existing['id'];
		} else {
			$data['email']      = $email;
			$data['created_at'] = $now;
			$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$id = (int) $wpdb->insert_id;
		}
		if ( ! $id ) {
			return new \WP_Error( 'tv_db', __( 'We could not save your subscription. Please try again.', 'techvenza-core' ), array( 'status' => 500 ) );
		}
		Newsletter::send_confirmation( $id );
		return array(
			'status' => 'pending',
			'id'     => $id,
		);
	}

	/**
	 * Confirm a pending subscription.
	 *
	 * @param string $token Confirm token.
	 * @return array<string,mixed>|null The subscriber, or null when the token is unknown.
	 */
	public static function confirm( string $token ): ?array {
		global $wpdb;
		$sub = self::by_token( 'confirm_token', $token );
		if ( ! $sub ) {
			return null;
		}
		if ( 'confirmed' !== $sub['status'] ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				self::table(),
				array(
					'status'       => 'confirmed',
					'confirmed_at' => current_time( 'mysql' ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $sub['id'] )
			);
			Esp::sync( (int) $sub['id'] );
			Newsletter::send_welcome( (int) $sub['id'] );
			$sub['status'] = 'confirmed';
		}
		return $sub;
	}

	/**
	 * Unsubscribe.
	 *
	 * @param int $id Subscriber.
	 */
	public static function unsubscribe( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array(
				'status'          => 'unsubscribed',
				'unsubscribed_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		Esp::sync( $id );
	}

	/**
	 * Delete a subscriber permanently (GDPR erasure).
	 *
	 * @param int $id Subscriber.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		$sub = self::get( $id );
		if ( ! $sub ) {
			return;
		}
		Esp::remove( (string) $sub['email'] );
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Mirror a reader's dashboard frequency choice (weekly|monthly|off) to their subscription.
	 *
	 * @param int    $user_id User.
	 * @param string $freq    weekly|monthly|off.
	 */
	public static function sync_user_frequency( int $user_id, string $freq ): void {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$sub = self::find( $user->user_email );
		if ( 'off' === $freq ) {
			if ( $sub && 'unsubscribed' !== $sub['status'] ) {
				self::unsubscribe( (int) $sub['id'] );
			}
			return;
		}
		if ( $sub && 'confirmed' === $sub['status'] ) {
			$wpdb->update(
				self::table(),
				array(
					'frequency'  => $freq,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $sub['id'] )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Esp::sync( (int) $sub['id'] );
			return;
		}
		$topics = array_map( 'strval', (array) get_user_meta( $user_id, 'tv_topics', true ) );
		self::subscribe( $user->user_email, $topics, $freq, 'dashboard', __( 'Chose a newsletter frequency in the reader dashboard.', 'techvenza-core' ) );
	}

	/**
	 * Paged list.
	 *
	 * @param array<string,mixed> $args status, q, topic, page, per.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['q'] ) ) {
			$where[]  = 'email LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
		}
		if ( ! empty( $args['topic'] ) ) {
			$where[]  = 'topics LIKE %s';
			$params[] = '%' . $wpdb->esc_like( '"' . sanitize_key( (string) $args['topic'] ) . '"' ) . '%';
		}
		$per   = max( 1, (int) ( $args['per'] ?? 25 ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$sql   = 'FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );
		$limit = -1 === (int) ( $args['per'] ?? 25 ) ? '' : $wpdb->prepare( ' LIMIT %d OFFSET %d', $per, ( $page - 1 ) * $per );
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) {$sql}", $params ) : "SELECT COUNT(*) {$sql}" );
		$rows  = $wpdb->get_results( ( $params ? $wpdb->prepare( "SELECT * {$sql} ORDER BY created_at DESC, id DESC", $params ) : "SELECT * {$sql} ORDER BY created_at DESC, id DESC" ) . $limit );
		return array(
			'rows'  => array_values( array_filter( array_map( array( self::class, 'hydrate' ), (array) $rows ) ) ),
			'total' => $total,
		);
	}

	/**
	 * Counts per status.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		global $wpdb;
		$out = array_fill_keys( self::STATUSES, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status' ) as $r ) {
			$out[ (string) $r->status ] = (int) $r->n;
		}
		return $out;
	}

	/**
	 * Confirmed recipients for an audience ('all', or a topic slug) and frequency.
	 *
	 * @param string $audience  all|topic slug.
	 * @param string $frequency ''|weekly|monthly.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recipients( string $audience, string $frequency = '' ): array {
		$rows = self::query(
			array(
				'status' => 'confirmed',
				'topic'  => 'all' === $audience ? '' : $audience,
				'per'    => -1,
			)
		)['rows'];
		if ( $frequency ) {
			$rows = array_values( array_filter( $rows, static fn( $r ) => $frequency === $r['frequency'] ) );
		}
		return $rows;
	}

	/**
	 * Delete pending sign-ups that were never confirmed.
	 *
	 * @param int $days Age in days.
	 */
	public static function purge_unconfirmed( int $days = 14 ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . " WHERE status = 'pending' AND is_seed = 0 AND updated_at < %s", gmdate( 'Y-m-d H:i:s', (int) strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Store the provider's contact id.
	 *
	 * @param int    $id     Subscriber.
	 * @param string $esp_id Provider id.
	 */
	public static function set_esp_id( int $id, string $esp_id ): void {
		global $wpdb;
		$wpdb->update( self::table(), array( 'esp_id' => substr( $esp_id, 0, 100 ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
