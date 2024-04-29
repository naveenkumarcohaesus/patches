<?php

namespace Drupal\restrict_route_by_ip\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface;
use Drupal\restrict_route_by_ip\Service\RestrictIpInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the RestrictRoute add and edit forms.
 */
class RestrictRouteForm extends EntityForm {

  /**
   * The Drupal RouteBuilder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * The Restrict IP service.
   *
   * @var \Drupal\restrict_route_by_ip\Service\RestrictIpInterface
   */
  protected $restrictIPService;

  /**
   * Constructs a RestrictRoute object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entityTypeManager service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The Drupal RouteBuilder service.
   * @param \Drupal\restrict_route_by_ip\Service\RestrictIpInterface $restrict_ip_service
   *   The Restrict IP service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RouteBuilderInterface $route_builder,
    RestrictIpInterface $restrict_ip_service) {
    $this->entityTypeManager = $entityTypeManager;
    $this->routeBuilder = $route_builder;
    $this->restrictIPService = $restrict_ip_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('router.builder'),
      $container->get('restrict_route_by_ip.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $restrict_route */
    $restrict_route = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $restrict_route->label(),
      '#description' => $this->t("Label for the Restricted route."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Id'),
      '#default_value' => $restrict_route->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$restrict_route->isNew(),
    ];
    $form['route'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Route name or path'),
      '#description' => $this->t('Route name like user.login or path like /user/login is possible.
      Using the syntax /user/ will match routes like /user/1 or /user/1234 <br />'),
      '#default_value' => $restrict_route->getRoute(),
      '#required' => TRUE,
    ];

    $route_detail = $this->restrictIPService->getRestrictedRouteDetail($restrict_route);
    $impacted_routes = implode("\n", $route_detail['path']);
    $form['impacted-routes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Impacted routes'),
      '#description' => $this->t('See all impacted routes.
      <b>Change the route field and click outside the field to see all impacted routes.</b>'),
      '#default_value' => empty($impacted_routes) ? $this->t('No impacted route.') : $impacted_routes,
      '#disabled' => TRUE,
    ];
    $form['methods'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Request methods'),
      '#options' => [
        'GET' => $this->t('GET'),
        'POST' => $this->t('POST'),
        'DELETE' => $this->t('DELETE'),
        'PATCH' => $this->t('PATCH'),
      ],
      '#default_value' => $restrict_route->getMethods(),
      '#required' => TRUE,
    ];
    $form['params'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URL parameters'),
      '#description' => $this->t('Use one param and value.'),
      '#default_value' => $restrict_route->getParams(RestrictRouteInterface::FORMAT_STRING),
    ];
    $form['ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ips'),
      '#description' => $this->t('Only one IP or range of IP by line.'),
      '#default_value' => $restrict_route->getIps(RestrictRouteInterface::FORMAT_STRING),
      '#required' => TRUE,
    ];
    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operation'),
      '#options' => [
        $this->t('Restrict access'),
        $this->t('Allow access'),
      ],
      '#default_value' => (int) $restrict_route->getOperation(),
      '#required' => TRUE,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $restrict_route->getStatus(),
    ];
    $form['#attached']['library'][] = 'restrict_route_by_ip/entity_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\restrict_route_by_ip\Entity\RestrictRouteInterface $restrict_route */
    $restrict_route = $this->entity;

    $restrict_route->setRoute($form_state->getValue('route'));
    $restrict_route->setIps($form_state->getValue('ips'));
    $restrict_route->setMethods($form_state->getValue('methods'));
    $restrict_route->setParams($form_state->getValue('params'));
    $restrict_route->setStatus($form_state->getValue('status'));
    $restrict_route->setOperation($form_state->getValue('operation'));
    $status = $restrict_route->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The %label RestrictRoute created.', [
        '%label' => $restrict_route->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label RestrictRoute updated.', [
        '%label' => $restrict_route->label(),
      ]));
    }

    // Rebuild routes.
    $this->routeBuilder->rebuild();

    // Redirect.
    $form_state->setRedirect('entity.restrict_route.collection');
    return $status;
  }

  /**
   * Function to check whether a RestrictRoute configuration entity exists.
   *
   * @param string $id
   *   Id of the route.
   */
  public function exist(string $id): bool {
    $entity = $this->entityTypeManager->getStorage('restrict_route')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
