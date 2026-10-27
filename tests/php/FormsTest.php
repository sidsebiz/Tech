<?php
/**
 * Public forms: contact routing and author applications.
 *
 * @package Techvenza\Tests
 */

namespace Techvenza\Tests;

use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Forms\Forms;
use Techvenza\Core\Settings\Options;

/**
 * @covers \Techvenza\Core\Forms\Forms
 */
class FormsTest extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$_SERVER['REMOTE_ADDR'] = '198.51.100.' . wp_rand( 1, 254 );
		self::factory()->category->create(
			array(
				'name' => 'Cloud Computing',
				'slug' => 'cloud',
			)
		);
		reset_phpmailer_instance();
	}

	/**
	 * Valid contact submission.
	 *
	 * @return array<string,mixed>
	 */
	private function contact(): array {
		return array(
			'type'    => 'correction',
			'name'    => 'Casey Reader',
			'email'   => 'casey@example.com',
			'subject' => 'A date in the FinOps article',
			'message' => 'The article says 2024 but the report it cites is from 2025.',
			'consent' => true,
		);
	}

	/**
	 * Valid application.
	 *
	 * @return array<string,mixed>
	 */
	private function application(): array {
		return array(
			'name'      => 'Jordan Writer',
			'email'     => 'jordan@example.com',
			'title'     => 'Principal Engineer',
			'expertise' => 'Cloud, FinOps',
			'cats'      => array( 'cloud', 'nonexistent' ),
			'bio'       => 'Jordan is a principal engineer who has led cloud cost programmes at two scale-ups and writes about FinOps.',
			'link'      => 'https://example.com/jordan',
			'samples'   => 'https://example.com/a, not-a-url',
			'reason'    => 'I would like to share practical, vendor-neutral lessons on cloud cost management.',
			'agree'     => true,
			'privacy'   => '1',
		);
	}

	public function test_contact_requires_every_field(): void {
		$result = Forms::dispatch( 'contact', array() );
		$this->assertWPError( $result );
		$this->assertEqualsCanonicalizing( array( 'type', 'name', 'email', 'subject', 'message', 'consent' ), array_keys( $result->get_error_data()['errors'] ) );
	}

	public function test_contact_is_stored_and_routed(): void {
		Options::update( array( 'contact_email_correction' => 'standards@example.com' ) );
		$result = Forms::dispatch( 'contact', $this->contact() );
		$this->assertIsArray( $result );
		$this->assertSame( 'sent', $result['code'] );

		$id = (int) $result['reference'];
		$this->assertSame( PostTypes::MESSAGE, get_post_type( $id ) );
		$this->assertSame( 'standards@example.com', get_post_meta( $id, 'tv_routed_to', true ) );
		$this->assertSame( '0', get_post_meta( $id, 'tv_handled', true ) );

		$to = array_map( static fn( $m ) => $m['to'][0][0], tests_retrieve_phpmailer_instance()->mock_sent );
		$this->assertContains( 'standards@example.com', $to, 'The routed team is notified.' );
		$this->assertContains( 'casey@example.com', $to, 'The sender gets an acknowledgement.' );
	}

	public function test_honeypot_submissions_are_discarded(): void {
		$before = wp_count_posts( PostTypes::MESSAGE )->private ?? 0;
		$result = Forms::dispatch( 'contact', $this->contact() + array( 'website' => 'http://spam.example' ) );
		$this->assertIsArray( $result );
		$this->assertSame( $before, wp_count_posts( PostTypes::MESSAGE )->private ?? 0 );
	}

	public function test_application_is_created_once_per_open_request(): void {
		$result = Forms::dispatch( 'apply', $this->application() );
		$this->assertIsArray( $result );
		$this->assertMatchesRegularExpression( '/^TV-[A-Z0-9]{6}$/', $result['reference'] );

		$apps = get_posts(
			array(
				'post_type'   => PostTypes::APPLICATION,
				'post_status' => 'private',
			)
		);
		$this->assertCount( 1, $apps );
		$id = $apps[0]->ID;
		$this->assertSame( 'pending', get_post_meta( $id, 'tv_status', true ) );
		$this->assertSame( array( 'cloud' ), get_post_meta( $id, 'tv_categories', true ), 'Unknown categories are dropped.' );
		$this->assertSame( array( 'https://example.com/a' ), get_post_meta( $id, 'tv_samples', true ), 'Invalid sample links are dropped.' );
		$this->assertSame( array( 'Cloud', 'FinOps' ), get_post_meta( $id, 'tv_expertise', true ) );

		$again = Forms::dispatch( 'apply', $this->application() );
		$this->assertWPError( $again );
		$this->assertArrayHasKey( 'email', $again->get_error_data()['errors'] );
	}

	public function test_application_validation(): void {
		$data          = $this->application();
		$data['bio']   = 'Too short.';
		$data['link']  = 'ftp://example.com';
		$data['agree'] = false;
		$result        = Forms::dispatch( 'apply', $data );
		$this->assertWPError( $result );
		$errors = $result->get_error_data()['errors'];
		$this->assertArrayHasKey( 'bio', $errors );
		$this->assertArrayHasKey( 'link', $errors );
		$this->assertArrayHasKey( 'agree', $errors );
		$this->assertArrayNotHasKey( 'privacy', $errors );
	}

	public function test_url_check(): void {
		$this->assertTrue( Forms::is_url( 'https://example.com/path' ) );
		$this->assertFalse( Forms::is_url( 'javascript:alert(1)' ) );
		$this->assertFalse( Forms::is_url( 'https://localhost' ) );
		$this->assertFalse( Forms::is_url( '' ) );
	}
}
