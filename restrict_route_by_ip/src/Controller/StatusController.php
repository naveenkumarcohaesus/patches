<?php

namespace Drupal\restrict_route_by_ip\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;
use Drupal\restrict_route_by_ip\Service\RestrictIpInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Set status on RestrictRoute.
 */
class StatusController extends ControllerBase {

  /**
   * The Drupal RouteBuilder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * The Drupal RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The RestrictRouteByIp service.
   *
   * @var \Drupal\restrict_route_by_ip\Service\RestrictIpInterface
   */
  protected $restrictIpService;

  /**
   * StatusController constructor.
   *
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The Drupal RequestStack service.
   * @param \Drupal\restrict_route_by_ip\Service\RestrictIpInterface $restrict_ip_service
   *   The RestrictRouteByIp service.
   */
  public function __construct(
    RouteBuilderInterface $route_builder,
    RequestStack $request_stack,
    RestrictIpInterface $restrict_ip_service) {
    $this->routeBuilder = $route_builder;
    $this->requestStack = $request_stack;
    $this->restrictIpService = $restrict_ip_service;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.builder'),
      $container->get('request_stack'),
      $container->get('restrict_route_by_ip.service')
    );
  }

  /**
   * Enables the configuration entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  public function enable(EntityInterface $restrict_route) {
    if ($restrict_route instanceof RestrictRouteInterface) {
      $restrict_route->setStatus(TRUE);
      $restrict_route->save();
      $this->messenger()->addMessage($this->t('Route @label enabled.', [
        '@label' => $restrict_route->label(),
      ]));
      $this->routeBuilder->rebuild();
    }
    return $this->redirect('entity.restrict_route.collection');
  }

  /**
   * Disables the configuration entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  public function disable(EntityInterface $restrict_route) {
    if ($restrict_route instanceof RestrictRouteInterface) {
      $restrict_route->setStatus(FALSE);
      $restrict_route->save();
      $this->messenger()->addMessage($this->t('Route @label disabled.', [
        '@label' => $restrict_route->label(),
      ]));
      $this->routeBuilder->rebuild();
    }
    return $this->redirect('entity.restrict_route.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function impactedPath(): JsonResponse {
    $current_request = $this->requestStack->getCurrentRequest();
    $response = new JsonResponse([
      'status' => 'ko',
    ]);
    if ($current_request->isXmlHttpRequest()) {
      $route_name = $current_request->request->get('route_name');
      $restricted_routes = $this->restrictIpService->getAllRouteNamesAndPath($route_name);
      $response = new JsonResponse([
        'status' => 'ok',
        'impacted' => $restricted_routes['path'],
      ]);
    }
    return $response;
  }

}
