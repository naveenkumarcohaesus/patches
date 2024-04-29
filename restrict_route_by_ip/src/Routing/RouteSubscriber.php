<?php

namespace Drupal\restrict_route_by_ip\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\restrict_route_by_ip\Service\RestrictIpService;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The restrict route service.
   *
   * @var \Drupal\restrict_route_by_ip\Service\RestrictIpService
   */
  protected $restrictIpService;

  /**
   * RouteSubscriber constructor.
   *
   * @param \Drupal\restrict_route_by_ip\Service\RestrictIpService $restrictIpService
   *   The restrict route service.
   */
  public function __construct(RestrictIpService $restrictIpService) {
    $this->restrictIpService = $restrictIpService;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $routes = $this->restrictIpService->getAllRestrictedRoutes();
    $route_names = array_column($routes, 'route_names');
    $route_names = array_reduce($route_names, 'array_merge', []);

    // Restrict asset on all routes from configurations.
    if (!empty($route_names)) {
      foreach ($route_names as $route_name) {
        if ($route = $collection->get($route_name)) {
          $route->setRequirement('_custom_access', 'restrict_route_by_ip.services_access_checker::access');
          $route->setOption('no_cache', TRUE);
        }
      }
    }
  }

}
