<?php

namespace Drupal\Tests\env_sync\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;
use Drupal\restrict_route_by_ip\Service\RestrictIpService;
use Drupal\restrict_route_by_ip\Form\RestrictRouteGlobalForm;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests of the RestrictIpService class.
 *
 * @coversDefaultClass \Drupal\restrict_route_by_ip\Service\RestrictIpService
 * @group restrict_route_by_ip
 */
class RestrictIpTest extends UnitTestCase {

  protected static $modules = ['restrict_route_by_ip'];

  /**
   * Restrict IP Service.
   *
   * @var \Drupal\restrict_route_by_ip\Service\RestrictIpService
   */
  protected $restrictIpService;

  protected $configObject;

  protected $entityStorage;

  protected $routeMatch;

  protected $request;

  protected $restrictRouteEntity1;
  protected $restrictRouteEntity2;

  protected $globalConfigInitialData = [
    RestrictRouteGlobalForm::FIELD_STATUS => RestrictRouteGlobalForm::STATUS_ENABLE,
    RestrictRouteGlobalForm::FIELD_DEBUG_MODE => FALSE,
  ];

  /**
   * Before a test method is run, setUp() is invoked.
   * Create new unit object.
   */
  public function setUp() : void {
    parent::setUp();

    // Config factory.
    $this->configObject = $this->prophesize(Config::class);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get(RestrictRouteGlobalForm::CONFIG_NAME)
      ->willReturn($this->configObject->reveal());

    // Request Stack.
    $this->request = $this->prophesize('\Symfony\Component\HttpFoundation\Request');
    $request_stack = $this->prophesize('\Symfony\Component\HttpFoundation\RequestStack');
    $request_stack->getCurrentRequest()->willReturn($this->request->reveal());

    // Logger factory.
    $logger = $this->prophesize(LoggerInterface::class);
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get('restrict_route_by_ip')->willReturn($logger->reveal());

    // Route match.
    $this->routeMatch = $this->prophesize(RouteMatchInterface::class);

    // Entity type manager.
    $restrict_route_entity = $this->prophesize(RestrictRouteInterface::class);
    $restrict_route_entity->getRoute()->willReturn('admin.test');
    $restrict_route_entity->id()->willReturn('1');
    $restrict_route_entity->getIps()->willReturn(['123.123.123.1', '123.123.123.2']);
    $this->restrictRouteEntity1 = $restrict_route_entity->reveal();

    $restrict_route_entity2 = $this->prophesize(RestrictRouteInterface::class);
    $restrict_route_entity2->getRoute()->willReturn('admin.test2');
    $restrict_route_entity2->id()->willReturn('2');
    $restrict_route_entity2->getIps()->willReturn(['123.123.123.123']);
    $this->restrictRouteEntity2 = $restrict_route_entity2->reveal();

    $this->entityStorage = $this->prophesize(EntityStorageInterface::class);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('restrict_route')->willReturn($this->entityStorage->reveal());

    // RouteProvider
    $route1 = $this->prophesize('\Symfony\Component\Routing\Route');
    $route1->getPath()->willReturn('/admin/test');

    $route2 = $this->prophesize('\Symfony\Component\Routing\Route');
    $route2->getPath()->willReturn('/admin/test/2');

    $route3 = $this->prophesize('\Symfony\Component\Routing\Route');
    $route3->getPath()->willReturn('/my/test/3');

    $route4 = $this->prophesize('\Symfony\Component\Routing\Route');
    $route4->getPath()->willReturn('/my/param/{number}');

    $route_provider = $this->prophesize('\Drupal\Core\Routing\RouteProvider');
    $route_provider->getAllRoutes()->willReturn([
      'admin.test' => $route1->reveal(),
      'admin.test2' => $route2->reveal(),
      'admin.test3' => $route3->reveal(),
      'route.param' => $route3->reveal(),
    ]);

    // Messenger
    $messenger = $this->prophesize(MessengerInterface::class);

    $this->restrictIpService = new RestrictIpService(
      $config_factory->reveal(),
      $request_stack->reveal(),
      $logger_factory->reveal(),
      $this->routeMatch->reveal(),
      $entity_type_manager->reveal(),
      $route_provider->reveal(),
      $messenger->reveal());
  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::userIpIsRestricted
   */
  public function testUserIpIsRestricted() {
    // Account, not use for now.
    $account = $this->prophesize(AccountInterface::class);

    // Used in getAllRestrictedRoutes.
    $query_interface = $this->prophesize(QueryInterface::class);
    $query_interface->condition('status', '1')->willReturn($query_interface->reveal());
    $query_interface->execute()->willReturn(['1', '2']);
    $this->entityStorage->getQuery()->willReturn($query_interface->reveal());
    $this->entityStorage->loadMultiple(['1', '2'])->willReturn([$this->restrictRouteEntity1, $this->restrictRouteEntity2]);

    // Used in userIpIsRestricted.
    $this->routeMatch->getRouteName()->willReturn('admin.test');
    $this->request->getClientIp()->willReturn('123.123.123.123');
    $this->entityStorage->load('1')->willReturn($this->restrictRouteEntity1);
    $this->entityStorage->load('2')->willReturn($this->restrictRouteEntity2);

    $ip_is_restricted = $this->restrictIpService->userIpIsRestricted($account->reveal());
    $this->assertEquals(TRUE, $ip_is_restricted, 'User ip in restricted ips.');

    $this->request->getClientIp()->willReturn('123.123.123.1');
    $ip_is_not_restricted = $this->restrictIpService->userIpIsRestricted($account->reveal());
    $this->assertEquals(FALSE, $ip_is_not_restricted, 'User ip not in restricted ips.');

  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::getAllRouteNamesAndPath
   */
  public function testGetAllRouteNamesAndPath() {
    $name_and_path = $this->restrictIpService->getAllRouteNamesAndPath('admin.test2');
    $this->assertArrayHasKey('name', $name_and_path, 'Route detail as key name.');
    $this->assertArrayHasKey('path', $name_and_path, 'Route names and paths has key path.');

    $this->assertEquals(['admin.test2'], $name_and_path['name'], 'A route name should return the same route in array.');
    $this->assertEquals(['/admin/test/2'], $name_and_path['path'], 'A route name should return the only one route path in array.');

    $name_and_path = $this->restrictIpService->getAllRouteNamesAndPath('admin.test.dummy');
    $this->assertEquals([], $name_and_path['name'], 'A non existing route should return an empty array for key "name".');
    $this->assertEquals([], $name_and_path['path'], 'A non existing route should return an empty array for key "path".');

    $name_and_path = $this->restrictIpService->getAllRouteNamesAndPath('/admin/%');
    $this->assertEquals(['admin.test', 'admin.test2'], $name_and_path['name'], 'A path with wildcard return searched routes names.');
    $this->assertEquals(['/admin/test', '/admin/test/2'], $name_and_path['path'], 'A path with wildcard return searched routes path.');

    $name_and_path = $this->restrictIpService->getAllRouteNamesAndPath('/admin/');
    $this->assertEquals(['admin.test', 'admin.test2'], $name_and_path['name'], 'A path without wildcard return routes names same than wildcard at the end.');
    $this->assertEquals(['/admin/test', '/admin/test/2'], $name_and_path['path'], 'A path without wildcard return routes path same than wildcard at the end.');

    $name_and_path = $this->restrictIpService->getAllRouteNamesAndPath('/[a-z]{3,}/test');
    $this->assertEquals(['admin.test', 'admin.test2'], $name_and_path['name'], 'Check route names return for regex.');
    $this->assertEquals(['/admin/test', '/admin/test/2'], $name_and_path['path'], 'Check route paths return for regex.');
  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::getRestrictedRouteDetail
   */
  public function testGetRestrictedRouteDetail() {
    $restrict_route_entity = $this->prophesize(RestrictRouteInterface::class);
    $restrict_route_entity->getRoute()->willReturn('admin.test');
    $restrict_route_entity->id()->willReturn('1');

    $detail = $this->restrictIpService->getRestrictedRouteDetail($restrict_route_entity->reveal());
    $this->assertArrayHasKey('restricted_route_id', $detail, 'Route detail has key restricted_route_id.');
    $this->assertArrayHasKey('route_names', $detail, 'Route detail has key route_names.');
    $this->assertArrayHasKey('path', $detail, 'Route detail has key path.');

    $this->assertEquals('1', $detail['restricted_route_id'], 'Get restricted route id.');
    $this->assertEquals(['admin.test'], $detail['route_names'], 'Get restricted route names.');
    $this->assertEquals(['/admin/test'], $detail['path'], 'Get restricted route paths.');
  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::getRestrictedRouteId
   */
  public function testGetRestrictedRouteId() {
    // Used in getAllRestrictedRoutes.
    $query_interface = $this->prophesize(QueryInterface::class);
    $query_interface->condition('status', '1')->willReturn($query_interface->reveal());
    $query_interface->execute()->willReturn(['1', '2']);
    $this->entityStorage->getQuery()->willReturn($query_interface->reveal());
    $this->entityStorage->loadMultiple(['1', '2'])->willReturn([$this->restrictRouteEntity1, $this->restrictRouteEntity2]);

    // Get an id of route.
    $id = $this->restrictIpService->getRestrictedRouteId('admin.test');
    $this->assertEquals('1', $id, 'Get restricted route id.');

    // Route not exist, return NULL.
    $id = $this->restrictIpService->getRestrictedRouteId('dummy.test');
    $this->assertEquals(NULL, $id, 'Get no restricted route for dummy id.');
  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::getAllRestrictedRoutes
   */
  public function testGetAllRestrictedRoutes() {
    $query_interface = $this->prophesize(QueryInterface::class);
    $query_interface->condition('status', '1')->willReturn($query_interface->reveal());
    $query_interface->execute()->willReturn(['1', '2']);
    $this->entityStorage->getQuery()->willReturn($query_interface->reveal());

    $this->entityStorage->loadMultiple(['1', '2'])->willReturn([$this->restrictRouteEntity1, $this->restrictRouteEntity2]);


    $routes = $this->restrictIpService->getAllRestrictedRoutes();
    $this->assertEquals(2, count($routes), 'Get all available restricted routes.');

    $this->configObject->get(RestrictRouteGlobalForm::FIELD_STATUS)
      ->willReturn(RestrictRouteGlobalForm::STATUS_DISABLE);

    $no_route = $this->restrictIpService->getAllRestrictedRoutes();
    $this->assertEquals(0, count($no_route), 'Should no return any route due to disable configuration.');
  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::checkRangeIp
   */
  public function testEnableCheckRangeIp() {
    $this->configObject->get(RestrictRouteGlobalForm::FIELD_STATUS)
      ->willReturn(RestrictRouteGlobalForm::STATUS_ENABLE);

    // Check a simple ip string
    $simple_ip = $this->restrictIpService->checkRangeIp('84.34.35.23', '84.34.35.23');
    $this->assertEquals(TRUE, $simple_ip, 'Check simple ip address.');

    $simple_false_ip = $this->restrictIpService->checkRangeIp('84.34.35.23', '84.34.35.22');
    $this->assertEquals(FALSE, $simple_false_ip, 'Check simple wrong ip address.');

    // Check inside ip range CIDR format
    $inside_24 = $this->restrictIpService->checkRangeIp('84.34.35.2', '84.34.35.1/24');
    $this->assertEquals(TRUE, $inside_24, 'Check ip address is inside a range (CIDR format).');

    // Check outside ip range CIDR format
    $outside_24 = $this->restrictIpService->checkRangeIp('84.34.36.2', '84.34.35.1/24');
    $this->assertEquals(FALSE, $outside_24, 'Check ip address is outside a range (CIDR format).');

    // Check inside ip range start-end format
    $inside_wildcard = $this->restrictIpService->checkRangeIp('84.34.35.22', '84.34.35.1-84.34.35.23');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (start-end format).');

    // Check outside ip range start-end format
    $inside_wildcard = $this->restrictIpService->checkRangeIp('84.34.35.24', '84.34.35.1-84.34.35.23');
    $this->assertEquals(FALSE, $inside_wildcard, 'Check ip address is outside a range (start-end format).');

    // Check inside ip range wildcard format
        $inside_wildcard = $this->restrictIpService->checkRangeIp('84.34.35.2', '84.34.35.*');
        $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (wildcard format).');

    // Check outside ip range wildcard format
        $outside_wildcard = $this->restrictIpService->checkRangeIp('84.34.36.5', '84.34.35.*');
        $this->assertEquals(FALSE, $outside_wildcard, 'Check ip address is outside a range (wildcard format).');

  }

  /**
   * @covers Drupal\restrict_route_by_ip\Service\RestrictIpService::checkRangeIp
   */
  public function testDisableOnLocalhostCheckRangeIp() {
    $this->configObject->get(RestrictRouteGlobalForm::FIELD_STATUS)
      ->willReturn(RestrictRouteGlobalForm::STATUS_DISABLE_ON_LOCALHOST);

    // Check a simple ip string
    $simple_ip = $this->restrictIpService->checkRangeIp('84.34.35.23', '84.34.35.23');
    $this->assertEquals(TRUE, $simple_ip, 'Check simple ip address.');

    // Check inside ip range CIDR format
    $inside_24 = $this->restrictIpService->checkRangeIp('84.34.35.2', '84.34.35.1/24');
    $this->assertEquals(TRUE, $inside_24, 'Check ip address is inside a range (CIDR format).');

    // Check inside ip range start-end format
    $inside_wildcard = $this->restrictIpService->checkRangeIp('84.34.35.22', '84.34.35.1-84.34.35.23');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (start-end format).');

    // Check inside ip range wildcard format
    $inside_wildcard = $this->restrictIpService->checkRangeIp('84.34.35.2', '84.34.35.*');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (wildcard format).');

    /**
     * CHECK WITH 127.0.0.1
     */

    // Check a simple ip string (127.0.0.1)
    $simple_ip = $this->restrictIpService->checkRangeIp('127.0.0.1', '84.34.35.23');
    $this->assertEquals(TRUE, $simple_ip, 'Check simple ip address (127.0.0.1).');

    // Check inside ip range CIDR format (127.0.0.1)
    $inside_24 = $this->restrictIpService->checkRangeIp('127.0.0.1', '84.34.35.1/24');
    $this->assertEquals(TRUE, $inside_24, 'Check ip address is inside a range (127.0.0.1, CIDR format).');

    // Check inside ip range start-end format (127.0.0.1)
    $inside_wildcard = $this->restrictIpService->checkRangeIp('127.0.0.1', '84.34.35.1-84.34.35.23');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (127.0.0.1, start-end format).');

    // Check inside ip range wildcard format (127.0.0.1)
    $inside_wildcard = $this->restrictIpService->checkRangeIp('127.0.0.1', '84.34.35.*');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (127.0.0.1, wildcard format).');

    /**
     * CHECK WITH ::1
     */

    // Check a simple ip string (::1)
    $simple_ip = $this->restrictIpService->checkRangeIp('::1', '84.34.35.23');
    $this->assertEquals(TRUE, $simple_ip, 'Check simple ip address (::1).');

    // Check inside ip range CIDR format (::1)
    $inside_24 = $this->restrictIpService->checkRangeIp('::1', '84.34.35.1/24');
    $this->assertEquals(TRUE, $inside_24, 'Check ip address is inside a range (::1, CIDR format).');

    // Check inside ip range start-end format (::1)
    $inside_wildcard = $this->restrictIpService->checkRangeIp('::1', '84.34.35.1-84.34.35.23');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (::1, start-end format).');

    // Check inside ip range wildcard format (::1)
    $inside_wildcard = $this->restrictIpService->checkRangeIp('::1', '84.34.35.*');
    $this->assertEquals(TRUE, $inside_wildcard, 'Check ip address is inside a range (::1, wildcard format).');


  }

}
