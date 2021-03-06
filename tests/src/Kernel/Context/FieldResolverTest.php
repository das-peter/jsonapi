<?php

namespace Drupal\Tests\jsonapi\Kernel\Context;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Context\FieldResolver
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class FieldResolverTest extends JsonapiKernelTestBase {

  public static $modules = [
    'entity_test',
    'serialization',
    'field',
    'text',
    'user',
  ];

  /**
   * The subject under test.
   *
   * @var \Drupal\jsonapi\Context\FieldResolver
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->sut = \Drupal::service('jsonapi.field_resolver');

    $this->makeBundle('bundle1');
    $this->makeBundle('bundle2');
    $this->makeBundle('bundle3');

    $this->makeField('string', 'field_test1', 'entity_test_with_bundle', ['bundle1']);
    $this->makeField('string', 'field_test2', 'entity_test_with_bundle', ['bundle1']);
    $this->makeField('string', 'field_test3', 'entity_test_with_bundle', ['bundle2', 'bundle3']);

    // Provides entity reference fields.
    $settings = ['target_type' => 'entity_test_with_bundle'];
    $this->makeField('entity_reference', 'field_test_ref1', 'entity_test_with_bundle', ['bundle1'], $settings, [
      'handler_settings' => [
        'target_bundles' => ['bundle2', 'bundle3'],
      ],
    ]);
    $this->makeField('entity_reference', 'field_test_ref2', 'entity_test_with_bundle', ['bundle1'], $settings);
    $this->makeField('entity_reference', 'field_test_ref3', 'entity_test_with_bundle', ['bundle2', 'bundle3'], $settings);

    // Add a field with multiple properties.
    $this->makeField('text', 'field_test_text', 'entity_test_with_bundle', ['bundle1', 'bundle2']);
  }

  /**
   * @covers ::resolveInternal
   * @dataProvider resolveInternalProvider
   */
  public function testResolveInternal($expect, $external_path, $entity_type_id = 'entity_test_with_bundle', $bundle = 'bundle1') {
    $this->assertEquals($expect, $this->sut->resolveInternal($entity_type_id, $bundle, $external_path));
  }

  /**
   * Provides test cases for field resolution.
   */
  public function resolveInternalProvider() {
    return [
      'config entity as base' => [
        'uuid', 'uuid', 'entity_test_bundle', 'entity_test_bundle',
      ],
      'config entity as target' => ['type.entity:entity_test_bundle.uuid', 'type.uuid'],

      'primitive field; variation A' => ['field_test1', 'field_test1'],
      'primitive field; variation B' => ['field_test2', 'field_test2'],

      'entity reference then a primitive field; variation A' => ['field_test_ref2.entity:entity_test_with_bundle.field_test1', 'field_test_ref2.field_test1'],
      'entity reference then a primitive field; variation B' => ['field_test_ref2.entity:entity_test_with_bundle.field_test2', 'field_test_ref2.field_test2'],

      'entity reference then a complex field with no property specifier' => ['field_test_ref2.entity:entity_test_with_bundle.field_test_text', 'field_test_ref2.field_test_text'],
      'entity reference then a complex field with property specifier `value`' => ['field_test_ref2.entity:entity_test_with_bundle.field_test_text.value', 'field_test_ref2.field_test_text.value'],
      'entity reference then a complex field with property specifier `format`' => ['field_test_ref2.entity:entity_test_with_bundle.field_test_text.format', 'field_test_ref2.field_test_text.format'],

      'entity reference then no delta with property specifier `target_id`' => ['field_test_ref1.target_id', 'field_test_ref1.target_id'],
      'entity reference then delta 0 with property specifier `target_id`' => ['field_test_ref1.0.target_id', 'field_test_ref1.0.target_id'],
      'entity reference then delta 1 with property specifier `target_id`' => ['field_test_ref1.1.target_id', 'field_test_ref1.1.target_id'],

      'entity reference then no reference property then a complex field' => ['field_test_ref1.entity:entity_test_with_bundle.field_test_text', 'field_test_ref1.field_test_text'],
      'entity reference then reference property then a complex field' => ['field_test_ref1.entity.field_test_text', 'field_test_ref1.entity.field_test_text'],

      'entity reference then no reference property and a complex field with property specifier `value`' => ['field_test_ref1.entity:entity_test_with_bundle.field_test_text.value', 'field_test_ref1.field_test_text.value'],
      'entity reference then a reference property and a complex field with property specifier `value`' => ['field_test_ref1.entity.field_test_text.value', 'field_test_ref1.entity.field_test_text.value'],
      'entity reference then no reference property and a complex field with property specifier `format`' => ['field_test_ref1.entity:entity_test_with_bundle.field_test_text.format', 'field_test_ref1.field_test_text.format'],
      'entity reference then a reference property and a complex field with property specifier `format`' => ['field_test_ref1.entity.field_test_text.format', 'field_test_ref1.entity.field_test_text.format'],

      'entity reference with a delta and no reference property then a complex field and property specifier `value`' => ['field_test_ref1.0.entity:entity_test_with_bundle.field_test_text.value', 'field_test_ref1.0.field_test_text.value'],
      'entity reference with a delta and a reference property then a complex field and property specifier `value`' => ['field_test_ref1.0.entity.field_test_text.value', 'field_test_ref1.0.entity.field_test_text.value'],

      'entity reference with no reference property then another entity reference with no reference property a complex field with property specifier `value`' => ['field_test_ref1.entity:entity_test_with_bundle.field_test_ref3.entity:entity_test_with_bundle.field_test_text.value', 'field_test_ref1.field_test_ref3.field_test_text.value'],
      'entity reference with a reference property then another entity reference with no reference property a complex field with property specifier `value`' => ['field_test_ref1.entity.field_test_ref3.entity:entity_test_with_bundle.field_test_text.value', 'field_test_ref1.entity.field_test_ref3.field_test_text.value'],
      'entity reference with no reference property then another entity reference with a reference property a complex field with property specifier `value`' => ['field_test_ref1.entity:entity_test_with_bundle.field_test_ref3.entity.field_test_text.value', 'field_test_ref1.field_test_ref3.entity.field_test_text.value'],
      'entity reference with a reference property then another entity reference with a reference property a complex field with property specifier `value`' => ['field_test_ref1.entity.field_test_ref3.entity.field_test_text.value', 'field_test_ref1.entity.field_test_ref3.entity.field_test_text.value'],
    ];
  }

  /**
   * Expects an error when an invalid field is provided.
   *
   * @param string $entity_type
   *   The entity type for which to test field resolution.
   * @param string $bundle
   *   The entity bundle for which to test field resolution.
   * @param string $external_path
   *   The external field path to resolve.
   *
   * @covers ::resolveInternal
   * @dataProvider resolveInternalErrorProvider
   */
  public function testResolveInternalError($entity_type, $bundle, $external_path) {
    $this->setExpectedException(BadRequestHttpException::class);
    $this->sut->resolveInternal($entity_type, $bundle, $external_path);
  }

  /**
   * Provides test cases for ::testResolveInternalError.
   */
  public function resolveInternalErrorProvider() {
    return [
      // Should fail because none of these bundles have these fields.
      ['entity_test_with_bundle', 'bundle1', 'host.fail!!.deep'],
      ['entity_test_with_bundle', 'bundle2', 'field_test_ref2'],
      ['entity_test_with_bundle', 'bundle1', 'field_test_ref3'],
      // Should fail because the nested fields don't exist on the targeted
      // resource types.
      ['entity_test_with_bundle', 'bundle1', 'field_test_ref1.field_test1'],
      ['entity_test_with_bundle', 'bundle1', 'field_test_ref1.field_test2'],
      ['entity_test_with_bundle', 'bundle1', 'field_test_ref1.field_test_ref1'],
      ['entity_test_with_bundle', 'bundle1', 'field_test_ref1.field_test_ref2'],
    ];
  }

  /**
   * Create a simple bundle.
   *
   * @param string $name
   *   The name of the bundle to create.
   */
  protected function makeBundle($name) {
    EntityTestBundle::create([
      'id' => $name,
    ])->save();
  }

  /**
   * Creates a field for a specified entity type/bundle.
   *
   * @param string $type
   *   The field type.
   * @param string $name
   *   The name of the field to create.
   * @param string $entity_type
   *   The entity type to which the field will be attached.
   * @param string[] $bundles
   *   The entity bundles to which the field will be attached.
   * @param array $storage_settings
   *   Custom storage settings for the field.
   * @param array $config_settings
   *   Custom configuration settings for the field.
   */
  protected function makeField($type, $name, $entity_type, array $bundles, array $storage_settings = [], array $config_settings = []) {
    $storage_config = [
      'field_name' => $name,
      'type' => $type,
      'entity_type' => $entity_type,
      'settings' => $storage_settings,
    ];

    FieldStorageConfig::create($storage_config)->save();

    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'settings' => $config_settings,
      ])->save();
    }
  }

}
