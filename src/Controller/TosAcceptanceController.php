<?php

namespace Drupal\ucb_user_invite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling TOS acceptance.
 */
class TosAcceptanceController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a TosAcceptanceController object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(AccountProxyInterface $current_user, UserStorageInterface $user_storage) {
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Handles TOS acceptance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function accept(Request $request) {
    $user = $this->userStorage->load($this->currentUser->id());
    
    if (!$user) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'User not found.',
      ], 404);
    }

    if (!$user->hasField('field_tos_acceptance')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'TOS acceptance field not found.',
      ], 500);
    }

    // Set TOS accepted to TRUE.
    $user->set('field_tos_acceptance', 1);
    
    // Update the accepted_date field if it exists.
    if ($user->hasField('field_accepted_date')) {
      // Create a DrupalDateTime object in UTC timezone.
      $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
      // Format according to datetime storage format.
      $formatted_date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      $user->set('field_accepted_date', $formatted_date);
    }
    
    $user->save();

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Terms of Service accepted successfully.',
    ]);
  }

}
