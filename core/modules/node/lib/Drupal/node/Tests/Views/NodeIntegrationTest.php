<?php

/**
 * @file
 * Contains \Drupal\node\Tests\Views\NodeIntegrationTest.
 */

namespace Drupal\node\Tests\Views;

/**
 * Tests Node module's Views integration.
 */
class NodeIntegrationTest extends NodeTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_node_view');

  public static function getInfo() {
    return array(
      'name' => 'Node: Views data',
      'description' => 'Tests the node integration into views.',
      'group' => 'Views module integration',
    );
  }

  /**
   * Tests basic node view with a node type argument.
   */
  public function testNodeViewTypeArgument() {
    // Create two content types with three nodes each.
    $types = array();
    $all_nids = array();
    for ($i = 0; $i < 2; $i++) {
      $type = $this->drupalCreateContentType();
      $types[] = $type;

      for ($j = 0; $j < 5; $j++) {
        // Ensure the right order of the nodes.
        $node = $this->drupalCreateNode(array('type' => $type->type, 'created' => REQUEST_TIME - ($i * 5 + $j)));
        $nodes[$type->type][$node->id()] = $node;
        $all_nids[] = $node->id();
      }
    }

    $this->drupalGet('test-node-view');
    $this->assertResponse(404);

    $this->drupalGet('test-node-view/all');
    $this->assertResponse(200);
    $this->assertNids($all_nids);

    foreach ($types as $type) {
      $this->drupalGet("test-node-view/{$type->type}");
      $this->assertNids(array_keys($nodes[$type->type]));
    }
  }

  /**
   * Ensures that a list of nodes appear on the page.
   *
   * @param array $expected_nids
   *   An array of node IDs.
   */
  protected function assertNids(array $expected_nids = array()) {
    $result = $this->xpath('//span[@class="field-content"]');
    $nids = array();
    foreach ($result as $element) {
      $nids[] = (int) $element;
    }
    $this->assertEqual($nids, $expected_nids);
  }

}
