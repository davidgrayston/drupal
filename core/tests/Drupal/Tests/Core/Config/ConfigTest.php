<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Config\ConfigTest.
 */

namespace Drupal\Tests\Core\Config;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\Config;
use Drupal\Component\Utility\String;

/**
 * Tests the Config.
 *
 * @group Drupal
 * @group Config
 *
 * @see \Drupal\Core\Config\Config
 */
class ConfigTest extends UnitTestCase {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * Event Dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $event_dispatcher;

  /**
   * Typed Config.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typed_config;

  /**
   * Test Info.
   */
  public static function getInfo() {
    return array(
      'name' => 'Config test',
      'description' => 'Tests Config.',
      'group' => 'Configuration'
    );
  }

  /**
   * Setup.
   */
  public function setUp() {
    $this->storage = $this->getMock('Drupal\Core\Config\StorageInterface');
    $this->event_dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $this->typed_config = $this->getMock('\Drupal\Core\Config\TypedConfigManagerInterface');
    $this->config = new Config('config.test', $this->storage, $this->event_dispatcher, $this->typed_config);
  }

  /**
   * Check that the config name is set correctly.
   */
  public function testSetName() {
    // Valid name with dot.
    $testName = 'test.name';

    // Set the name.
    $this->config->setName($testName);

    // Check that the name has been set correctly.
    $this->assertEquals($testName, $this->config->getName());

    // Check that the name validates.
    // Should throw \Drupal\Core\Config\ConfigNameException if invalid.
    $this->config->validateName($testName);
  }

  /**
   * Check that isNew is set correctly.
   */
  public function testIsNew() {
    // Config should be new by default.
    $this->assertTrue($this->config->isNew());

    // Config is no longer new once saved.
    $this->config->save();
    $this->assertFalse($this->config->isNew());
  }

  /**
   * Check that data is set correctly.
   */
  public function testSetData() {
    $data = array('a' => 1, 'b' => 2, 'c' => array('d' => 3));
    $this->config->setData($data);
    $this->assertEquals($data, $this->config->getRawData());
    $this->assertEquals(1, $this->config->get('a'));
    $this->assertEquals(3, $this->config->get('c.d'));
  }

  /**
   * Check that original data is set when config is saved.
   */
  public function testSave() {
    // Set initial data.
    $this->config->setData(array('a' => 'testVal'));

    // Check that original data has not been set yet.
    $this->assertNull($this->config->getOriginal('a', FALSE));

    // Save so that the original data is set.
    $config = $this->config->save();

    // Check that returned $config is instance of Config.
    $this->assertInstanceOf('\Drupal\Core\Config\Config', $config);

    // Check that the original data it saved.
    $this->assertEquals($this->config->getOriginal('a', FALSE), 'testVal');
  }

  /**
   * Check that overrides are returned by get method and original data is maintained.
   */
  public function testOverrideData() {
    // Set initial data.
    $this->config->setData(array('a' => 1));

    // Check original data was set correctly.
    $this->assertEquals($this->config->get('a'), 1);

    // Save so that the original data is stored.
    $this->config->save();

    // Set module override data and check value before and after save.
    $this->config->setModuleOverride(array('a' => 3));
    $this->assertEquals($this->config->get('a'), 3);
    $this->config->save();
    $this->assertEquals($this->config->get('a'), 3);

    // Set settings override data and check value before and after save.
    $this->config->setSettingsOverride(array('a' => 4));
    $this->assertEquals($this->config->get('a'), 4);
    $this->config->save();
    $this->assertEquals($this->config->get('a'), 4);

    // Set module again to ensure override order is correct.
    $this->config->setModuleOverride(array('a' => 3));

    // 'a' should still be '4' after setting module overrides.
    $this->assertEquals($this->config->get('a'), 4);
    $this->config->save();
    $this->assertEquals($this->config->get('a'), 4);

    // Check original data has not changed.
    $this->assertEquals($this->config->getOriginal('a', FALSE), 1);

    // Check correct override value '4' is returned with $apply_overrides = TRUE.
    $this->assertEquals($this->config->getOriginal('a', TRUE), 4);

    // Check $apply_overrides defaults to TRUE.
    $this->assertEquals($this->config->getOriginal('a'), 4);
  }

  /**
   * Check that data is set correctly.
   */
  public function testSetValue() {
    // Check single value.
    $this->config->set('testData', 'testDataValue');
    $this->assertEquals('testDataValue', $this->config->get('testData'));

    // Check nested value.
    $this->config->set('nested.testData', 'nestedDataValue');
    $this->assertEquals('nestedDataValue', $this->config->get('nested.testData'));
  }

