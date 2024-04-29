<?php

namespace Drupal\restrict_route_by_ip\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\restrict_route_by_ip\Service\RestrictIpInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check service.
 */
class ServicesAccessCheck implements AccessInterface {

  /**
   * Restrict IP service.
   *
   * @var \Drupal\restrict_route_by_ip\Service\RestrictIpInterface
   */
  protected $restrictIpService;

  /**
   * Constructor.
   *
   * @param \Drupal\restrict_route_by_ip\Service\RestrictIpInterface $restrict_ip_service
   *   Restrict IP service.
   */
  public function __construct(RestrictIpInterface $restrict_ip_service) {
    $this->restrictIpService = $restrict_ip_service;
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->restrictIpService->requestAffected() && !$this->restrictIpService->userIpIsRestricted($account));
  }

}
