<?php

namespace Drupal\Tests\jsonapi\Kernel\Configuration;

use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Configuration\ResourceManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Class ResourceManagerTest.
 *
 * @package Drupal\Tests\jsonapi\Kernel\Resource
 *
 * @coversDefaultClass \Drupal\jsonapi\Configuration\ResourceManager
 *
 * @group jsonapi
 */
class ResourceManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'jsonapi',
    'rest',
    'serialization',
    'system',
    'user',
  ];

  /**
   * The entity resource under test.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    NodeType::create([
      'type' => 'article',
    ])->save();
    NodeType::create([
      'type' => 'page',
    ])->save();

    $this->resourceManager = $this->container->get('jsonapi.resource.manager');
  }

  /**
   * @covers ::all
   */
  public function testAll() {
    // Make sure that there are resources being created.
    $all = $this->resourceManager->all();
    $this->assertNotEmpty($all);
    // Get a random resource config.
    $resource_config = $all[mt_rand(0, count($all) - 1)];
    $this->assertNotEmpty($resource_config->getDeserializationTargetClass());
    $this->assertNotEmpty($resource_config->getEntityTypeId());
    $this->assertNotEmpty($resource_config->getBundleId());
    $this->assertNotEmpty($resource_config->getGlobalConfig());
    $this->assertNotEmpty($resource_config->getPath());
    $this->assertNotEmpty($resource_config->getTypeName());
  }

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($entity_type_id, $bundle_id, $entity_class) {
    // Make sure that there are resources being created.
    $resource_config = $this->resourceManager->get($entity_type_id, $bundle_id);
    $this->assertInstanceOf(ResourceConfigInterface::class, $resource_config);
    $this->assertSame($entity_class, $resource_config->getDeserializationTargetClass());
    $this->assertSame($entity_type_id, $resource_config->getEntityTypeId());
    $this->assertSame($bundle_id, $resource_config->getBundleId());
    $this->assertNotEmpty($resource_config->getGlobalConfig());
    $this->assertSame('/' . $entity_type_id . '/' . $bundle_id, $resource_config->getPath());
    $this->assertSame($entity_type_id . '--' . $bundle_id, $resource_config->getTypeName());
  }

  /**
   * Data provider for testGet.
   *
   * @returns array
   *   The data for the test method.
   */
  public function getProvider() {
    return [
      ['node', 'article', 'Drupal\node\Entity\Node'],
      ['node_type', 'node_type', 'Drupal\node\Entity\NodeType'],
    ];
  }

}
