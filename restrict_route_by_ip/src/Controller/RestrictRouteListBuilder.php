<?php

namespace Drupal\restrict_route_by_ip\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;

/**
 * Provides a listing of RestrictRoute.
 */
class RestrictRouteListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Title');
    $header['route'] = $this->t('Route');
    $header['ips'] = $this->t('Ip');
    $header['method'] = $this->t('Method');
    $header['params'] = $this->t('Params');
    $header['operation'] = $this->t('Operation');
    $header['status'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    if ($entity instanceof RestrictRouteInterface) {
      $row['label'] = $entity->label();
      $row['route'] = $entity->getRoute();
      $row['ips'] = [
        'data' => [
          '#markup' => $entity->getIps('html'),
        ],
      ];
      $methods = implode(',', $entity->getMethods());
      $row['method'] = $methods;
      $row['params'] = $entity->getParams(RestrictRouteInterface::FORMAT_STRING);
      $row['operation'] = $entity->getOperation() ? $this->t('Allow') : $this->t('Restrict');
      $row['status'] = $entity->getstatus() ? $this->t('yes') : $this->t('no');
    }
    return $row + parent::buildRow($entity);
  }

}
