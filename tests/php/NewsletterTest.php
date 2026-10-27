<?php
/**
 * Newsletter subscriptions: double opt-in, preference updates, unsubscribe, erasure.
 *
 * @package Techvenza\Tests
 */

namespace Techvenza\Tests;

use Techvenza\Core\Newsletter\Newsletter;
use Techvenza\Core\Newsletter\Subscribers;

/**
 * @covers \Techvenza\Core\Newsletter\Subscribers
 * @covers \Techvenza\Core\Newsletter\Newsletter
 */
class NewsletterTest extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 );
		self::factory()->category->create(
			array(
				'name' => 'Artificial Intelligence',
				'slug' => 'ai',
			)
		);
		self::factory()->category->create(
			array(
				'name' => 'Security',
				'slug' => 'security',
			)
		);
		reset_phpmailer_instance();
	}

	/**
	 * Emails sent so far.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function sent(): array {
		return tests_retrieve_phpmailer_instance()->mock_sent;
	}

	public function test_signup_is_pending_until_confirmed(): void {
		$result = Subscribers::subscribe( 'Reader@Example.com', array( 'ai', 'unknown-topic' ), 'weekly', 'test', 'Consent text' );
		$this->assertIsArray( $result );
		$this->assertSame( 'pending', $result['status'] );

		$sub = Subscribers::find( 'reader@example.com' );
		$this->assertNotNull( $sub );
		$this->assertSame( 'pending', $sub['status'] );
		$this->assertSame( array( 'ai' ), $sub['topics'], 'Unknown topics are dropped.' );
		$this->assertSame( 'Consent text', $sub['consent_text'] );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{40}$/', $sub['confirm_token'] );
		$this->assertCount( 1, $this->sent(), 'A confirmation email is sent.' );
		$this->assertStringContainsString( 'tv_nl_confirm=' . $sub['confirm_token'], quoted_printable_decode( $this->sent()[0]['body'] ) );

		$this->assertSame( array(), Subscribers::recipients( 'all' ), 'Pending addresses never receive campaigns.' );

		$confirmed = Subscribers::confirm( $sub['confirm_token'] );
		$this->assertSame( 'confirmed', $confirmed['status'] );
		$this->assertCount( 1, Subscribers::recipients( 'all' ) );
		$this->assertCount( 1, Subscribers::recipients( 'ai' ) );
		$this->assertCount( 0, Subscribers::recipients( 'security' ) );
	}

	public function test_existing_subscriber_only_updates_preferences(): void {
		Subscribers::subscribe( 'pat@example.com', array( 'ai' ), 'weekly', 'test', 'x' );
		Subscribers::confirm( Subscribers::find( 'pat@example.com' )['confirm_token'] );
		reset_phpmailer_instance();

		$again = Subscribers::subscribe( 'pat@example.com', array( 'security' ), 'monthly', 'test', 'x' );
		$this->assertSame( 'updated', $again['status'] );
		$sub = Subscribers::find( 'pat@example.com' );
		$this->assertSame( 'confirmed', $sub['status'] );
		$this->assertSame( array( 'security' ), $sub['topics'] );
		$this->assertSame( 'monthly', $sub['frequency'] );
		$this->assertCount( 0, $this->sent(), 'No second confirmation for a confirmed address.' );
	}

	public function test_unsubscribe_and_erase(): void {
		Subscribers::subscribe( 'lee@example.com', array( 'ai' ), 'weekly', 'test', 'x' );
		$sub = Subscribers::find( 'lee@example.com' );
		Subscribers::confirm( $sub['confirm_token'] );

		$this->assertNull( Subscribers::by_token( 'unsubscribe_token', 'not-a-token' ) );
		$by = Subscribers::by_token( 'unsubscribe_token', $sub['unsubscribe_token'] );
		$this->assertSame( $sub['id'], $by['id'] );

		Subscribers::unsubscribe( $sub['id'] );
		$this->assertSame( 'unsubscribed', Subscribers::find( 'lee@example.com' )['status'] );
		$this->assertSame( array(), Subscribers::recipients( 'all' ) );

		Subscribers::delete( $sub['id'] );
		$this->assertNull( Subscribers::find( 'lee@example.com' ) );
	}

	public function test_form_processing_validates_and_hides_existing_addresses(): void {
		$bad = Newsletter::process(
			array(
				'email' => 'not-an-email',
				'full'  => '1',
			)
		);
		$this->assertWPError( $bad );
		$errors = $bad->get_error_data()['errors'];
		$this->assertArrayHasKey( 'email', $errors );
		$this->assertArrayHasKey( 'topics', $errors );
		$this->assertArrayHasKey( 'consent', $errors );

		$ok = Newsletter::process(
			array(
				'email'   => 'sam@example.com',
				'full'    => '1',
				'topics'  => array( 'ai' ),
				'consent' => true,
			)
		);
		$this->assertIsArray( $ok );
		$this->assertStringContainsString( 'Check your inbox', $ok['html'] );

		$quick = Newsletter::process( array( 'email' => 'quick@example.com' ) );
		$this->assertIsArray( $quick, 'The compact form needs only an email.' );
		$this->assertEqualsCanonicalizing( array( 'ai', 'security' ), Subscribers::find( 'quick@example.com' )['topics'], 'Compact sign-ups follow every topic.' );

		$honeypot = Newsletter::process(
			array(
				'email'   => 'bot@example.com',
				'website' => 'spam',
			)
		);
		$this->assertIsArray( $honeypot );
		$this->assertNull( Subscribers::find( 'bot@example.com' ), 'Honeypot hits are not stored.' );
	}

	public function test_unconfirmed_signups_are_purged(): void {
		global $wpdb;
		Subscribers::subscribe( 'old@example.com', array( 'ai' ), 'weekly', 'test', 'x' );
		$wpdb->update( \Techvenza\Core\Database\Schema::table( 'subscribers' ), array( 'updated_at' => '2020-01-01 00:00:00' ), array( 'email' => 'old@example.com' ) );
		$this->assertSame( 1, Subscribers::purge_unconfirmed( 14 ) );
		$this->assertNull( Subscribers::find( 'old@example.com' ) );
	}
}