  /**
   * Check that single value cannot be overwritten with a nested value.
   *
   * @expectedException PHPUnit_Framework_Error_Warning
   */
  public function testSetIllegalOffsetValue() {
    // Set a single value.
    $this->config->set('testData', 'testDataValue');

    // Attempt to treat the single value as a nested item.
    $this->config->set('testData.illegalOffset', 'testDataValue');
  }

  /**
   * Check that config can be initialized with data.
   */
  public function testInitWithData() {
    $config = $this->config->initWithData(array('a' => 'b'));

    // Should return the Config object.
    $this->assertInstanceOf('\Drupal\Core\Config\Config', $config);

    // Check config is not new.
    $this->assertEquals(FALSE, $this->config->isNew());

    // Check that data value was set correctly.
    $this->assertEquals('b', $this->config->get('a'));

    // Check that original data was set.
    $this->assertEquals('b', $this->config->getOriginal('a'));
  }

  /**
   * Check clear.
   */
  public function testClear() {
    // Check that value is cleared.
    $this->config->set('a', 'testVal');
    $this->assertEquals('testVal', $this->config->get('a'));
    $this->config->clear('a');
    $this->assertNull($this->config->get('a'));

    // Check that nested value is cleared.
    $this->config->set('a', array('b' => 'testVal'));
    $this->assertEquals('testVal', $this->config->get('a.b'));
    $this->config->clear('a.b');
    $this->assertNull($this->config->get('a.b'));
  }

  /**
   * Check that config delete is working correctly.
   */
  public function testDelete() {
    // Set initial data.
    $this->config->set('a', 'testVal');
    $this->config->setModuleOverride(array('a' => 'overrideVal'));

    // Save.
    $this->config->save();

    // Check that values have been set correctly.
    $this->assertEquals('overrideVal', $this->config->get('a'));
    $this->assertEquals('overrideVal', $this->config->getOriginal('a', TRUE));
    $this->assertEquals('testVal', $this->config->getOriginal('a', FALSE));
    $this->assertFalse($this->config->isNew());

    // Delete.
    $this->config->delete();

    // Check object properties have been reset.
    $this->assertTrue($this->config->isNew());
    $this->assertEmpty($this->config->get('a'));
    $this->assertEmpty($this->config->getOriginal('a', TRUE));
    $this->assertEmpty($this->config->getOriginal('a', FALSE));
  }

  /**
   * Check that data merges correctly.
   */
  public function testMerge() {
    // Set initial data.
    $data = array('a' => 1, 'b' => 2, 'c' => array('d' => 3));
    $this->config->setData($data);

    // Data to merge.
    $dataToMerge = array('a' => 2, 'e' => 4, 'c' => array('f' => 5));
    $this->config->merge($dataToMerge);

    // Check that data has merged correctly.
    $mergedData = array('a' => 2, 'b' => 2, 'c' => array('d' => 3, 'f' => 5), 'e' => 4);
    $this->assertEquals($mergedData, $this->config->getRawData());
  }

  /**
   * Checks that name validation exception are thrown.
   *
   * @expectedException \Drupal\Core\Config\ConfigNameException
   * @dataProvider validateNameProvider
   */
  public function testValidateNameException($name, $exception_message) {
    $this->setExpectedException('\Drupal\Core\Config\ConfigNameException', $exception_message);
    $this->config->validateName($name);
  }

  /**
   * Provides data to test name validation.
   */
  public function validateNameProvider() {
    $return = array(
      // Name missing namespace (dot).
      array(
        'MissingNamespace',
        String::format('Missing namespace in Config object name MissingNamespace.', array(
          '@name' => 'MissingNamespace',
        )),
      ),
      // Exceeds length (max length plus an extra dot).
      array(
        str_repeat('a', Config::MAX_NAME_LENGTH) . ".",
        String::format('Config object name @name exceeds maximum allowed length of @length characters.', array(
          '@name' => str_repeat('a', Config::MAX_NAME_LENGTH) . ".",
          '@length' => Config::MAX_NAME_LENGTH,
        )),
      ),
    );
    // Name must not contain : ? * < > " ' / \
    foreach (array(':', '?', '*', '<', '>', '"',"'",'/','\\') as $char) {
      $name = 'name.' . $char;
      $return[] = array(
        $name,
        String::format('Invalid character in Config object name @name.', array(
          '@name' => $name,
        ))
      );
    }
    return $return;
  }
}
