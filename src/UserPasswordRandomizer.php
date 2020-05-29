<?php

namespace Drupal\user_password_randomizer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\user\UserInterface;

/**
 * User Password Randomizer utility.
 */
class UserPasswordRandomizer implements UserPasswordRandomizerInterface {

  /**
   * The token replacement instance.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * User storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * UserPasswordRandomizer constructor.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(Token $token, ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->token = $token;
    $this->configFactory = $configFactory;
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function generateUsername(UserInterface $user) {
    $config = $this->configFactory->get('user_password_randomizer.settings');

    if (!$config->get('randomize_username')) {
      return $user->getAccountName();
    }

    $countUsers = function ($username) use ($user) {
      return (int) $this->userStorage->getQuery()
        ->condition('name', $username)
        // Don't include this user.
        ->condition($this->userStorage->getEntityType()->getKey('id'), $user->id(), '<>')
        ->count()
        ->execute();
    };

    // Generate usernames until it is unique.
    $pattern = $config->get('username_pattern');
    do {
      if (!empty($pattern)) {
        $newUsername = $this->token->replace($pattern, ['user' => $user]);
      }
      else {
        // When pattern is empty we generate a random (not cryptographically
        // secure) string.
        $newUsername = user_password(16);
      }
    } while ($countUsers($newUsername) > 0);

    return $newUsername;
  }

}
