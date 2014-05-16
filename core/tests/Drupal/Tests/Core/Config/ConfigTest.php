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
      array(
        // Valid name with dot.
        'test.name',
      ),
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
    $this->assertConfigDataEquals($data);
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
    $this->assertOriginalConfigDataEquals($data);
  }

  /**
   * Check that overrides are returned by get method and original data is maintained.
   *
   * @dataProvider overrideDataProvider
   */
  public function testOverrideData($data, $moduleData, $settingData) {
    // Set initial data.
    $this->config->setData($data);

    // Check original data was set correctly.
    $this->assertConfigDataEquals($data);

    // Save so that the original data is stored.
    $this->config->save();

    // Set module override data and check value before and after save.
    $this->config->setModuleOverride($moduleData);
    $this->assertConfigDataEquals($moduleData);
    $this->config->save();
    $this->assertConfigDataEquals($moduleData);

    // Set setting override data and check value before and after save.
    $this->config->setSettingsOverride($settingData);
    $this->assertConfigDataEquals($settingData);
    $this->config->save();
    $this->assertConfigDataEquals($settingData);

    // Set module overrides again to ensure override order is correct.
    $this->config->setModuleOverride($moduleData);

    // setting data should be overriding module data.
    $this->assertConfigDataEquals($settingData);
    $this->config->save();
    $this->assertConfigDataEquals($settingData);

    // Check original data has not changed.
    $this->assertOriginalConfigDataEquals($data, FALSE);

    // Check setting overrides are returned with $apply_overrides = TRUE.
    $this->assertOriginalConfigDataEquals($settingData, TRUE);

    // Check $apply_overrides defaults to TRUE.
    $this->assertOriginalConfigDataEquals($settingData);
  }

  /**
   * Check that data is set correctly.
   *
   * @dataProvider structuredDataProvider
   */
  public function testSetValue($data) {
    foreach ($data as $key => $value) {
      $this->config->set($key, $value);
    }
    $this->assertConfigDataEquals($data);
  }

  /**
   * Check that single value cannot be overwritten with a nested value.
   *
   * @expectedException PHPUnit_Framework_Error_Warning
   */
  public function testSetIllegalOffsetValue() {
    // Set a single value.
    $this->config->set('testData', 1);

    // Attempt to treat the single value as a nested item.
    $this->config->set('testData.illegalOffset', 1);
  }

  /**
   * Check that config can be initialized with data.
   *
   * @dataProvider structuredDataProvider
   */
  public function testInitWithData($data) {
    $config = $this->config->initWithData($data);

    // Should return the Config object.
    $this->assertInstanceOf('\Drupal\Core\Config\Config', $config);

    // Check config is not new.
    $this->assertEquals(FALSE, $this->config->isNew());

    // Check that data value was set correctly.
    $this->assertConfigDataEquals($data);

    // Check that original data was set.
    $this->assertOriginalConfigDataEquals($data);
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

    // Check that original data is still correct.
    $this->assertOriginalConfigDataEquals($data, FALSE);

    // Check overrides have been set.
    $this->assertConfigDataEquals($moduleData);
    $this->assertOriginalConfigDataEquals($moduleData, TRUE);

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

  /**
   * Asserts all config data equals $data provided.
   */
  public function assertConfigDataEquals($data) {
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
   * Asserts all original config data equals $data provided.
   */
  public function assertOriginalConfigDataEquals($data, $apply_overrides = null) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        // Check each nested value.
        foreach($value as $nestedKey => $nestedValue) {
          $fullNestedKey = $key . '.' . $nestedKey;
          if (is_null($apply_overrides)) {
            $configValue = $this->config->getOriginal($fullNestedKey);
          }
          else {
            $configValue = $this->config->getOriginal($fullNestedKey, $apply_overrides);
          }
          $this->assertEquals($nestedValue, $configValue);
        }
      }
      else {
        // Check single value.
        if (is_null($apply_overrides)) {
          $configValue = $this->config->getOriginal($key);
        }
        else {
          $configValue = $this->config->getOriginal($key, $apply_overrides);
        }
        $this->assertEquals($value, $configValue);
      }
    }
  }
}
