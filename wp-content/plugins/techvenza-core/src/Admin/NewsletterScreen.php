<?php
/**
 * Newsletter admin: subscribers (filter, export, unsubscribe, erase) and campaigns (compose,
 * preview, test send, schedule, send).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Newsletter\Campaigns;
use Techvenza\Core\Newsletter\Esp;
use Techvenza\Core\Newsletter\Subscribers;
use Techvenza\Core\Support\Render as R;

/**
 * Newsletter screen.
 */
final class NewsletterScreen {

	public const SLUG = 'techvenza-newsletter';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_techvenza_subscriber', array( self::class, 'handle_subscriber' ) );
		add_action( 'admin_post_techvenza_subscribers_csv', array( self::class, 'csv' ) );
		add_action( 'admin_post_techvenza_campaign', array( self::class, 'handle_campaign' ) );
		add_action( 'admin_post_techvenza_campaign_preview', array( self::class, 'preview' ) );
	}

	/**
	 * Current filters.
	 *
	 * @return array<string,string|int>
	 */
	private static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		return array(
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'q'      => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'topic'  => isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '',
			'page'   => isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1,
		);
		// phpcs:enable
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'subscribers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'subscribers' => __( 'Subscribers', 'techvenza-core' ),
			'campaigns'   => __( 'Campaigns', 'techvenza-core' ),
			'compose'     => __( 'Draft campaign', 'techvenza-core' ),
		);
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'subscribers';
		$nav  = '';
		foreach ( $tabs as $k => $label ) {
			$nav .= sprintf( '<a class="tab" href="%s"%s>%s</a>', esc_url( Admin::url( self::SLUG, array( 'tab' => $k ) ) ), $k === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		$provider = Esp::provider();
		$err      = get_option( 'techvenza_esp_last_error' );
		$desc     = 'local' === $provider
			? esc_html__( 'Sending through WordPress mail in background batches. Connect Brevo or Mailchimp in Settings → Newsletter for large lists.', 'techvenza-core' )
			/* translators: %s: provider name */
			: esc_html( sprintf( __( 'Confirmed subscribers sync to %s. Campaigns to all subscribers are sent through it; topic campaigns go through WordPress mail.', 'techvenza-core' ), ucfirst( $provider ) ) );
		$body = match ( $tab ) {
			'campaigns' => self::campaigns(),
			'compose'   => self::compose(),
			default     => self::subscribers(),
		};
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Newsletter', 'techvenza-core' ), '' ),
			),
			__( 'Newsletter', 'techvenza-core' ),
			$desc,
			sprintf( '<a class="button button-primary" href="%s">%s %s</a>', esc_url( Admin::url( self::SLUG, array( 'tab' => 'compose' ) ) ), R::icon( 'edit' ), esc_html__( 'New campaign', 'techvenza-core' ) )
		)
		. ( is_array( $err ) && 'local' !== $provider ? '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( /* translators: 1: date, 2: error */ __( 'Last sync error (%1$s): %2$s', 'techvenza-core' ), (string) $err[0], (string) $err[1] ) ) . '</p></div>' : '' )
		. sprintf( '<div class="table-wrap"><nav class="tabs" aria-label="%s">%s</nav>%s</div>', esc_attr__( 'Newsletter sections', 'techvenza-core' ), $nav, $body );
	}

	/**
	 * Subscribers tab.
	 */
	private static function subscribers(): string {
		$f      = self::filters();
		$per    = 25;
		$res    = Subscribers::query( $f + array( 'per' => $per ) );
		$counts = Subscribers::counts();
		$topics = Subscribers::topics();

		$status_links = '';
		foreach ( array(
			''             => __( 'All', 'techvenza-core' ),
			'confirmed'    => __( 'Active', 'techvenza-core' ),
			'pending'      => __( 'Awaiting confirmation', 'techvenza-core' ),
			'unsubscribed' => __( 'Unsubscribed', 'techvenza-core' ),
		) as $k => $label ) {
			$n             = '' === $k ? array_sum( $counts ) : ( $counts[ $k ] ?? 0 );
			$status_links .= sprintf(
				'<a class="button button-small%s" href="%s"%s>%s <span class="count">(%d)</span></a>',
				$k === $f['status'] ? ' button-primary' : '',
				esc_url(
					Admin::url(
						self::SLUG,
						array_filter(
							array(
								'status' => $k,
								'q'      => $f['q'],
								'topic'  => $f['topic'],
							)
						)
					)
				),
				$k === $f['status'] ? ' aria-current="page"' : '',
				esc_html( $label ),
				$n
			);
		}
		$opts = '<option value="">' . esc_html__( 'All topics', 'techvenza-core' ) . '</option>';
		foreach ( $topics as $slug => $name ) {
			$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $f['topic'], $slug, false ), esc_html( $name ) );
		}
		$filters = sprintf(
			'<form method="get" class="bulk" role="search"><input type="hidden" name="page" value="%1$s"><input type="hidden" name="status" value="%2$s"><label class="sr-only" for="tvSubQ">%3$s</label><input type="search" id="tvSubQ" name="q" value="%4$s" placeholder="%3$s"><label class="sr-only" for="tvSubTopic">%5$s</label><select id="tvSubTopic" name="topic">%6$s</select><button class="button" type="submit">%7$s</button><span style="flex:1"></span><div class="row" style="gap:4px">%8$s</div><a class="button" href="%9$s">%10$s %11$s</a></form>',
			esc_attr( self::SLUG ),
			esc_attr( (string) $f['status'] ),
			esc_attr__( 'Search email', 'techvenza-core' ),
			esc_attr( (string) $f['q'] ),
			esc_html__( 'Topic', 'techvenza-core' ),
			$opts,
			esc_html__( 'Filter', 'techvenza-core' ),
			$status_links,
			esc_url(
				wp_nonce_url(
					add_query_arg(
						array_filter(
							array(
								'action' => 'techvenza_subscribers_csv',
								'status' => $f['status'],
								'q'      => $f['q'],
								'topic'  => $f['topic'],
							)
						),
						admin_url( 'admin-post.php' )
					),
					'techvenza_subscribers_csv'
				)
			),
			R::icon( 'download' ),
			esc_html__( 'Export CSV', 'techvenza-core' )
		);
		$rows    = '';
		foreach ( $res['rows'] as $s ) {
			$chips = '';
			foreach ( array_slice( (array) $s['topics'], 0, 4 ) as $t ) {
				$chips .= '<span class="chip">' . esc_html( $topics[ $t ] ?? $t ) . '</span> ';
			}
			if ( count( (array) $s['topics'] ) > 4 ) {
				$chips .= '<span class="meta">+' . ( count( (array) $s['topics'] ) - 4 ) . '</span>';
			}
			$actions = '';
			if ( 'unsubscribed' !== $s['status'] ) {
				$actions .= self::sub_button( (int) $s['id'], 'unsubscribe', __( 'Unsubscribe', 'techvenza-core' ), 'button button-small', (string) $s['email'] );
			}
			$actions .= self::sub_button( (int) $s['id'], 'delete', __( 'Erase', 'techvenza-core' ), 'button button-small tva-danger', (string) $s['email'], __( 'Permanently erase this subscriber and their consent record? Use this for deletion requests.', 'techvenza-core' ) );
			$rows    .= sprintf(
				'<tr><td><strong>%s</strong>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><div class="row" style="gap:4px">%s</div></td></tr>',
				esc_html( (string) $s['email'] ),
				$s['source'] ? '<div class="meta">' . esc_html( sprintf( /* translators: %s: form */ __( 'via %s', 'techvenza-core' ), (string) $s['source'] ) ) . '</div>' : '',
				esc_html( R::date( substr( (string) $s['created_at'], 0, 10 ) ) ),
				$chips,
				esc_html( 'monthly' === $s['frequency'] ? __( 'Monthly', 'techvenza-core' ) : __( 'Weekly', 'techvenza-core' ) ),
				R::badge( (string) $s['status'] ),
				$actions
			);
		}
		$pages = (int) ceil( $res['total'] / $per );
		$pager = '';
		if ( $pages > 1 ) {
			$pager = '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(
				array(
					'base'    => add_query_arg(
						'pg',
						'%#%',
						Admin::url(
							self::SLUG,
							array_filter(
								array(
									'status' => $f['status'],
									'q'      => $f['q'],
									'topic'  => $f['topic'],
								)
							)
						)
					),
					'format'  => '',
					'current' => (int) $f['page'],
					'total'   => $pages,
				)
			) . '</div></div>';
		}
		$table = $rows
			? sprintf(
				'<table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col"><span class="sr-only">%s</span></th></tr></thead><tbody>%s</tbody></table>',
				esc_html__( 'Email', 'techvenza-core' ),
				esc_html__( 'Joined', 'techvenza-core' ),
				esc_html__( 'Topics', 'techvenza-core' ),
				esc_html__( 'Frequency', 'techvenza-core' ),
				esc_html__( 'Status', 'techvenza-core' ),
				esc_html__( 'Actions', 'techvenza-core' ),
				$rows
			)
			: R::empty_state( 'mail', __( 'No subscribers found', 'techvenza-core' ), __( 'Try a different filter, or share the newsletter page.', 'techvenza-core' ) );
		return $filters . $table . $pager;
	}

	/**
	 * Subscriber action button.
	 *
	 * @param int    $id      Subscriber.
	 * @param string $op      unsubscribe|delete.
	 * @param string $label   Label.
	 * @param string $cls     Classes.
	 * @param string $email   Email (accessible name).
	 * @param string $confirm Confirmation text.
	 */
	private static function sub_button( int $id, string $op, string $label, string $cls, string $email, string $confirm = '' ): string {
		return sprintf(
			'<form method="post" action="%1$s" style="display:inline"%2$s>%3$s<input type="hidden" name="action" value="techvenza_subscriber"><input type="hidden" name="id" value="%4$d"><input type="hidden" name="op" value="%5$s"><button class="%6$s" type="submit" aria-label="%7$s">%8$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '',
			wp_nonce_field( 'techvenza_subscriber_' . $id, '_wpnonce', true, false ),
			$id,
			esc_attr( $op ),
			esc_attr( $cls ),
			esc_attr( $label . ' ' . $email ),
			esc_html( $label )
		);
	}

	/**
	 * Subscriber actions (admin-post).
	 */
	public static function handle_subscriber(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'techvenza_subscriber_' . $id );
		if ( ! current_user_can( 'tv_manage_newsletter' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscribers.', 'techvenza-core' ), 403 );
		}
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( 'unsubscribe' === $op ) {
			Subscribers::unsubscribe( $id );
		} elseif ( 'delete' === $op ) {
			Subscribers::delete( $id );
		}
		Admin::back( 'done' );
	}

	/**
	 * CSV export of the filtered list.
	 */
	public static function csv(): void {
		check_admin_referer( 'techvenza_subscribers_csv' );
		if ( ! current_user_can( 'tv_manage_newsletter' ) ) {
			wp_die( esc_html__( 'You do not have permission to export subscribers.', 'techvenza-core' ), 403 );
		}
		$f = self::filters();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="techvenza-subscribers-' . current_time( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}
		fputcsv( $out, array( 'email', 'status', 'frequency', 'topics', 'source', 'created_at', 'consent_at', 'consent_text', 'confirmed_at', 'unsubscribed_at' ) );
		foreach ( Subscribers::query( $f + array( 'per' => -1 ) )['rows'] as $s ) {
			fputcsv(
				$out,
				array(
					Analytics::cell( (string) $s['email'] ),
					$s['status'],
					$s['frequency'],
					implode( ' ', (array) $s['topics'] ),
					Analytics::cell( (string) $s['source'] ),
					$s['created_at'],
					$s['consent_at'],
					Analytics::cell( (string) $s['consent_text'] ),
					$s['confirmed_at'],
					$s['unsubscribed_at'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Campaigns tab.
	 */
	private static function campaigns(): string {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::CAMPAIGN,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$aud   = Campaigns::audiences();
		$rows  = '';
		foreach ( $posts as $p ) {
			$c     = Campaigns::get( $p->ID );
			$stats = (array) $c['stats'];
			$note  = '';
			if ( isset( $stats['recipients'] ) ) {
				/* translators: 1: sent, 2: total */
				$note = sprintf( __( '%1$d of %2$d sent', 'techvenza-core' ), (int) ( $stats['sent'] ?? 0 ), (int) $stats['recipients'] ) . ( ! empty( $stats['failed'] ) ? ' · ' . sprintf( /* translators: %d: failures */ __( '%d failed', 'techvenza-core' ), (int) $stats['failed'] ) : '' );
			} elseif ( ! empty( $stats['via'] ) ) {
				/* translators: %s: provider */
				$note = sprintf( __( 'Via %s — open rates in the provider dashboard', 'techvenza-core' ), ucfirst( (string) $stats['via'] ) );
			} elseif ( ! empty( $stats['error'] ) ) {
				$note = (string) $stats['error'];
			} elseif ( ! empty( $stats['note'] ) ) {
				$note = (string) $stats['note'];
			}
			$date  = $c['sent_at'] ? $c['sent_at'] : $c['send_at'];
			$rows .= sprintf(
				'<tr><td><strong>%1$s</strong><div class="meta">%2$s</div></td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td class="meta">%6$s</td><td><div class="row" style="gap:4px"><a class="button button-small" href="%7$s" target="_blank" rel="noopener">%8$s<span class="sr-only"> %1$s %11$s</span></a>%9$s</div></td></tr>',
				esc_html( (string) $c['name'] ),
				esc_html( (string) $c['subject'] ),
				esc_html( $aud[ $c['audience'] ] ?? (string) $c['audience'] ),
				$date ? esc_html( R::date( substr( $date, 0, 10 ) ) ) : '—',
				R::badge( (string) $c['status'] ),
				esc_html( $note ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=techvenza_campaign_preview&id=' . $p->ID ), 'techvenza_campaign_preview' ) ),
				esc_html__( 'Preview', 'techvenza-core' ),
				in_array( $c['status'], array( 'draft', 'scheduled' ), true ) ? sprintf(
					'<a class="button button-small" href="%s">%s<span class="sr-only"> %s</span></a>',
					esc_url(
						Admin::url(
							self::SLUG,
							array(
								'tab' => 'compose',
								'id'  => $p->ID,
							)
						)
					),
					esc_html__( 'Edit', 'techvenza-core' ),
					esc_html( (string) $c['name'] )
				) : '',
				'',
				esc_html__( '(opens in a new tab)', 'techvenza-core' )
			);
		}
		if ( ! $rows ) {
			return R::empty_state( 'mail', __( 'No campaigns yet', 'techvenza-core' ), __( 'Draft your first issue from recent articles.', 'techvenza-core' ), '<a class="button button-primary" href="' . esc_url( Admin::url( self::SLUG, array( 'tab' => 'compose' ) ) ) . '">' . esc_html__( 'Draft campaign', 'techvenza-core' ) . '</a>' );
		}
		return sprintf(
			'<table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col"><span class="sr-only">%s</span></th></tr></thead><tbody>%s</tbody></table>',
			esc_html__( 'Campaign', 'techvenza-core' ),
			esc_html__( 'Audience', 'techvenza-core' ),
			esc_html__( 'Date', 'techvenza-core' ),
			esc_html__( 'Status', 'techvenza-core' ),
			esc_html__( 'Delivery', 'techvenza-core' ),
			esc_html__( 'Actions', 'techvenza-core' ),
			$rows
		);
	}

	/**
	 * Compose tab.
	 */
	private static function compose(): string {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$c  = $id && PostTypes::CAMPAIGN === get_post_type( $id ) ? Campaigns::get( $id ) : array(
			'id'        => 0,
			/* translators: %s: date */
			'name'      => sprintf( __( 'The Weekly Brief — %s', 'techvenza-core' ), wp_date( 'j M' ) ),
			'subject'   => '',
			'preheader' => '',
			'intro'     => '',
			'posts'     => array(),
			'audience'  => 'all',
			'status'    => 'draft',
			'send_at'   => '',
		);
		if ( ! in_array( $c['status'], array( 'draft', 'scheduled' ), true ) ) {
			return '<div style="padding:20px">' . R::empty_state( 'lock', __( 'This campaign was already sent', 'techvenza-core' ), __( 'Sent campaigns cannot be edited. Preview it from the Campaigns tab.', 'techvenza-core' ) ) . '</div>';
		}
		$recent = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
			)
		);
		$checks = '';
		foreach ( $recent as $p ) {
			$checks .= sprintf( '<label class="tva-check"><input type="checkbox" name="posts[]" value="%d"%s> %s <span class="meta">· %s</span></label>', $p->ID, checked( in_array( $p->ID, (array) $c['posts'], true ), true, false ), esc_html( get_the_title( $p ) ), esc_html( R::date( get_the_date( 'Y-m-d', $p ) ) ) );
		}
		$aud = '';
		foreach ( Campaigns::audiences() as $k => $label ) {
			$aud .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $c['audience'], $k, false ), esc_html( $label ) );
		}
		$input = static fn( string $id, string $name, string $label, string $value, string $extra = '' ): string => sprintf( '<p><label for="%1$s"><strong>%3$s</strong></label><br><input class="large-text" type="text" id="%1$s" name="%2$s" value="%4$s"%5$s></p>', esc_attr( $id ), esc_attr( $name ), esc_html( $label ), esc_attr( $value ), $extra );
		$when  = $c['send_at'] ? str_replace( ' ', 'T', substr( (string) $c['send_at'], 0, 16 ) ) : gmdate( 'Y-m-d\T08:00', (int) strtotime( 'next thursday', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$me    = wp_get_current_user()->user_email;
		return sprintf(
			'<form method="post" action="%1$s" style="padding:20px;max-width:860px" data-campaign-form>%2$s<input type="hidden" name="action" value="techvenza_campaign"><input type="hidden" name="id" value="%3$d">
			<div class="tva-grid2">%4$s<p><label for="cpAud"><strong>%5$s</strong></label><br><select id="cpAud" name="audience" class="large-text">%6$s</select></p></div>
			%7$s%8$s<p><label for="cpIntro"><strong>%9$s</strong></label><br><textarea class="large-text" id="cpIntro" name="intro" rows="3">%10$s</textarea></p>
			<fieldset><legend><strong>%11$s</strong></legend><div class="tva-checks">%12$s</div></fieldset>
			<div class="row" style="margin-top:16px;gap:8px;flex-wrap:wrap"><button class="button button-primary" type="submit" name="op" value="save">%13$s</button><button class="button" type="submit" name="op" value="test">%14$s</button>%15$s</div>
			<div class="panel" style="margin-top:20px"><h2 class="panel-title">%16$s</h2><div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end"><p style="margin:0"><label for="cpWhen"><strong>%17$s</strong></label><br><input type="datetime-local" id="cpWhen" name="send_at" value="%18$s"></p><button class="button" type="submit" name="op" value="schedule">%19$s</button><button class="button tva-danger" type="submit" name="op" value="send" data-confirm-send="%20$s">%21$s</button></div><p class="meta">%22$s</p></div></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'techvenza_campaign', '_wpnonce', true, false ),
			(int) $c['id'],
			$input( 'cpName', 'name', __( 'Campaign name', 'techvenza-core' ), (string) $c['name'], ' required' ),
			esc_html__( 'Audience', 'techvenza-core' ),
			$aud,
			$input( 'cpSubj', 'subject', __( 'Subject line (max 70 characters)', 'techvenza-core' ), (string) $c['subject'], ' required maxlength="70"' ),
			$input( 'cpPre', 'preheader', __( 'Preheader', 'techvenza-core' ), (string) $c['preheader'], ' maxlength="110"' ),
			esc_html__( "Editor's note", 'techvenza-core' ),
			esc_textarea( (string) $c['intro'] ),
			esc_html__( 'Stories to include', 'techvenza-core' ),
			$checks,
			esc_html__( 'Save draft', 'techvenza-core' ),
			/* translators: %s: email */
			esc_html( sprintf( __( 'Send test to %s', 'techvenza-core' ), $me ) ),
			$c['id'] ? sprintf( '<a class="button" href="%s" target="_blank" rel="noopener">%s<span class="sr-only"> %s</span></a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=techvenza_campaign_preview&id=' . (int) $c['id'] ), 'techvenza_campaign_preview' ) ), esc_html__( 'Preview', 'techvenza-core' ), esc_html__( '(opens in a new tab)', 'techvenza-core' ) ) : '',
			esc_html__( 'Send', 'techvenza-core' ),
			esc_html__( 'Send at (site time)', 'techvenza-core' ),
			esc_attr( $when ),
			esc_html__( 'Schedule', 'techvenza-core' ),
			esc_attr__( 'Send this campaign to every confirmed subscriber in the selected audience now? This cannot be undone.', 'techvenza-core' ),
			esc_html__( 'Send now', 'techvenza-core' ),
			'scheduled' === $c['status'] ? esc_html( sprintf( /* translators: %s: date */ __( 'Currently scheduled for %s. Saving keeps the schedule; schedule again to change it.', 'techvenza-core' ), (string) $c['send_at'] ) ) : esc_html__( 'Subscribers receive only confirmed, opted-in email. Every message includes one-click unsubscribe.', 'techvenza-core' )
		);
	}

	/**
	 * Campaign actions (admin-post).
	 */
	public static function handle_campaign(): void {
		check_admin_referer( 'techvenza_campaign' );
		if ( ! current_user_can( 'tv_manage_newsletter' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage campaigns.', 'techvenza-core' ), 403 );
		}
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : 'save';
		$data = array(
			'name'      => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'subject'   => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'preheader' => isset( $_POST['preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['preheader'] ) ) : '',
			'intro'     => isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '',
			'audience'  => isset( $_POST['audience'] ) ? sanitize_key( wp_unslash( $_POST['audience'] ) ) : 'all',
			'posts'     => isset( $_POST['posts'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['posts'] ) ) : array(),
		);
		if ( $id ) {
			$status = (string) get_post_meta( $id, 'tv_status', true );
			if ( ! in_array( $status, array( '', 'draft', 'scheduled' ), true ) ) {
				Admin::back( 'error', __( 'This campaign was already sent.', 'techvenza-core' ) );
			}
		}
		$saved = Campaigns::save( $id, $data );
		if ( is_wp_error( $saved ) ) {
			Admin::back( 'error', $saved->get_error_message() );
		}
		$id   = (int) $saved;
		$edit = Admin::url(
			self::SLUG,
			array(
				'tab' => 'compose',
				'id'  => $id,
			)
		);
		$list = Admin::url( self::SLUG, array( 'tab' => 'campaigns' ) );
		switch ( $op ) {
			case 'test':
				$ok = Campaigns::send_test( $id, wp_get_current_user()->user_email );
				Admin::back( $ok ? 'done' : 'error', $ok ? __( 'Test email sent.', 'techvenza-core' ) : __( 'The test email could not be sent. Check Settings → Email.', 'techvenza-core' ), $edit );
				break;
			case 'schedule':
				$when = isset( $_POST['send_at'] ) ? str_replace( 'T', ' ', sanitize_text_field( wp_unslash( $_POST['send_at'] ) ) ) : '';
				$res  = Campaigns::schedule( $id, substr( $when, 0, 16 ) );
				Admin::back( is_wp_error( $res ) ? 'error' : 'done', is_wp_error( $res ) ? $res->get_error_message() : __( 'Campaign scheduled.', 'techvenza-core' ), is_wp_error( $res ) ? $edit : $list );
				break;
			case 'send':
				wp_clear_scheduled_hook( 'techvenza_campaign_start', array( $id ) );
				update_post_meta( $id, 'tv_status', 'draft' );
				$res = Campaigns::start( $id );
				Admin::back( is_wp_error( $res ) ? 'error' : 'done', is_wp_error( $res ) ? $res->get_error_message() : $res, is_wp_error( $res ) ? $edit : $list );
				break;
			default:
				Admin::back( 'done', __( 'Draft saved.', 'techvenza-core' ), $edit );
		}
	}

	/**
	 * Email preview (admin-post, opens in a new tab).
	 */
	public static function preview(): void {
		check_admin_referer( 'techvenza_campaign_preview' );
		if ( ! current_user_can( 'tv_manage_newsletter' ) ) {
			wp_die( esc_html__( 'You do not have permission to preview campaigns.', 'techvenza-core' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( PostTypes::CAMPAIGN !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Campaign not found.', 'techvenza-core' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo Campaigns::html( Campaigns::get( $id ), home_url( '/newsletter/' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		exit;
	}
}
