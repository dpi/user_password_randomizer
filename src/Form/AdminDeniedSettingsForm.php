<?php

namespace Drupal\admin_denied\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for Admin Denied.
 */
class AdminDeniedSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AdminDeniedSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    $this->setConfigFactory($configFactory);
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['admin_denied.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_denied_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('admin_denied.settings');

    $form['randomize_username'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize username'),
      '#description' => $this->t('Whether to randomize username.'),
      '#default_value' => $config->get('randomize_username'),
      '#id' => Html::getUniqueId('randomize_username'),
    ];

    $form['username_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username pattern'),
      '#description' => $this->t('Leave empty to generate a random username. Field accepts tokens.'),
      '#default_value' => $config->get('username_pattern'),
      '#states' => [
        'visible' => [
          [':input[id="' . $form['randomize_username']['#id'] . '"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      // Explicitely suggest the random token because site builder should be
      // empowered to create nicer user names.
      $form['username_pattern']['#description'] = $this->t('Leave empty to generate a random username. Field accepts tokens, for example [random:number] or [random:hash:sha1].');
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['user'],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('admin_denied.settings')
      ->set('username_pattern', $form_state->getValue('username_pattern'))
      ->set('randomize_username', (bool) $form_state->getValue('randomize_username'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
