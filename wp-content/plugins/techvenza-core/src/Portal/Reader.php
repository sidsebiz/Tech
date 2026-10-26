<?php
/**
 * Reader dashboard views: overview, saved articles, reading history, preferences & profile.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Portal;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Database\History;
use Techvenza\Core\Support\Render as R;

/**
 * Reader dashboard.
 */
final class Reader {

	/**
	 * Render the dashboard for the current user.
	 */
	public static function render(): string {
		$view = App::view( array( 'dashboard', 'saved', 'history', 'settings' ), 'dashboard' );
		switch ( $view ) {
			case 'saved':
				$html = self::saved();
				break;
			case 'history':
				$html = self::history();
				break;
			case 'settings':
				$html = self::settings();
				break;
			default:
				$html = self::overview();
		}
		return App::shell( 'reader', $view, $html );
	}

	/**
	 * Saved post IDs (published only), newest first.
	 *
	 * @return array<int,int>
	 */
	private static function saved_ids(): array {
		$ids = array_reverse( UserMeta::get_list( get_current_user_id(), 'tv_bookmarks' ) );
		return array_values( array_filter( array_map( 'intval', $ids ), static fn( $id ) => 'publish' === get_post_status( $id ) ) );
	}

	/**
	 * Followed topic slugs.
	 *
	 * @return array<int,string>
	 */
	private static function topics(): array {
		return array_values( array_filter( array_map( 'strval', UserMeta::get_list( get_current_user_id(), 'tv_topics' ) ) ) );
	}

