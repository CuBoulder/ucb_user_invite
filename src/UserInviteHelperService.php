<?php

/**
 * @file
 * Contains \Drupal\ucb_user_invite\UserInviteHelperService.
 */

namespace Drupal\ucb_user_invite;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

class UserInviteHelperService {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger, used for setting status messages.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The email validator used for validating full email addresses.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The mail manager used for sending emails.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The entity type manager, used in the process of checking for an existing user account.
   * 
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  

  /**
   * The entity type repository, used in the process of checking for an existing user account.
   * 
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * Constructs a UserInviteHelperService.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger, used for setting status messages.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator.
   * @param \Drupal\Core\Extension\EmailValidatorInterface $emailValidator
   *   The email validator used for validating full email addresses.
   * @param \Drupal\Core\Mail\MailManagerInterface
   *   The mail manager used for sending emails.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager, used in the process of checking for an existing user account.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface
   *   The entity type repository, used in the process of checking for an existing user account.
   */
  public function __construct(
    AccountInterface $user,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    UrlGeneratorInterface $urlGenerator,
    EmailValidatorInterface $emailValidator,
    MailManagerInterface $mailManager,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeRepositoryInterface $entityTypeRepository
  ) {
    $this->user = $user;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->urlGenerator = $urlGenerator;
    $this->emailValidator = $emailValidator;
    $this->mailManager = $mailManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeRepository = $entityTypeRepository;
  }

  /**
   * @return string
   *   The machine name of the CU Boulder User Invite configuration.
   */
  public function getConfigName() {
    return 'ucb_user_invite.configuration';
  }

  /**
   * @return \Drupal\Core\Config\Config
   *   The read-only CU Boulder User Invite configuration, to be used locally.
   */
  protected function getConfig() {
    return $this->configFactory->get($this->getConfigName());
  }

  /**
   * @return array
   *   id -> label mapping of all roles for invites.
   */
  public function getAllRoleNames() {
    $userRoles = Role::loadMultiple(); // Load all user role entites
    unset($userRoles[Role::ANONYMOUS_ID]); // Remove the anonymous role
    return array_map(
      function(Role $roleEntity) {
        return $roleEntity->label(); // Convert the role entity to a string
      }, $userRoles);
  }

  /**
   * @return array
   *   id -> label mapping of only allowed roles for invites.
   */
  public function getAllowedRoleNames() {
    $userRoleNames = $this->getAllRoleNames();
    $allowedRoleIds = $this->getConfig()->get('roles') ?? [];
    return array_filter($userRoleNames, 
      function($userRoleId) use ($allowedRoleIds) {
        return isset($allowedRoleIds[$userRoleId]); // Filter roles that are not allowed
      }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * @return string
   *   The URL path to the configuration settings form for CU Boulder User Invite.
   */
  public function getAdminFormLink() {
    return $this->urlGenerator->generateFromRoute('ucb_user_invite.settings_form');
  }

  /**
   * @param string $identikey
   *   The CU Boulder IdentiKey
   * @return string
   *   [IdentiKey]@colorado.edu
   */
  public function cuBoulderIdentiKeyToEmail($identikey) {
    return "{$identikey}@colorado.edu";
  }

  /**
   * @param string $email
   *   The email to validate.
   * @return bool
   *   TRUE if the email is valid, FALSE if not.
   */
  public function isEmailValid($email) {
    return $this->emailValidator->isValid($email);
  }

  /**
   * @param string $identikey
   *   The CU Boulder IdentiKey to validate.
   * @return bool
   *   TRUE if the CU Boulder IdentiKey is valid, FALSE if not.
   */
  public function isCUBoulderIdentiKeyValid($identikey) {
    // TODO: Actual IdentiKey validation instead of validating as an email
    return $this->isEmailValid($this->cuBoulderIdentiKeyToEmail($identikey));
  }

  /**
   * Emails CU Boulder users inviting them to log in to the site, and grants their accounts a role.
   * 
   * @param string[] $invitedUsers
   *   An array of CU Boulder IdentiKeys.
   * @param string $roleId
   *   The role to grant. Must be the id of a Role entity.
   * @param string $customMessage
   *   A custom message to include in the invite email.
   * @param bool $mailConfirmation
   *   Whether to mail a confirmation back to the sender in addition to sending the invites. Defaults to TRUE.
   */
  public function invite(array $invitedUsers, $roleId, $customMessage, $mailConfirmation = TRUE) {
    $role = Role::load($roleId);
    $data = [ // Data to pass to the email message template
      'config_name' => $this->getConfigName(),
      // 'invite_role' => $role,
      'invite_role_label' => $role->label(),
      'invite_role_id' => $role->id(),
      'invite_custom_message' => $customMessage,
      'invite_user_list' => $invitedUsers,
      'invite_address_list' => array_map(
        function($invitedUser) {
          return $this->cuBoulderIdentiKeyToEmail($invitedUser);
        }, $invitedUsers)];
    $senderEmail = $this->user->getEmail();
    foreach($invitedUsers as $invitedUser) {
      $invitedAddress = $this->cuBoulderIdentiKeyToEmail($invitedUser);
      // Mail the invite. Reply-To will be set to the email of the user sending the invite.
      $output = $this->mailManager->mail('ucb_user_invite', 'invite', $invitedAddress, $this->user->getPreferredAdminLangcode(), $data, $senderEmail);
      if($output['result']) {
        $this->messenger->addStatus('Invite sent to ' . $invitedAddress . '!');
        $this->createAccount($invitedUser, $invitedAddress, $roleId);
      } else {
        $this->messenger->addError('Invite to ' . $invitedAddress . ' failed!');
      }
    }
    if($mailConfirmation) { // Mail the confirmation back to the sender. Reply-To will be set to the administration email of the site.
      $administratorEmail = $this->configFactory->get('system.site')->get('mail');
      $this->mailManager->mail('ucb_user_invite', 'confirmation', $senderEmail, $this->user->getPreferredAdminLangcode(), $data, $administratorEmail);
    }
  }

  /**
   * Creates an account with a role or grants a role if one already exists.
   * 
   * @param string $invitedUser
   *   The unique username.
   * @param string $invitedAddress
   *   The email address of the user.
   * @param string $roleId
   *   The role to grant. Must be the id of a Role entity.
   */
  protected function createAccount($invitedUser, $invitedAddress, $roleId) {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeRepository->getEntityTypeFromClass(User::class));
    $existingUserIds = $storage->getQuery()->accessCheck(FALSE)->condition('name', $invitedUser)->execute(); // Comes back as an array but there should be only one
    if($existingUserIds) {
      $existingUser = User::load(array_keys($existingUserIds)[0]);
      if($roleId != Role::AUTHENTICATED_ID) // Trying to add `authenticated` role results in error, this avoids it
        $existingUser->addRole($roleId);
      $existingUser->save();
    } else {
      User::create([
        'name' => $invitedUser,
        'mail' => $invitedAddress,
        'pass' => 'password', // These accounts can't be authenticated locally so this makes no difference
        'status' => 1,
        'roles' => $roleId,
      ])->enforceIsNew()->save();
    }
  }
}
