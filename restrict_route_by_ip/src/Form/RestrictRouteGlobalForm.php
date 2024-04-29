<?php

namespace Drupal\restrict_route_by_ip\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the RestrictRoute Global forms.
 */
class RestrictRouteGlobalForm extends ConfigFormBase {

  /**
   * The Drupal RouteBuilder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The Drupal RouteBuilder service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilderInterface $route_builder) {
    $this->setConfigFactory($config_factory);
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder')
    );
  }

  /**
   * Configuration name.
   */
  const CONFIG_NAME = 'restrict_route_by_ip.settings';

  /**
   * Available fields.
   */
  const FIELD_STATUS = 'status';
  const FIELD_DEBUG_MODE = 'debug_mode';

  /**
   * Available status.
   */
  const STATUS_ENABLE = 'enable';
  const STATUS_DISABLE = 'disable';
  const STATUS_DISABLE_ON_LOCALHOST = 'disable_localhost';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'restrict_route_by_ip_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $form[self::FIELD_STATUS] = [
      '#type' => 'select',
      '#title' => $this->t('Status of restrictions.'),
      '#options' => [
        self::STATUS_ENABLE => $this->t('Enable'),
        self::STATUS_DISABLE => $this->t('Disable'),
        self::STATUS_DISABLE_ON_LOCALHOST => $this->t('Disable for localhost only'),
      ],
      '#default_value' => $config->get(self::FIELD_STATUS),
    ];
    $form[self::FIELD_DEBUG_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the debug mode.'),
      '#description' => $this->t('Useful to see ips when ips are restricted in drupal logs.'),
      '#default_value' => $config->get(self::FIELD_DEBUG_MODE),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config(self::CONFIG_NAME)
      ->set(self::FIELD_STATUS, $form_state->getValue(self::FIELD_STATUS))
      ->set(self::FIELD_DEBUG_MODE, $form_state->getValue(self::FIELD_DEBUG_MODE))
      ->save();

    // Rebuild routes.
    $this->routeBuilder->rebuild();
  }

}