	/**
	 * Overview.
	 */
	private static function overview(): string {
		$user   = wp_get_current_user();
		$saved  = self::saved_ids();
		$recent = array_values( array_filter( History::recent( $user->ID, 12 ), static fn( $r ) => 'publish' === get_post_status( $r['post_id'] ) ) );
		$topics = self::topics();
		$freq   = (string) get_user_meta( $user->ID, 'tv_nl_frequency', true );
		$recs   = $topics ? Query::latest( 3, $saved, $topics ) : array();

		$saved_html  = $saved
			? implode( '', array_map( array( App::class, 'row' ), array_slice( $saved, 0, 4 ) ) )
			: R::empty_state( 'bookmark', __( 'No saved articles yet', 'techvenza-core' ), __( 'Tap the bookmark icon on any story to save it here.', 'techvenza-core' ), sprintf( '<a class="btn btn-secondary btn-sm" href="%s">%s</a>', esc_url( R::page_url( 'explore' ) ), esc_html__( 'Browse stories', 'techvenza-core' ) ) );
		$topic_html  = $topics
			? implode( '', array_map( static fn( $s ) => R::chip( CategoryMeta::get( $s ) ), $topics ) )
			: '<p class="muted">' . esc_html__( 'No topics followed.', 'techvenza-core' ) . '</p>';
		$recs_html   = $recs
			? implode(
				'',
				array_map(
					static function ( $p ) {
						$c = CategoryMeta::for_post( $p->ID );
						return sprintf( '<a class="row" style="padding:8px 0;border-top:1px solid var(--border);flex-wrap:nowrap;gap:10px" href="%s"><span class="dot-c" style="--c:%s"></span><span style="font-size:var(--t-small);color:var(--ink);font-weight:500">%s</span></a>', esc_url( (string) get_permalink( $p ) ), esc_attr( $c['color'] ?? '#2563EB' ), esc_html( get_the_title( $p ) ) );
					},
					$recs
				)
			)
			: '<p class="muted" style="font-size:var(--t-small)">' . esc_html__( 'Follow topics to get recommendations.', 'techvenza-core' ) . '</p>';
		$recent_html = $recent
			? '<div class="grid g-3">' . implode( '', array_map( static fn( $r ) => R::card( $r['post_id'], 'boxed', array( 'noExcerpt' => true ) ), array_slice( $recent, 0, 3 ) ) ) . '</div>'
			: R::empty_state( 'clock', __( 'Nothing here yet', 'techvenza-core' ), __( 'Stories you open will appear here.', 'techvenza-core' ) );

		$first = strtok( $user->display_name, ' ' );
		return App::head(
			array(
				array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
				array( __( 'Dashboard', 'techvenza-core' ), '' ),
			),
			/* translators: %s: first name */
			sprintf( __( 'Welcome back, %s', 'techvenza-core' ), $first ? $first : $user->display_name ),
			esc_html__( 'Your saved stories, reading history and topic preferences.', 'techvenza-core' ),
			sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s %s</a>', esc_url( R::page_url( 'explore' ) ), R::icon( 'compass' ), esc_html__( 'Discover stories', 'techvenza-core' ) )
		)
		. '<div class="stats">'
		. App::stat( __( 'Saved articles', 'techvenza-core' ), (string) count( $saved ), 'var(--blue)' )
		. App::stat( __( 'Recently read', 'techvenza-core' ), (string) count( $recent ), 'var(--violet)' )
		. App::stat( __( 'Topics followed', 'techvenza-core' ), (string) count( $topics ), 'var(--teal)' )
		. sprintf( '<div class="stat" style="--c:var(--coral)"><span class="k"><i></i>%s</span><span class="v" style="font-size:1.25rem;margin-top:8px">%s</span></div>', esc_html__( 'Newsletter', 'techvenza-core' ), esc_html( 'monthly' === $freq ? __( 'Monthly digest', 'techvenza-core' ) : ( 'off' === $freq || '' === $freq ? __( 'Off', 'techvenza-core' ) : __( 'Weekly brief', 'techvenza-core' ) ) ) )
		. '</div>'
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%1$s <a class="sec-link" href="%2$s" style="font-size:var(--t-meta)">%3$s %4$s</a></h2><div data-saved-list>%5$s</div></section><section class="panel"><h2 class="panel-title">%6$s <a class="sec-link" href="%7$s" style="font-size:var(--t-meta)">%8$s %4$s</a></h2><div class="chip-row">%9$s</div><h3 class="label mt-6 mb-4">%10$s</h3>%11$s</section></div>',
			esc_html__( 'Saved for later', 'techvenza-core' ),
			esc_url( App::url( 'dashboard', 'saved' ) ),
			esc_html__( 'View all', 'techvenza-core' ),
			R::icon( 'arrowRight' ),
			$saved_html,
			esc_html__( 'Your topics', 'techvenza-core' ),
			esc_url( App::url( 'dashboard', 'settings' ) ),
			esc_html__( 'Edit', 'techvenza-core' ),
			$topic_html,
			esc_html__( 'Recommended for you', 'techvenza-core' ),
			$recs_html
		)
		. sprintf(
			'<section class="panel mt-5"><h2 class="panel-title">%s <a class="sec-link" href="%s" style="font-size:var(--t-meta)">%s %s</a></h2>%s</section>',
			esc_html__( 'Recently viewed', 'techvenza-core' ),
			esc_url( App::url( 'dashboard', 'history' ) ),
			esc_html__( 'Full history', 'techvenza-core' ),
			R::icon( 'arrowRight' ),
			$recent_html
		);
	}

	/**
	 * Saved articles.
	 */
	private static function saved(): string {
		$saved = self::saved_ids();
		$n     = count( $saved );
		return App::head(
			array(
				array( __( 'Dashboard', 'techvenza-core' ), App::url( 'dashboard' ) ),
				array( __( 'Saved', 'techvenza-core' ), '' ),
			),
			__( 'Saved articles', 'techvenza-core' ),
			/* translators: %d: count */
			esc_html( sprintf( _n( '%d saved story', '%d saved stories', $n, 'techvenza-core' ), $n ) ),
			$n ? sprintf( '<button class="btn btn-secondary btn-sm" type="button" data-action="clearSaved">%s %s</button>', R::icon( 'trash' ), esc_html__( 'Clear all', 'techvenza-core' ) ) : ''
		) . '<section class="panel" data-saved-list>' . (
			$n
				? implode( '', array_map( array( App::class, 'row' ), $saved ) )
				: R::empty_state( 'bookmark', __( 'Your reading list is empty', 'techvenza-core' ), __( 'Save stories to read later — they will appear here.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( R::page_url( 'explore' ) ), esc_html__( 'Explore stories', 'techvenza-core' ) ) )
		) . '</section>';
	}

	/**
	 * Reading history.
	 */
	private static function history(): string {
		$rows = array_values( array_filter( History::recent( get_current_user_id(), 50 ), static fn( $r ) => 'publish' === get_post_status( $r['post_id'] ) ) );
		return App::head(
			array(
				array( __( 'Dashboard', 'techvenza-core' ), App::url( 'dashboard' ) ),
				array( __( 'History', 'techvenza-core' ), '' ),
			),
			__( 'Reading history', 'techvenza-core' ),
			esc_html__( 'Articles you have opened while signed in.', 'techvenza-core' ),
			$rows ? sprintf( '<button class="btn btn-secondary btn-sm" type="button" data-action="clearHistory">%s %s</button>', R::icon( 'trash' ), esc_html__( 'Clear history', 'techvenza-core' ) ) : ''
		) . '<section class="panel" data-history-list>' . (
			$rows
				/* translators: %s: date */
				? implode( '', array_map( static fn( $r ) => App::row( $r['post_id'], '<span class="dot"></span> ' . esc_html( sprintf( __( 'Viewed %s', 'techvenza-core' ), R::date( substr( $r['viewed_at'], 0, 10 ) ) ) ) ), $rows ) )
				: R::empty_state( 'clock', __( 'No reading history', 'techvenza-core' ), __( 'Open any story and it will be listed here.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( R::page_url( 'trending' ) ), esc_html__( 'See what’s trending', 'techvenza-core' ) ) )
		) . '</section>';
	}

	/**
	 * Preferences & profile.
	 */
	private static function settings(): string {
		$user   = wp_get_current_user();
		$topics = self::topics();
		$cats   = array();
		foreach ( CategoryMeta::all() as $c ) {
			$cats[ $c['slug'] ] = $c['name'];
		}
		$freq    = (string) get_user_meta( $user->ID, 'tv_nl_frequency', true );
		$freq    = $freq ? $freq : 'off';
		$pending = (string) get_user_meta( $user->ID, 'tv_pending_email', true );
		$radios  = '';
		foreach ( array(
			'weekly'  => __( 'The Weekly Brief (every Thursday)', 'techvenza-core' ),
			'monthly' => __( 'Monthly digest', 'techvenza-core' ),
			'off'     => __( 'No newsletter', 'techvenza-core' ),
		) as $k => $l ) {
			$radios .= sprintf( '<label class="check"><input type="radio" name="frequency" value="%s"%s> <span>%s</span></label>', esc_attr( $k ), checked( $freq, $k, false ), esc_html( $l ) );
		}
		$form_attrs = 'data-tv-form data-method="PUT" novalidate';
		return App::head(
			array(
				array( __( 'Dashboard', 'techvenza-core' ), App::url( 'dashboard' ) ),
				array( __( 'Preferences', 'techvenza-core' ), '' ),
			),
			__( 'Preferences & profile', 'techvenza-core' ),
			esc_html__( 'Choose topics, newsletter settings and your profile details.', 'techvenza-core' )
		) . sprintf(
			'<div class="dash-grid even" style="margin-top:0">
			<section class="panel"><h2 class="panel-title">%1$s</h2><form class="form" %2$s data-endpoint="me/topics"><input type="hidden" name="topics[]" value="">%3$s<button class="btn btn-primary btn-sm" type="submit" style="justify-self:start">%4$s</button></form></section>
			<section class="panel"><h2 class="panel-title">%5$s</h2><form class="form" %2$s data-endpoint="me/preferences"><fieldset class="form" style="border:0;padding:0;margin:0"><legend class="sr-only">%5$s</legend>%6$s</fieldset><p class="hint">%7$s</p><button class="btn btn-primary btn-sm" type="submit" style="justify-self:start">%8$s</button></form></section>
			</div>',
			esc_html__( 'Preferred topics', 'techvenza-core' ),
			$form_attrs,
			R::checks( 'topics', $cats, $topics, array( 'label' => __( 'Preferred topics', 'techvenza-core' ) ) ),
			esc_html__( 'Save topics', 'techvenza-core' ),
			esc_html__( 'Newsletter preferences', 'techvenza-core' ),
			$radios,
			esc_html__( 'Every email includes a one-click unsubscribe link.', 'techvenza-core' ),
			esc_html__( 'Save preferences', 'techvenza-core' )
		) . sprintf(
			'<section class="panel mt-5"><h2 class="panel-title">%1$s</h2><form class="form" %2$s data-endpoint="me/profile"><div class="form-grid">%3$s%4$s</div>%5$s%6$s<div class="row"><button class="btn btn-primary" type="submit">%7$s</button><a class="link" href="%8$s" style="font-size:var(--t-meta)">%9$s</a></div></form></section>',
			esc_html__( 'Profile', 'techvenza-core' ),
			$form_attrs,
			R::field(
				array(
					'id'       => 'rpName',
					'name'     => 'name',
					'label'    => __( 'Display name', 'techvenza-core' ),
					'value'    => $user->display_name,
					'required' => true,
				)
			),
			R::field(
				array(
					'id'           => 'rpEmail',
					'name'         => 'email',
					'type'         => 'email',
					'label'        => __( 'Email', 'techvenza-core' ),
					'value'        => $user->user_email,
					'required'     => true,
					'autocomplete' => 'email',
					/* translators: %s: pending email */
					'hint'         => $pending ? sprintf( __( 'Waiting for confirmation of %s — check that inbox.', 'techvenza-core' ), $pending ) : __( 'Changing your email sends a confirmation link to the new address.', 'techvenza-core' ),
					'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'    => 'rpBio',
					'name'  => 'bio',
					'type'  => 'textarea',
					'label' => __( 'Short bio', 'techvenza-core' ),
					'opt'   => true,
					'rows'  => 3,
					'max'   => 280,
					'value' => $user->description,
				)
			),
			'<div class="form-note warn" data-form-error hidden role="alert">' . R::icon( 'alert' ) . '<span></span></div>',
			esc_html__( 'Save profile', 'techvenza-core' ),
			esc_url( App::url( 'dashboard', 'settings', array( 'pw' => 1 ) ) . '#password' ),
			esc_html__( 'Change password', 'techvenza-core' )
		) . Account::password_panel();
	}
}
