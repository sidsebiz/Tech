<?php
/**
 * Phase 11: SVG sanitizing, Markdown, AEO output, partner links and Bulk Blog.
 *
 * @package Techvenza\Tests
 */

namespace Techvenza\Tests;

use Techvenza\Core\Import\Csv;
use Techvenza\Core\Import\Importer;
use Techvenza\Core\Import\MarkdownToHtml;
use Techvenza\Core\Media\Images;
use Techvenza\Core\Media\Svg;
use Techvenza\Core\Seo\Aeo;
use Techvenza\Core\Seo\Links;
use Techvenza\Core\Seo\Markdown;
use Techvenza\Core\Settings\Options;

/**
 * @covers \Techvenza\Core\Media\Svg
 * @covers \Techvenza\Core\Import\Csv
 * @covers \Techvenza\Core\Import\Importer
 * @covers \Techvenza\Core\Seo\Links
 * @covers \Techvenza\Core\Seo\Aeo
 */
class GrowthTest extends \WP_UnitTestCase {

	public function tear_down(): void {
		Options::flush();
		Links::flush();
		parent::tear_down();
	}

	public function test_svg_sanitizer_strips_scripts_handlers_and_external_refs(): void {
		$dirty = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(1)</script>'
			. '<a href="javascript:alert(1)"><rect width="5" height="5"/></a>'
			. '<image href="https://evil.example/x.png"/><foreignObject><p>x</p></foreignObject>'
			. '<circle cx="5" cy="5" r="2" fill="url(https://evil.example/#g)" stroke="#000"/></svg>';
		$clean = Svg::sanitize( $dirty );
		$this->assertStringStartsWith( '<svg', $clean );
		foreach ( array( 'script', 'onload', 'javascript', 'evil.example', 'foreignObject', '<a ' ) as $bad ) {
			$this->assertStringNotContainsStringIgnoringCase( $bad, $clean );
		}
		$this->assertStringContainsString( 'stroke="#000"', $clean );
		$this->assertSame( '', Svg::sanitize( '<!DOCTYPE svg [<!ENTITY x "y">]><svg/>' ) );
		$this->assertSame( '', Svg::sanitize( '<html><body/></html>' ) );
		$this->assertSame( array( 1600, 900 ), Svg::dimensions( '<svg viewBox="0 0 1600 900"/>' ) );
	}

	public function test_bundled_illustrations_cover_every_category(): void {
		$names = Images::names();
		$this->assertContains( 'default', $names );
		$this->assertSame( 'cloud-2', Images::name_for( 'cloud', 1 ) );
		$this->assertSame( 'default', Images::name_for( 'no-such-topic', 1 ) );
		$this->assertStringEndsWith( '/social/default.jpg', Images::social( 'no-such-topic' )['url'] );
		$cover = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$this->assertSame(
			$cover,
			Images::fallback_id(
				array(
					'hero' => $cover,
					'slug' => 'cloud',
				)
			)
		);
	}

	public function test_markdown_round_trip(): void {
		$html = MarkdownToHtml::convert( "## Why it matters\n\nA **bold** claim with [a link](https://example.com).\n\n- one\n- two\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n![Chart](chart.png)" );
		$this->assertStringContainsString( '<h2>Why it matters</h2>', $html );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringContainsString( '<table>', $html );
		$this->assertStringContainsString( 'src="chart.png"', $html );

		$md = Markdown::from_html( $html );
		$this->assertStringContainsString( '## Why it matters', $md );
		$this->assertStringContainsString( '[a link](https://example.com)', $md );
		$this->assertStringContainsString( '- two', $md );
		$this->assertStringContainsString( '| 1 | 2 |', $md );
	}

	public function test_csv_cell_parsers(): void {
		$this->assertSame(
			array(
				array(
					'q' => 'What?',
					'a' => 'This.',
				),
				array(
					'q' => 'Why?',
					'a' => 'Because.',
				),
			),
			Csv::faq( 'What?|This.||Why?|Because.' )
		);
		$this->assertNull( Csv::faq( 'A question without an answer' ) );
		$this->assertCount( 1, (array) Csv::faq( '[{"question":"Q","answer":"A"}]' ) );
		$sources = Csv::sources( 'NIST|https://www.nist.gov/||https://owasp.org/' );
		$this->assertSame( 'NIST', $sources[0]['title'] );
		$this->assertSame( 'https://owasp.org/', $sources[1]['url'] );
		$this->assertSame( 'html', Csv::format( '<p>Hi</p>' ) );
		$this->assertSame( 'markdown', Csv::format( "# Title\n\nText" ) );
		$this->assertSame( 'text', Csv::format( 'Just words.' ) );
		$this->assertSame( '2027-01-15 09:30:00', Csv::date( '2027-01-15 09:30' ) );
		$this->assertNull( Csv::date( 'not a date' ) );
		$this->assertTrue( Csv::yes( 'Yes' ) );
		$this->assertSame( array( 'a', 'b' ), Csv::list( 'a | b,' ) );
	}

