<?php
/**
 * Settings: sanitizing, encrypted secrets, constant overrides and SEO helpers.
 *
 * @package Techvenza\Tests
 */

namespace Techvenza\Tests;

use Techvenza\Core\Admin\Settings;
use Techvenza\Core\Settings\Fields;
use Techvenza\Core\Settings\Options;

/**
 * @covers \Techvenza\Core\Admin\Settings
 * @covers \Techvenza\Core\Settings\Options
 */
class SettingsTest extends \WP_UnitTestCase {

	public function tear_down(): void {
		Options::flush(); // The request cache would otherwise outlive the rolled-back option.
		parent::tear_down();
	}

	public function test_sanitize_by_type(): void {
		$f = Fields::all();
		$this->assertSame( '1', Settings::sanitize( $f['consent_enabled'], '1' ) );
		$this->assertSame( '0', Settings::sanitize( $f['consent_enabled'], 'yes' ) );
		$this->assertSame( 'brevo', Settings::sanitize( $f['esp_provider'], 'brevo' ) );
		$this->assertSame( 'local', Settings::sanitize( $f['esp_provider'], 'evil' ), 'Unknown options fall back to the default.' );
		$this->assertSame( 12, Settings::sanitize( $f['review_sla_days'], '12abc' ) );
		$this->assertSame( '', Settings::sanitize( $f['color_ink'], 'red; background:url(x)' ) );
		$this->assertSame( '#111827', Settings::sanitize( $f['color_ink'], '#111827' ) );
		$this->assertSame( '/the-agentic-enterprise/', Settings::sanitize( $f['announce_link_url'], '/the-agentic-enterprise/' ) );
		$this->assertSame( '', Settings::sanitize( $f['announce_link_url'], 'javascript:alert(1)' ) );
		$this->assertSame( array( 'hero' ), Settings::sanitize( $f['home_hidden'], array( 'hero', 'not-a-section', '' ) ) );
		$this->assertSame( "One\nTwo", Settings::sanitize( $f['checklist'], "One\n\n<b>Two</b>\n" ) );
	}

	public function test_secrets_are_encrypted_and_kept_when_left_empty(): void {
		Options::update( array( 'brevo_api_key' => 'xkeysib-secret-value' ) );
		$raw = get_option( 'techvenza_settings' );
		$this->assertNotSame( 'xkeysib-secret-value', $raw['brevo_api_key'], 'Stored value is encrypted.' );
		$this->assertSame( 'xkeysib-secret-value', Options::get( 'brevo_api_key' ) );

		Options::update( array( 'brevo_api_key' => '' ) );
		$this->assertSame( 'xkeysib-secret-value', Options::get( 'brevo_api_key' ), 'An empty submission keeps the secret.' );

		Options::update( array( 'brevo_api_key' => '__clear__' ) );
		$this->assertSame( '', Options::get( 'brevo_api_key' ) );
	}

	public function test_constants_override_stored_values(): void {
		Options::update( array( 'seo_twitter' => '@stored' ) );
		if ( ! defined( 'TECHVENZA_SEO_TWITTER' ) ) {
			define( 'TECHVENZA_SEO_TWITTER', '@constant' );
		}
		$this->assertTrue( Options::is_constant( 'seo_twitter' ) );
		$this->assertSame( '@constant', Options::get( 'seo_twitter' ) );
	}

	public function test_review_checklist_comes_from_settings(): void {
		Options::update( array( 'checklist' => "First check\nSecond check" ) );
		$this->assertSame( array( 'First check', 'Second check' ), \Techvenza\Core\Admin\Review::checklist() );
	}
}
