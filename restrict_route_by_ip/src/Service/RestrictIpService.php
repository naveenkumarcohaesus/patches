<?php

namespace Drupal\restrict_route_by_ip\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;
use Drupal\restrict_route_by_ip\Form\RestrictRouteGlobalForm;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Restrict Ip service.
 */
class RestrictIpService implements RestrictIpInterface {

  /**
   * Drupal ConfigFactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configManager;

  /**
   * Drupal current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Drupal logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Drupal RouteMatch service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Drupal EntityStorage on restrict_route entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $restrictRouteStorage;

  /**
   * Drupal RouteProvider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Drupal Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * All definied routes.
   *
   * @var array
   */
  protected $allRoutes;

  /**
   * Global settings of restrict_route_by_ip module.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $globalConfig;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Drupal ConfigFactory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack_service
   *   Drupal RequestStack service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Drupal LoggerChannelFactory service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   Drupal RouteMatch service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal EntityTypeManager service.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   Drupal RouteProvider service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal RouteProvider service.
   */
  public function __construct(
    ConfigFactoryInterface $config,
    RequestStack $request_stack_service,
    LoggerChannelFactoryInterface $logger_factory,
    RouteMatchInterface $current_route_match,
    EntityTypeManagerInterface $entity_type_manager,
    RouteProviderInterface $route_provider,
    MessengerInterface $messenger) {
    $this->configManager = $config;
    $this->currentRequest = $request_stack_service->getCurrentRequest();
    $this->logger = $logger_factory->get('restrict_route_by_ip');
    $this->routeMatch = $current_route_match;
    $this->restrictRouteStorage = $entity_type_manager->getStorage('restrict_route');
    $this->routeProvider = $route_provider;
    $this->messenger = $messenger;

    // Get the global configuration.
    $this->globalConfig = $this->configManager->get(RestrictRouteGlobalForm::CONFIG_NAME);
  }

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function checkRangeIp(string $ip, string $range): bool {
    $global_config_status = $this->globalConfig->get(RestrictRouteGlobalForm::FIELD_STATUS);
    if ($global_config_status === RestrictRouteGlobalForm::STATUS_DISABLE_ON_LOCALHOST
        && $this->isLocalIp($ip)) {
      return TRUE;
    }
    if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(\/\d{1,3})?\z/', $range)) {
      if (strpos($range, '/') === FALSE) {
        $range .= '/32';
      }
      [$range, $netmask] = explode('/', $range, 2);
      $range_decimal = ip2long($range);
      $ip_decimal = ip2long($ip);
      $wildcard_decimal = pow(2, (32 - (int) $netmask)) - 1;
      $netmask_decimal = ~ $wildcard_decimal;
      return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
    else {
      // Range might be 255.255.*.* or 1.2.3.0-1.2.3.255.
      if (strpos($range, '*') !== FALSE) {
        // Just convert to A-B format by setting * to 0 for A and 255 for B.
        $lower = str_replace('*', '0', $range);
        $upper = str_replace('*', '255', $range);
        $range = "$lower-$upper";
      }
      // A-B format.
      if (strpos($range, '-') !== FALSE) {
        [$lower, $upper] = explode('-', $range, 2);
        $lower_dec = (float) sprintf("%u", ip2long($lower));
        $upper_dec = (float) sprintf("%u", ip2long($upper));
        $ip_dec = (float) sprintf("%u", ip2long($ip));
        return (($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec));
      }
    }
    if ($ip === $range) {
      // If regex not matching, try string comparison.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if IP is a local IP.
   *
   * @param string $ip
   *   An IP.
   *
   * @return bool
   *   TRUE if IP is a local IP, FALSE.
   */
  public function isLocalIp($ip) {
    return in_array($ip, [
      '127.0.0.1',
      '::1',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function userIpIsRestricted(AccountInterface $account): bool {
    $current_route_name = $this->routeMatch->getRouteName();
    $restricted_route_id = $this->getRestrictedRouteId($current_route_name);
    $restricted = FALSE;

    if ($restricted_route_id) {
      // Load the current restricted route entity.
      /** @var \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $restrict_route */
      $restrict_route = $this->restrictRouteStorage->load($restricted_route_id);
      $current_ip = $this->currentRequest->getClientIp();

      $in_ip_list = FALSE;
      // Check the current IP.
      $ips = $restrict_route->getIps();
      if (is_array($ips) && count($ips) > 0) {
        $logs[] = 'Restricted IPs : ' . json_encode($ips);
        foreach ($ips as $ip) {
          $match = $this->checkRangeIp($current_ip, $ip);
          $logs[] = 'Checked ' . $ip . ', found : ' . strval($match);
          if ($match) {
            $in_ip_list = TRUE;
            break;
          }
        }
      }
      if ($in_ip_list) {
        $restricted = !$restrict_route->getOperation();
      }
      else {
        $restricted = $restrict_route->getOperation();
      }

      if ($restricted) {
        $global_config_debug_mode = $this->globalConfig->get(RestrictRouteGlobalForm::FIELD_DEBUG_MODE);

        // Log restricted routes if debug mode is enabled.
        if ($global_config_debug_mode) {
          $logs[] = 'Restricted : ' . $restricted;
          $this->logger->notice($current_ip . ' not accepted.' . implode("\n", $logs));
        }
      }
    }
    return $restricted;
  }

  /**
   * {@inheritdoc}
   */
  public function requestAffected(): bool {
    $current_route_name = $this->routeMatch->getRouteName();
    if (empty($current_route_name)) {
      return FALSE;
    }
    $restricted_route_id = $this->getRestrictedRouteId($current_route_name);
    if (empty($restricted_route_id)) {
      return FALSE;
    }
    /** @var \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $restrict_route */
    $restrict_route = $this->restrictRouteStorage->load($restricted_route_id);

    $method_match = FALSE;
    if (in_array($this->currentRequest->getMethod(), $restrict_route->getMethods())) {
      $method_match = TRUE;
    }

    $params_match = FALSE;
    $query_string = $this->currentRequest->getQueryString();
    $params = $restrict_route->getParams();
    if (!$params || ($params && !$query_string)) {
      $params_match = TRUE;
    }
    else {
      parse_str($this->currentRequest->getQueryString(), $current_params);
      // Try to find all params in current request.
      if (empty(array_diff($current_params, $params))) {
        $params_match = TRUE;
      }
    }
    return $method_match && $params_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRestrictedRouteId(string $route_name) {
    $restricted_route_id = NULL;
    $all_restricted_routes = $this->getAllRestrictedRoutes();
    foreach ($all_restricted_routes as $route) {
      if (array_search($route_name, $route['route_names']) !== FALSE) {
        $restricted_route_id = $route['restricted_route_id'];
      }
    }
    return $restricted_route_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRestrictedRoutes(): array {
    $routes = [];
    $status = $this->globalConfig->get(RestrictRouteGlobalForm::FIELD_STATUS);
    if ($status !== RestrictRouteGlobalForm::STATUS_DISABLE) {
      $restrict_route_ids = $this->restrictRouteStorage->getQuery()
        ->condition('status', '1')
        ->execute();
      $entities = $this->restrictRouteStorage->loadMultiple($restrict_route_ids);

      /** @var \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $entity */
      foreach ($entities as $entity) {
        $routes[] = $this->getRestrictedRouteDetail($entity);
      }
    }
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  public function getRestrictedRouteDetail(RestrictRouteInterface $entity): array {
    $restricted_route_name = $entity->getRoute();
    $restricted_routes = $this->getAllRouteNamesAndPath($restricted_route_name);
    return [
      'restricted_route_id' => $entity->id(),
      'route_names' => $restricted_routes['name'],
      'path' => $restricted_routes['path'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRouteNamesAndPath(string $restricted_route_name): array {
    $route_names = [
      'name' => [],
      'path' => [],
    ];
    $regex = '';

    // If the restricted route is a regex.
    if (substr($restricted_route_name, 0, 1) === '#'
      && substr($restricted_route_name, -1, 1) === '#') {
      $regex = $restricted_route_name;
    }

    // A path is defined, so check path by regex.
    if (empty($regex) && strpos($restricted_route_name, '/') !== FALSE) {
      $regex = $this->getRegularExpressionFromPath($restricted_route_name);
    }

    // Load all routes.
    if (empty($this->allRoutes)) {
      $this->allRoutes = $this->routeProvider->getAllRoutes();
    }

    // Use regex to define impacted route names.
    if (!empty($regex)) {

      foreach ($this->allRoutes as $route_name => $route) {
        $original_path = $route->getPath();
        $route_path = preg_replace('#(\{[_\-0-9a-zA-Z]+\})#', 'rrbip_replaced', $original_path);

        $match = [];
        $check_expression = @preg_match($regex, $route_path, $match);
        if ($check_expression === FALSE) {
          $this->messenger->addWarning($this->t('Invalid regular expression: @regex', [
            '@regex' => $regex,
          ]));
          break;
        }
        if ($check_expression) {
          $route_names['name'][] = $route_name;
          $route_names['path'][] = $original_path;
        }
      }
    }
    elseif (isset($this->allRoutes) && is_array($this->allRoutes) && isset($this->allRoutes[$restricted_route_name])) {
      // If it's not a regex or a path, set the route name directly in array.
      $route = $this->allRoutes[$restricted_route_name];

      $route_names['name'][] = $restricted_route_name;
      $route_names['path'][] = $route->getPath();
    }
    return $route_names;
  }

  /**
   * Use a path to define a regex applicable on routes.
   *
   * @param string $path
   *   A path.
   *
   * @return string
   *   A regex
   */
  protected function getRegularExpressionFromPath(string $path): string {
    $regex = $path;

    // If there is a Wildcard "%" in path, replace by ".+".
    if (strpos($regex, '%') !== FALSE) {
      $regex = str_replace('%', '.+', $regex);
    }
    return '#' . $regex . '#';
  }

}
