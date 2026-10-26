<?php
/**
 * Editorial workflow engine.
 *
 * @package Techvenza\Tests
 */

namespace Techvenza\Tests;

use Techvenza\Core\Database\Notifications;
use Techvenza\Core\Workflow\Workflow;

/**
 * @covers \Techvenza\Core\Workflow\Workflow
 * @covers \Techvenza\Core\Workflow\Capabilities
 */
class WorkflowTest extends \WP_UnitTestCase {

	/**
	 * Author.
	 *
	 * @var int
	 */
	private int $author;

	/**
	 * Editor.
	 *
	 * @var int
	 */
	private int $editor;

	/**
	 * Managing editor.
	 *
	 * @var int
	 */
	private int $managing;

	/**
	 * Category.
	 *
	 * @var int
	 */
	private int $cat;

	public function set_up(): void {
		parent::set_up();
		$this->author   = self::factory()->user->create( array( 'role' => 'tv_author' ) );
		$this->editor   = self::factory()->user->create( array( 'role' => 'tv_editor' ) );
		$this->managing = self::factory()->user->create( array( 'role' => 'tv_managing_editor' ) );
		$this->cat      = self::factory()->category->create( array( 'name' => 'Testing' ) );
	}

	/**
	 * A draft that passes the submission checklist.
	 */
	private function draft(): int {
		return self::factory()->post->create(
			array(
				'post_author'   => $this->author,
				'post_status'   => 'draft',
				'post_title'    => 'A sufficiently descriptive title',
				'post_excerpt'  => 'An excerpt that is comfortably longer than forty characters.',
				'post_content'  => str_repeat( 'Practical words about engineering teams. ', 40 ),
				'post_category' => array( $this->cat ),
			)
		);
	}

	public function test_roles_are_installed(): void {
		foreach ( array( 'tv_applicant', 'tv_author', 'tv_editor', 'tv_managing_editor' ) as $role ) {
			$this->assertNotNull( get_role( $role ), $role );
		}
		$this->assertTrue( user_can( $this->editor, 'tv_review_posts' ) );
		$this->assertFalse( user_can( $this->author, 'tv_review_posts' ) );
		$this->assertTrue( user_can( $this->managing, 'tv_publish_posts' ) );
	}

	public function test_full_happy_path(): void {
		$id = $this->draft();

		$this->assertTrue( Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) ) );
		$this->assertSame( 'submitted', get_post_status( $id ) );

		$this->assertTrue( Workflow::transition( $id, 'start', array( 'user_id' => $this->editor ) ) );
		$this->assertSame( 'in_review', get_post_status( $id ) );
		$this->assertSame( $this->editor, (int) get_post_meta( $id, 'tv_assigned_editor', true ) );

		$this->assertTrue( Workflow::transition( $id, 'approve', array( 'user_id' => $this->editor ) ) );
		$this->assertSame( 'approved', get_post_status( $id ) );

		$this->assertTrue( Workflow::transition( $id, 'publish', array( 'user_id' => $this->managing ) ) );
		$this->assertSame( 'publish', get_post_status( $id ) );

		$actions = wp_list_pluck( Workflow::timeline( $id ), 'action' );
		$this->assertSame( array( 'submit', 'start', 'approve', 'publish' ), array_values( array_intersect( $actions, array( 'submit', 'start', 'approve', 'publish' ) ) ) );
	}

	public function test_checklist_blocks_incomplete_submission(): void {
		$id     = self::factory()->post->create(
			array(
				'post_author' => $this->author,
				'post_status' => 'draft',
				'post_title'  => 'Short',
			)
		);
		$result = Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );
		$this->assertWPError( $result );
		$this->assertSame( 'tv_checklist', $result->get_error_code() );
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_author_cannot_approve_or_publish(): void {
		$id = $this->draft();
		Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );
		$result = Workflow::transition( $id, 'approve', array( 'user_id' => $this->author ) );
		$this->assertWPError( $result );
		$this->assertSame( 'tv_forbidden', $result->get_error_code() );
	}

	public function test_editor_cannot_publish(): void {
		$id = $this->draft();
		Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );
		Workflow::transition( $id, 'approve', array( 'user_id' => $this->editor ) );
		$this->assertWPError( Workflow::transition( $id, 'publish', array( 'user_id' => $this->editor ) ) );
	}

	public function test_invalid_transition_is_rejected(): void {
		$id     = $this->draft();
		$result = Workflow::transition( $id, 'publish', array( 'user_id' => $this->managing ) );
		$this->assertWPError( $result );
		$this->assertSame( 'tv_bad_transition', $result->get_error_code() );
	}

	public function test_request_changes_requires_note_and_notifies_author(): void {
		$id = $this->draft();
		Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );

		$missing = Workflow::transition( $id, 'request', array( 'user_id' => $this->editor ) );
		$this->assertWPError( $missing );
		$this->assertSame( 'tv_note_required', $missing->get_error_code() );

		$this->assertTrue(
			Workflow::transition(
				$id,
				'request',
				array(
					'user_id' => $this->editor,
					'note'    => 'Please add sources for the second section.',
				)
			)
		);
		$this->assertSame( 'changes_requested', get_post_status( $id ) );
		$this->assertCount( 1, Workflow::feedback( $id ) );
		$this->assertGreaterThanOrEqual( 1, Notifications::unread( $this->author ) );
	}

	public function test_schedule_requires_future_date(): void {
		$id = $this->draft();
		Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );
		Workflow::transition( $id, 'approve', array( 'user_id' => $this->editor ) );

		$past = Workflow::transition(
			$id,
			'schedule',
			array(
				'user_id' => $this->managing,
				'date'    => '2001-01-01 09:00',
			)
		);
		$this->assertWPError( $past );

		$future = gmdate( 'Y-m-d H:i', time() + 3 * DAY_IN_SECONDS );
		$this->assertTrue(
			Workflow::transition(
				$id,
				'schedule',
				array(
					'user_id' => $this->managing,
					'date'    => $future,
				)
			)
		);
		$this->assertSame( 'future', get_post_status( $id ) );
	}

	public function test_author_loses_edit_rights_while_in_review(): void {
		$id = $this->draft();
		$this->assertTrue( user_can( $this->author, 'edit_post', $id ) );
		Workflow::transition( $id, 'submit', array( 'user_id' => $this->author ) );
		clean_post_cache( $id );
		$this->assertFalse( user_can( $this->author, 'edit_post', $id ) );
		$this->assertTrue( user_can( $this->editor, 'edit_post', $id ) );
	}

	public function test_author_cannot_edit_someone_elses_draft(): void {
		$other = self::factory()->user->create( array( 'role' => 'tv_author' ) );
		$id    = $this->draft();
		$this->assertFalse( user_can( $other, 'edit_post', $id ) );
	}

	public function test_suspended_account_loses_capabilities(): void {
		update_user_meta( $this->author, 'tv_account_status', 'suspended' );
		$this->assertFalse( user_can( $this->author, 'edit_posts' ) );
		$this->assertTrue( user_can( $this->author, 'read' ) );
	}

	public function test_core_pending_status_maps_to_submitted(): void {
		$id = $this->draft();
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'pending',
			)
		);
		$this->assertSame( 'submitted', get_post_status( $id ) );
	}
}
