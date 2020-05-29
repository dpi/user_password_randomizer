<?php

namespace Drupal\admin_denied;

use Drupal\user\UserInterface;

/**
 * Interface for admin utility.
 */
interface AdminDeniedUtilityInterface {

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
