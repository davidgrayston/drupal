<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Tests\d6\MigrateCckFieldValuesTest.
 */

namespace Drupal\migrate_drupal\Tests\d6;

use Drupal\migrate\MigrateExecutable;

/**
 * Test cck field migration from Drupal 6 to 8.
 */
class MigrateCckFieldValuesTest extends MigrateNodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'text');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name'  => 'Migrate CCK fields',
      'description'  => 'CCK field content migration',
      'group' => 'Migrate Drupal',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    entity_create('field_config', array(
      'entity_type' => 'node',
      'name' => 'field_test',
      'type' => 'text',
    ))->save();
    entity_create('field_instance_config', array(
      'entity_type' => 'node',
      'field_name' => 'field_test',
      'bundle' => 'story',
    ))->save();
    entity_create('field_config', array(
      'entity_type' => 'node',
      'name' => 'field_test_two',
      'type' => 'integer',
      'cardinality' => -1,
    ))->save();
    entity_create('field_instance_config', array(
      'entity_type' => 'node',
      'field_name' => 'field_test_two',
      'bundle' => 'story',
    ))->save();

    // Add some id mappings for the dependant migrations.
    $id_mappings = array(
      'd6_field_formatter_settings' => array(
        array(array('page', 'default', 'node', 'field_test'), array('node', 'page', 'default', 'field_test')),
      ),
      'd6_field_instance_widget_settings' => array(
        array(array('page', 'field_test'), array('node', 'page', 'default', 'test')),
      ),
      'd6_node' => array(
        array(array(1), array(1)),
        array(array(2), array(2)),
      ),
    );
    $this->prepareIdMappings($id_mappings);

    $migrations = entity_load_multiple('migration', array('d6_cck_field_values:*'));
    foreach ($migrations as $migration) {
      $executable = new MigrateExecutable($migration, $this);
      $executable->import();
    }

  }

  /**
   * Test CCK migration from Drupal 6 to 8.
   */
  public function testCckFields() {
    $node = node_load(1);
    $this->assertEqual($node->field_test->value, 'This is a text field', "Single field storage field is correct.");
    $this->assertEqual($node->field_test_two->value, 10, 'Multi field storage field is correct');
    $this->assertEqual($node->field_test_two[1]->value, 20, 'Multi field second value is correct.');
  }

}
