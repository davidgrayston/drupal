<?php

/**
 * @file
 * Contains \Drupal\comment\Tests\CommentCacheTagsTest.
 */

namespace Drupal\comment\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\system\Tests\Entity\EntityWithUriCacheTagsTestBase;

/**
 * Tests the Comment entity's cache tags.
 */
class CommentCacheTagsTest extends EntityWithUriCacheTagsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('comment');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return parent::generateStandardizedInfo('Comment', 'Comment');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Give anonymous users permission to view comments, so that we can verify
    // the cache tags of cached versions of comment pages.
    $user_role = entity_load('user_role', DRUPAL_ANONYMOUS_RID);
    $user_role->grantPermission('access comments');
    $user_role->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "bar" bundle for the "entity_test" entity type and create.
    $bundle = 'bar';
    entity_test_create_bundle($bundle, NULL, 'entity_test');

    // Create a comment field on this bundle.
    \Drupal::service('comment.manager')->addDefaultField('entity_test', 'bar');

    // Create a "Camelids" test entity.
    $entity_test = entity_create('entity_test', array(
      'name' => 'Camelids',
      'type' => 'bar',
    ));
    $entity_test->save();

    // Create a "Llama" comment.
    $comment = entity_create('comment', array(
      'subject' => 'Llama',
      'comment_body' => array(
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ),
      'entity_id' => $entity_test->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'comment',
      'status' => \Drupal\comment\CommentInterface::PUBLISHED,
    ));
    $comment->save();

    return $comment;
  }

  /**
   * {@inheritdoc}
   *
   * Each comment must have a comment body, which always has a text format.
   */
  protected function getAdditionalCacheTagsForEntity(EntityInterface $entity) {
    return array('filter_format:plain_text');
  }

}
