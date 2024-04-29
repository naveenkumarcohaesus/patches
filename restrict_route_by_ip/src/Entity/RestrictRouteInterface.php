<?php

namespace Drupal\restrict_route_by_ip\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a RestrictRoute entity.
 */
interface RestrictRouteInterface extends ConfigEntityInterface {

  /**
   * Defining formats to export Ips.
   */
  const FORMAT_HTML = 'html';

  const FORMAT_STRING = 'string';

  /**
   * Get all IPs.
   *
   * @param string $format
   *   output format for IPs list.
   *
   * @return array|string
   *   Return a list of IPs
   */
  public function getIps($format = NULL);

  /**
   * Set an array of IPs.
   *
   * @param string $ips
   *   List of ips.
   */
  public function setIps($ips): void;

  /**
   * Get the URL params of the restricted route.
   *
   * @param string $format
   *   Output format for params list.
   *
   * @return array|string
   *   The URL params string or array.
   */
  public function getParams($format = NULL);

  /**
   * Set the URL params of the restricted route.
   *
   * @param string $params
   *   The URL params string.
   */
  public function setParams(string $params): void;

  /**
   * Get the restricted route.
   *
   * @return string
   *   The route string (route name or path).
   */
  public function getRoute(): string;

  /**
   * Set the restricted route.
   *
   * @param string $route
   *   The route string (route name or path).
   */
  public function setRoute(string $route): void;

  /**
   * Get request methods of the current restriction.
   *
   * @return array
   *   List of request methods.
   */
  public function getMethods(): array;

  /**
   * Set the methods of the current restriction.
   *
   * @param array $methods
   *   List of request methods.
   */
  public function setMethods(array $methods): void;

  /**
   * Get the operation of the current restriction.
   *
   * @return bool
   *   TRUE if restricted, FALSE allowed.
   */
  public function getOperation(): bool;

  /**
   * Set the operation of the current restriction.
   *
   * @param bool $operation
   *   TRUE if restricted, FALSE allowed.
   */
  public function setOperation($operation): void;

  /**
   * Get the status of the current restriction.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function getStatus(): bool;

  /**
   * Set the status of the current restriction.
   *
   * @param bool $status
   *   TRUE if enabled, FALSE otherwise.
   */
  public function setStatus($status);

}