	public function test_bulk_import_creates_scheduled_article_with_meta(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$rows = Csv::validate(
			array(
				array(
					'title'            => 'Edge AI in Factories',
					'content'          => "## Intro\n\nModels run **on the line**.",
					'slug'             => 'edge-ai-factories',
					'category'         => 'Manufacturing > Smart Factory',
					'tags'             => 'edge, ai',
					'publish_date'     => '2099-03-01 10:00',
					'meta_title'       => 'Edge AI on the factory floor',
					'meta_description' => 'How manufacturers deploy models at the edge.',
					'faq'              => 'Is it fast?|Yes.',
					'sources'          => 'NIST|https://www.nist.gov/',
				),
				array(
					'title'   => '',
					'content' => '',
				),
			),
			array(
				'status'       => 'publish',
				'duplicates'   => 'skip',
				'create_terms' => true,
			)
		);
		$this->assertSame( array(), $rows[0]['errors'] );
		$this->assertNotEmpty( $rows[1]['errors'] );

		$result = Importer::import_row( $rows[0]['data'], array( 'create_terms' => true ), '', $admin );
		$this->assertSame( 'created', $result['result'] );
		$post = get_post( $result['post'] );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( 'edge-ai-factories', $post->post_name );
		$this->assertStringContainsString( '<!-- wp:heading', $post->post_content );
		$this->assertSame( 'Edge AI on the factory floor', get_post_meta( $post->ID, 'tv_seo_title', true ) );
		$this->assertCount( 1, (array) get_post_meta( $post->ID, 'tv_faq', true ) );
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$this->assertSame( 'Smart Factory', $cats[0]->name );
		$this->assertSame( 'Manufacturing', get_term( $cats[0]->parent )->name );
		$this->assertEqualsCanonicalizing( array( 'edge', 'ai' ), wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ) );

		$again = Importer::import_row( $rows[0]['data'], array( 'duplicates' => 'skip' ), '', $admin );
		$this->assertSame( 'skipped', $again['result'] );
	}

	public function test_partner_links_and_outbound_policy(): void {
		$partner = self::factory()->post->create(
			array(
				'post_type'  => Links::CPT,
				'post_title' => 'Acme Cloud',
			)
		);
		update_post_meta( $partner, 'tv_url', 'https://acme.example/' );
		update_post_meta( $partner, 'tv_rel', 'sponsored' );
		update_post_meta( $partner, 'tv_keywords', 'Acme Cloud, acme' );
		Links::flush();

		$html = Links::autolink( '<p>We tried Acme Cloud and Acme Cloud again.</p><a href="#">Acme Cloud</a>', 3 );
		$this->assertSame( 1, substr_count( $html, 'href="https://acme.example/"' ) );
		$this->assertStringContainsString( 'sponsored', $html );

		$this->assertSame( '', Links::rel_for( home_url( '/about/' ) ) );
		$this->assertStringContainsString( 'sponsored', Links::rel_for( 'https://acme.example/pricing' ) );
		$this->assertStringContainsString( 'nofollow', Links::rel_for( 'https://other.example/', 'nofollow' ) );

		$sponsored = self::factory()->post->create();
		update_post_meta( $sponsored, 'tv_is_sponsored', 1 );
		$this->assertStringContainsString( 'sponsored', Links::rel_for( 'https://other.example/', 'follow', $sponsored ) );
	}

	public function test_robots_and_llms_txt(): void {
		$robots = Aeo::robots_txt( "User-agent: *\nDisallow: /wp-admin/\n\nSitemap: https://example.org/wp-sitemap.xml\n", true );
		$this->assertLessThan( strpos( $robots, 'Sitemap:' ), strpos( $robots, 'Disallow: /dashboard/' ) );
		$this->assertStringContainsString( 'User-agent: OAI-SearchBot', $robots );
		$this->assertStringContainsString( 'User-agent: GPTBot', $robots );

		Options::update( array( 'ai_training_bots' => false ) );
		Options::flush();
		$blocked = Aeo::robots_txt( "User-agent: *\n", true );
		$this->assertMatchesRegularExpression( '/User-agent: GPTBot\n(User-agent: [^\n]+\n)*Disallow: \//', $blocked );

		$post = self::factory()->post->create( array( 'post_title' => 'Answer engines & you' ) );
		update_post_meta( $post, 'tv_summary', 'A short answer.' );
		$llms = Aeo::llms_index();
		$this->assertStringStartsWith( '# ', $llms );
		$this->assertStringContainsString( 'Answer engines & you', $llms );
		$this->assertStringContainsString( 'A short answer.', $llms );
	}
}
