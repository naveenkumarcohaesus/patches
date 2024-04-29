<?php

namespace Drupal\restrict_route_by_ip\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the RestrictRoute entity.
 *
 * @ConfigEntityType(
 *   id = "restrict_route",
 *   label = @Translation("Restrict route"),
 *   handlers = {
 *     "list_builder" = "Drupal\restrict_route_by_ip\Controller\RestrictRouteListBuilder",
 *     "form" = {
 *       "add" = "Drupal\restrict_route_by_ip\Form\RestrictRouteForm",
 *       "edit" = "Drupal\restrict_route_by_ip\Form\RestrictRouteForm",
 *       "delete" = "Drupal\restrict_route_by_ip\Form\RestrictRouteDeleteForm",
 *     }
 *   },
 *   config_prefix = "restrict_route",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "route" = "route",
 *     "ips" = "ips",
 *     "methods" = "methods",
 *     "params" = "params",
 *     "status" = "enabled",
 *     "operation" = "operation",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "route",
 *     "ips",
 *     "methods",
 *     "params",
 *     "status",
 *     "operation",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/restrict_route_by_ip/{restrict_route}",
 *     "delete-form" = "/admin/config/system/restrict_route_by_ip/{restrict_route}/delete",
 *     "enable" = "/admin/config/system/restrict_route_by_ip/{restrict_route}/enable",
 *     "disable" = "/admin/config/system/restrict_route_by_ip/{restrict_route}/disable",
 *   }
 * )
 */
class RestrictRouteByIp extends ConfigEntityBase implements RestrictRouteInterface {

  /**
   * The RestrictRoute ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The RestrictRoute label.
   *
   * @var string
   */
  protected $label;

  /**
   * The restricted route.
   *
   * @var string
   */
  protected $route;

  /**
   * The methods of the restricted route.
   *
   * @var array
   */
  protected $methods = [];

  /**
   * The params of the restricted route.
   *
   * @var array
   */
  protected $params;

  /**
   * A list of IP/Range of Ip to restrict access.
   *
   * @var array
   */
  protected $ips;

  /**
   * The operation of this restricted route.
   *
   * @var bool
   */
  protected $operation = FALSE;

  /**
   * The status of this restricted route.
   *
   * @var bool
   */
  protected $status = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getIps($format = NULL) {
    $ips = $this->ips;
    if (is_array($ips)) {
      switch ($format) {
        case self::FORMAT_HTML:
          $ips = implode('<br />', $ips);
          break;

        case self::FORMAT_STRING:
          $ips = implode("\n", $ips);
          break;
      }

    }
    return $ips;
  }

  /**
   * {@inheritdoc}
   */
  public function setIps($ips): void {
    if (is_string($ips)) {
      $ips = explode("\n", $ips);
    }
    foreach ($ips as $key => $ip) {
      $ips[$key] = trim($ip);
    }
    $this->ips = $ips;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoute(): string {
    $route = $this->route;
    if (empty($route)) {
      $route = '';
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoute(string $route): void {
    $this->route = $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation(): bool {
    return $this->operation;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperation($operation): void {
    $this->operation = $operation;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParams($format = NULL) {
    if (!empty($this->params)) {
      if ($format == 'string') {
        return implode("\n", $this->params);
      }
      else {
        $params = [];
        foreach ($this->params as $param) {
          $parts = explode('=', $param);
          $params[$parts[0]] = $parts[1];
        }
        return $params;
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setParams(string $params): void {
    if (!empty($params)) {
      if (is_string($params)) {
        $params = explode("\n", $params);
      }
      foreach ($params as $key => $param) {
        $params[$key] = trim($param);
      }
      $this->params = $params;
    }
    else {
      $this->params = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMethods(): array {
    return array_filter($this->methods);
  }

  /**
   * {@inheritdoc}
   */
  public function setMethods($methods):void {
    $this->methods = $methods;
  }

}
