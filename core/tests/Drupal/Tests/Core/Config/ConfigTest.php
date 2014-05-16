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
   *
   * @dataProvider setNameProvider
   */
  public function testSetName($name) {
     // Set the name.
    $this->config->setName($name);

    // Check that the name has been set correctly.
    $this->assertEquals($name, $this->config->getName());

    // Check that the name validates.
    // Should throw \Drupal\Core\Config\ConfigNameException if invalid.
    $this->config->validateName($name);
  }

  /**
   * Provide config names to test.
   */
  public function setNameProvider() {
    return array(
      // Valid name with dot.
      'test.name',
    );
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
   *
   * @dataProvider structuredDataProvider
   */
  public function testSetData($data) {
    $this->config->setData($data);
    $this->assertEquals($data, $this->config->getRawData());
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        // Check each nested value.
        foreach($value as $nestedKey => $nestedValue) {
          $this->assertEquals($nestedValue, $this->config->get($key . '.' . $nestedKey));
        }
      }
      else {
        // Check single value.
        $this->assertEquals($value, $this->config->get($key));
      }
    }
  }

  /**
   * Check that original data is set when config is saved.
   *
   * @dataProvider structuredDataProvider
   */
  public function testSave($data) {
    // Set initial data.
    $this->config->setData($data);

    // Check that original data has not been set yet.
    foreach ($data as $key => $value) {
      $this->assertNull($this->config->getOriginal($key, FALSE));
    }

    // Save so that the original data is set.
    $config = $this->config->save();

    // Check that returned $config is instance of Config.
    $this->assertInstanceOf('\Drupal\Core\Config\Config', $config);

    // Check that the original data it saved.
    if (is_array($value)) {
      // Check each nested value.
      foreach($value as $nestedKey => $nestedValue) {
        $this->assertEquals($nestedValue, $this->config->getOriginal($key . '.' . $nestedKey));
      }
    }
    else {
      // Check single value.
      $this->assertEquals($value, $this->config->getOriginal($key));
    }
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
   *
   * @dataProvider basicDataProvider
   */
  public function testSetValue($data) {
    foreach ($data as $key => $value) {
      $this->config->set($key, $value);
      $this->assertEquals($value, $this->config->get($key));
    }
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
   *
   * @dataProvider structuredDataProvider
   */
  public function testClear($data) {
    foreach ($data as $key => $value) {
      // Check that values are cleared.
      $this->config->set($key, $value);
      if (is_array($value)) {
        // Check each nested value.
        foreach($value as $nestedKey => $nestedValue) {
          $fullNestedKey = $key . '.' . $nestedKey;
          $this->assertEquals($nestedValue, $this->config->get($fullNestedKey));
          $this->config->clear($fullNestedKey);
          $this->assertNull($this->config->get($fullNestedKey));
        }
      }
      else {
        // Check single values.
        $this->assertEquals($value, $this->config->get($key));
        $this->config->clear($key);
        $this->assertNull($this->config->get($key));
      }
    }
  }

  /**
   * Check that config delete is working correctly.
   *
   * @dataProvider overrideDataProvider
   */
  public function testDelete($data, $moduleData) {
    // Set initial data.
    foreach ($data as $key => $value) {
      $this->config->set($key, $value);
    }
    // Set overrides.
    $this->config->setModuleOverride($moduleData);

    // Save.
    $this->config->save();

    // Check that values have been set correctly.
    foreach ($data as $key => $value) {
      $this->assertEquals($value, $this->config->getOriginal($key, FALSE));
    }
    // Check overrides have been set.
    foreach ($moduleData as $key => $value) {
      $this->assertEquals($value, $this->config->get($key));
      $this->assertEquals($value, $this->config->getOriginal($key, TRUE));
    }

    // Check that config is new.
    $this->assertFalse($this->config->isNew());

    // Delete.
    $this->config->delete();

    // Check object properties have been reset.
    $this->assertTrue($this->config->isNew());
    foreach ($data as $key => $value) {
      $this->assertEmpty($this->config->get($key));
      $this->assertEmpty($this->config->getOriginal($key, FALSE));
    }

    // Check that overrides have been reset.
    foreach ($moduleData as $key => $value) {
      $this->assertEmpty($this->config->get($key));
      $this->assertEmpty($this->config->getOriginal($key, TRUE));
    }
  }

  /**
   * Check that data merges correctly.
   *
   * @dataProvider mergeDataProvider
   */
  public function testMerge($data, $dataToMerge, $mergedData) {
    // Set initial data.
    $this->config->setData($data);

    // Data to merge.
    $this->config->merge($dataToMerge);

    // Check that data has merged correctly.
    $this->assertEquals($mergedData, $this->config->getRawData());
  }

  /**
   * Provides data to test merges.
   */
  public function mergeDataProvider() {
    return array(
      array(
        // Data.
        array('a' => 1, 'b' => 2, 'c' => array('d' => 3)),
        // Data to merge.
        array('a' => 2, 'e' => 4, 'c' => array('f' => 5)),
        // Data merged.
        array('a' => 2, 'b' => 2, 'c' => array('d' => 3, 'f' => 5), 'e' => 4),
      )
    );
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

  /**
   * Override data provider.
   */
  public function overrideDataProvider() {
    return array(
      array(
        // Original data.
        array(
          'a' => 'originalValue'
        ),
        // Module overrides.
        array(
          'a' => 'moduleValue'
        ),
        // Setting overrides.
        array(
          'a' => 'settingValue'
        ),
      )
    );
  }

  /**
   * Provides structured test data.
   */
  public function structuredDataProvider() {
    return array(
      array(
        array(
          'a' => 1,
          'b' => 'testValue',
          'c' => array(
            'd' => 2
          )
        ),
      ),
    );
  }

}
