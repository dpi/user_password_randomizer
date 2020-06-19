<?php

namespace Drupal\user_password_randomizer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes scheduled randomizer jobs.
 */
class RandomizerJobExecutor {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The user password randomizer.
   *
   * @var \Drupal\user_password_randomizer\UserPasswordRandomizerInterface
   */
  protected $randomizer;

  /**
   * The user entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * RandomizerJobExecutor constructor.
   */
  public function __construct(
    LoggerInterface $logger,
    ConfigFactoryInterface $configFactory,
    StateInterface $state,
    TimeInterface $time,
    UserPasswordRandomizerInterface $randomizer,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->state = $state;
    $this->time = $time;
    $this->randomizer = $randomizer;
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * Execute the job.
   */
  public function execute() {
    // @todo allow users to be configured.
    $userId = 1;

    $config = $this->configFactory->get('user_password_randomizer.settings');
    $updateInterval = $config->get('update_interval') ?: 0;
    $lastCheck = $this->state->get('user_password_randomizer.last_check') ?: 0;
    $requestTime = $this->time->getRequestTime();
    if (($requestTime - $lastCheck) < $updateInterval) {
      // Skipping as update interval has not yet elapsed.
      return;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($userId);
    $loggerArgs = [
      '@user' => 'user ' . $user->id(),
    ];

    // Set password to random string.
    // @todo Replace user_password() with password component.
    // @see https://www.drupal.org/project/drupal/issues/3153085
    $user->setPassword(\user_password(16));

    // Dont bother if username is the same or randomisation is off:
    $newUsername = $this->randomizer->generateUsername($user);
    if ($oldUsername = $user->getAccountName() !== $newUsername) {
      $loggerArgs['@old_username'] = $oldUsername;
      $loggerArgs['@new_username'] = $newUsername;
      $user->setUsername($newUsername);
      $this->logger->info('Randomised username and password for @user. Username changed from @old_username to @new_username',
        $loggerArgs);
    }
    else {
      $this->logger->info('Randomised password for @user', $loggerArgs);
    }

    try {
      $user->save();
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to randomise the username and password for @user: @exception_message',
        [
          '@exception_message' => $e->getMessage(),
        ]);
    }
    $this->state->set('user_password_randomizer.last_check', $requestTime);
  }

}
