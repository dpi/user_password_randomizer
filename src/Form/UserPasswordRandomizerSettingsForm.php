<?php

namespace Drupal\user_password_randomizer\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for User Password Randomizer.
 */
class UserPasswordRandomizerSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new UserPasswordRandomizerSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, DateFormatterInterface $dateFormatter) {
    parent::__construct($configFactory);
    $this->moduleHandler = $moduleHandler;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['user_password_randomizer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_password_randomizer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('user_password_randomizer.settings');

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

    $period = [
      0,
      900,
      1800,
      2700,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
      172800,
      259200,
      345600,
      432000,
      518400,
      604800,
      1209600,
      1814400,
      2419200,
    ];
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . $this->t('cron run') . '>';
    $form['update_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Update every'),
      '#description' => $this->t('The interval between updates.'),
      '#options' => $period,
      '#default_value' => $config->get('update_interval'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('user_password_randomizer.settings')
      ->set('username_pattern', $form_state->getValue('username_pattern'))
      ->set('randomize_username', (bool) $form_state->getValue('randomize_username'))
      ->set('update_interval', (int) $form_state->getValue('update_interval'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
