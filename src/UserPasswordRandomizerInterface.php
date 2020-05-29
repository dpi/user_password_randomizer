<?php

namespace Drupal\user_password_randomizer;

use Drupal\user\UserInterface;

/**
 * Interface for admin utility.
 */
interface UserPasswordRandomizerInterface {

  /**
   * Generates a username for a user.
   *
   * This ensures no other username than the user passed have this username.
   *
   * @param \Drupal\user\UserInterface $user
   *   Generate a new username for this user.
   */
  public function generateUsername(UserInterface $user);

}
