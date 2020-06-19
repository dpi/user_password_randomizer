<?php

namespace Drupal\Tests\user_password_randomizer\Unit {


  use Drupal\Component\Datetime\TimeInterface;
  use Drupal\Core\Entity\EntityStorageInterface;
  use Drupal\Core\Entity\EntityTypeManagerInterface;
  use Drupal\Core\State\StateInterface;
  use Drupal\Tests\UnitTestCase;
  use Drupal\user\UserInterface;
  use Drupal\user_password_randomizer\RandomizerJobExecutor;
  use Drupal\user_password_randomizer\UserPasswordRandomizerInterface;
  use Psr\Log\LoggerInterface;

  /**
   * @coversDefaultClass \Drupal\user_password_randomizer\RandomizerJobExecutor
   *
   * @group user_password_randomizer
   */
  class RandomizerJobExecutorTest extends UnitTestCase {

    /**
     * @covers ::execute
     */
    public function testExecute() {
      /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockBuilder $configFactory */
      $configFactory = $this->getConfigFactoryStub([
        'user_password_randomizer.settings' => [
          'update_interval' => 60,
        ],
      ]);
      $time = $this->prophesize(TimeInterface::class);
      // 19/06/2020 @ 12:00am (UTC)
      $currentTime = 1592524800;
      $newUsername = "abcdef123456";

      $time->getRequestTime()->shouldBeCalledOnce()->willReturn($currentTime);

      $state = $this->prophesize(StateInterface::class);
      $state->get('user_password_randomizer.last_check')
        ->shouldBeCalledOnce()
        ->willReturn(NULL);
      $state->set('user_password_randomizer.last_check', $currentTime)
        ->shouldBeCalledOnce();

      $user = $this->prophesize(UserInterface::class);
      $user->getAccountName()->shouldBeCalledOnce()->willReturn("foobar");
      $user->id()->shouldBeCalledOnce()->willReturn(1);
      $user->setUsername($newUsername)->shouldBeCalledOnce();
      $user->setPassword("abcd1234")->shouldBeCalledOnce();
      $user->save()->shouldBeCalledOnce();

      $userStorage = $this->prophesize(EntityStorageInterface::class);
      $userStorage->load(1)->shouldBeCalledOnce()->willReturn($user);

      $randomizer = $this->prophesize(UserPasswordRandomizerInterface::class);

      $randomizer->generateUsername($user)
        ->shouldBeCalledOnce()
        ->willReturn($newUsername);

      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
      $entityTypeManager->getStorage('user')
        ->shouldBeCalledOnce()
        ->willReturn($userStorage);

      $logger = $this->prophesize(LoggerInterface::class);

      $executor = new RandomizerJobExecutor(
        $logger->reveal(), $configFactory,
        $state->reveal(),
        $time->reveal(),
        $randomizer->reveal(),
        $entityTypeManager->reveal()
      );
      $executor->execute();

    }

  }
}

namespace {

  if (!function_exists('user_password')) {

    /**
     * Mocks the user_password() global function.
     */
    function user_password($len) {
      return "abcd1234";
    }

  }
}
