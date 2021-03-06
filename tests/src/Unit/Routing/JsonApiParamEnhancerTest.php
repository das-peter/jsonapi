<?php

namespace Drupal\Tests\jsonapi\Unit\Routing;

use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\JsonApiParamEnhancer;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\Routing\Routes;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @coversDefaultClass \Drupal\jsonapi\Routing\JsonApiParamEnhancer
 * @group jsonapi
 * @group jsonapi_param_enhancer
 * @group legacy
 *
 * @internal
 */
class JsonApiParamEnhancerTest extends UnitTestCase {

  /**
   * @covers ::applies
   */
  public function testApplies() {
    list($filter_normalizer, $sort_normalizer, $page_normalizer) = $this->getMockNormalizers();
    $object = new JsonApiParamEnhancer($filter_normalizer, $sort_normalizer, $page_normalizer);
    $passing = $this->prophesize(Route::class);
    $passing->getDefaults()->willReturn([
      RouteObjectInterface::CONTROLLER_NAME => Routes::FRONT_CONTROLLER,
    ]);
    $failing = $this->prophesize(Route::class);
    $failing->getDefaults()->willReturn([
      RouteObjectInterface::CONTROLLER_NAME => 'failing',
    ]);

    $this->assertTrue($object->applies($passing->reveal()));
    $this->assertFalse($object->applies($failing->reveal()));
  }

  /**
   * @covers ::enhance
   */
  public function testEnhanceFilter() {
    list($filter_normalizer, $sort_normalizer, $page_normalizer) = $this->getMockNormalizers();
    $object = new JsonApiParamEnhancer($filter_normalizer, $sort_normalizer, $page_normalizer);
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('filter')->willReturn(['filed1' => 'lorem']);
    $query->has(Argument::type('string'))->willReturn(FALSE);
    $query->has('filter')->willReturn(TRUE);
    $request->query = $query->reveal();

    $defaults = $object->enhance([
      RouteObjectInterface::CONTROLLER_NAME => Routes::FRONT_CONTROLLER,
      Routes::RESOURCE_TYPE_KEY => new ResourceType('foo', 'bar', NULL),
    ], $request->reveal());
    $this->assertInstanceOf(Filter::class, $defaults['_json_api_params']['filter']);
    $this->assertInstanceOf(OffsetPage::class, $defaults['_json_api_params']['page']);
    $this->assertTrue(empty($defaults['_json_api_params']['sort']));
  }

  /**
   * @covers ::enhance
   */
  public function testEnhancePage() {
    list($filter_normalizer, $sort_normalizer, $page_normalizer) = $this->getMockNormalizers();
    $object = new JsonApiParamEnhancer($filter_normalizer, $sort_normalizer, $page_normalizer);
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('page')->willReturn(['cursor' => 'lorem']);
    $query->has(Argument::type('string'))->willReturn(FALSE);
    $query->has('page')->willReturn(TRUE);
    $request->query = $query->reveal();

    $defaults = $object->enhance([
      RouteObjectInterface::CONTROLLER_NAME => Routes::FRONT_CONTROLLER,
      Routes::RESOURCE_TYPE_KEY => new ResourceType('foo', 'bar', NULL),
    ], $request->reveal());
    $this->assertInstanceOf(OffsetPage::class, $defaults['_json_api_params']['page']);
    $this->assertTrue(empty($defaults['_json_api_params']['filter']));
    $this->assertTrue(empty($defaults['_json_api_params']['sort']));
  }

  /**
   * @covers ::enhance
   */
  public function testEnhanceSort() {
    list($filter_normalizer, $sort_normalizer, $page_normalizer) = $this->getMockNormalizers();
    $object = new JsonApiParamEnhancer($filter_normalizer, $sort_normalizer, $page_normalizer);
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('sort')->willReturn('-lorem');
    $query->has(Argument::type('string'))->willReturn(FALSE);
    $query->has('sort')->willReturn(TRUE);
    $request->query = $query->reveal();

    $defaults = $object->enhance([
      RouteObjectInterface::CONTROLLER_NAME => Routes::FRONT_CONTROLLER,
      Routes::RESOURCE_TYPE_KEY => new ResourceType('foo', 'bar', NULL),
    ], $request->reveal());
    $this->assertInstanceOf(Sort::class, $defaults['_json_api_params']['sort']);
    $this->assertInstanceOf(OffsetPage::class, $defaults['_json_api_params']['page']);
    $this->assertTrue(empty($defaults['_json_api_params']['filter']));
  }

  /**
   * Builds mock normalizers.
   */
  public function getMockNormalizers() {
    $filter_normalizer = $this->prophesize(DenormalizerInterface::class);
    $filter_normalizer->denormalize(
      Argument::any(),
      Filter::class,
      Argument::any(),
      Argument::any()
    )->willReturn($this->prophesize(Filter::class)->reveal());

    $sort_normalizer = $this->prophesize(DenormalizerInterface::class);
    $sort_normalizer->denormalize(
      Argument::any(),
      Sort::class,
      Argument::any(),
      Argument::any()
    )->willReturn($this->prophesize(Sort::class)->reveal());

    $page_normalizer = $this->prophesize(DenormalizerInterface::class);
    $page_normalizer->denormalize(Argument::any(), OffsetPage::class)->willReturn($this->prophesize(OffsetPage::class)->reveal());

    return [
      $filter_normalizer->reveal(),
      $sort_normalizer->reveal(),
      $page_normalizer->reveal(),
    ];
  }

}
