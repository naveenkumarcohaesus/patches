<?php

namespace Drupal\restrict_route_by_ip\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;

/**
 * Restrict Ip Interface.
 */
interface RestrictIpInterface {

  /**
   * Check a range of IP.
   *
   * @param string $ip
   *   IP to check.
   * @param string $range
   *   IP range authorized.
   *
   * @return bool
   *   TRUE if the IP to check is in the range.
   */
  public function checkRangeIp(string $ip, string $range): bool;

  /**
   * Check if the current user IP has a restricted access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   *
   * @return bool
   *   TRUE if access must be restricted.
   */
  public function userIpIsRestricted(AccountInterface $account): bool;

  /**
   * Check if the current request has a restricted access.
   *
   * @return bool
   *   TRUE if request must be restricted.
   */
  public function requestAffected(): bool;

  /**
   * Get all enabled restricted routes.
   *
   * @return array
   *   List of all routes. (route example :
   *   [
   *     'restricted_route_id' => $entity->id(),
   *     'route_names' => $this->getRouteNames($path_or_route_name)
   *   ]
   */
  public function getAllRestrictedRoutes(): array;

  /**
   * Get a restricted route by route name.
   *
   * @param string $route_name
   *   Route name to search a restricted route.
   *
   * @return string
   *   The restricted route id or NULLa
   */
  public function getRestrictedRouteId(string $route_name);

  /**
   * Get route id, route names and route paths from entity.
   *
   * @param \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $entity
   *   A RestrictRoute entity.
   *
   * @return array
   *   Array of detail from RestrictRoute entity.
   */
  public function getRestrictedRouteDetail(RestrictRouteInterface $entity): array;

  /**
   * Get all restricted route names and path from string.
   *
   * @param string $restricted_route_name
   *   A route name or a path or a regular expression.
   *
   * @return array
   *   Array of restricted route names and path. (ex: [
   *   'name' => [],
   *   'path' => [],
   *   ])
   */
  public function getAllRouteNamesAndPath(string $restricted_route_name): array;

}
