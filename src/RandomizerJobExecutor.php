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
   * @var \Drupal\user\UserStorageInterface
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
   * Implements hook_cron().
   *
   * Conditionally executes user metadata randomization.
   *
   * @see \user_password_randomizer_cron()
   */
  public function cron(): void {
    $config = $this->configFactory->get('user_password_randomizer.settings');
    $updateInterval = $config->get('update_interval') ?: 0;
    $lastCheck = $this->state->get('user_password_randomizer.last_check') ?: 0;
    $requestTime = $this->time->getRequestTime();
    if (($requestTime - $lastCheck) > $updateInterval) {
      // Skipping as update interval has not yet elapsed.
      $this->execute();
    }
  }

  /**
   * Executes randomisation of randomizes user metadata.
   */
  public function execute(): void {
    // @todo allow users to be configured.
    $userId = 1;

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($userId);
    $loggerArgs = [
      '@user' => 'user ' . $user->id(),
    ];

    $user->setPassword($this->randomizer->generatePassword($user));

    // Dont bother if username is the same or randomisation is off:
    $newUsername = $this->randomizer->generateUsername($user);
    $oldUsername = $user->getAccountName();
    if ($oldUsername !== $newUsername) {
      $loggerArgs['@old_username'] = $oldUsername;
      $loggerArgs['@new_username'] = $newUsername;
      $user->setUsername($newUsername);
      $this->logger->info('Randomised username and password for @user. Username changed from @old_username to @new_username', $loggerArgs);
    }
    else {
      $this->logger->info('Randomised password for @user', $loggerArgs);
    }

    try {
      $user->save();
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to randomise the username and password for @user: @exception_message', $loggerArgs + [
        '@exception_message' => $e->getMessage(),
      ]);
    }

    $requestTime = $this->time->getRequestTime();
    $this->state->set('user_password_randomizer.last_check', $requestTime);
  }

}
