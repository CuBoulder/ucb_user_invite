<?php

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

/**
 * Contains helper functions used by the form classes.
 */
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
   * The entity type manager.
   *
   * Used in the process of checking for an existing user account.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The entity type repository.
   *
   * Used in the process of checking for an existing user account.
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
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager used for sending emails.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entityTypeRepository
   *   The entity type repository.
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
   * Gets the machine name of the CU Boulder User Invite settings.
   *
   * @return string
   *   The machine name of the CU Boulder User Invite settings.
   */
  public function getConfigName() {
    return 'ucb_user_invite.settings';
  }

  /**
   * Gets the CU Boulder User Invite settings.
   *
   * @return \Drupal\Core\Config\Config
   *   The read-only CU Boulder User Invite settings, to be used locally.
   */
  protected function getConfig() {
    return $this->configFactory->get($this->getConfigName());
  }

  /**
   * Gets a mapped array of all user roles available on this Drupal instance.
   *
   * @return array
   *   id -> label mapping of all roles for invites.
   */
  public function getAllRoleLabels() {
    // Load all user role entites.
    $userRoles = $this->entityTypeManager->getStorage($this->entityTypeRepository->getEntityTypeFromClass(Role::class))->loadMultiple();
    // Remove the anonymous role.
    unset($userRoles[Role::ANONYMOUS_ID]);
    return array_map(
    function (Role $roleEntity) {
      // Convert the role entity to a string.
      return $roleEntity->label();
    }, $userRoles);
  }

  /**
   * Gets the allowed roles.
   *
   * @return array
   *   id -> label, description mapping of only allowed roles for invites.
   */
  public function getAllowedRoles() {
    $roleSettings = $this->getConfig()->get('roles') ?? [];
    $roleLabels = $this->getAllRoleLabels();
    $allowedRoles = [];
    foreach ($roleLabels as $rid => $roleLabel) {
      if (isset($roleSettings[$rid]['status']) && $roleSettings[$rid]['status']) {
        $allowedRoles[$rid] = [
          'label' => $roleLabel,
          'default' => $roleSettings[$rid]['default'] ?? FALSE,
          'description' => $roleSettings[$rid]['description'] ?? '',
        ];
      }
    }
    return $allowedRoles;
  }

  /**
   * Gets the link to the administration form.
   *
   * @return string
   *   The URL path to the settings form for CU Boulder User Invite.
   */
  public function getAdminFormLink() {
    return $this->urlGenerator->generateFromRoute('ucb_user_invite.settings_form');
  }

  /**
   * Converts a supplied identikey to a full email address.
   *
   * @param string $identikey
   *   The CU Boulder IdentiKey.
   *
   * @return string
   *   [IdentiKey]@colorado.edu
   */
  public function cuBoulderIdentiKeyToEmail($identikey) {
    return "{$identikey}@colorado.edu";
  }

  /**
   * Checks if a user-supplied email is valid.
   *
   * @param string $email
   *   The email to validate.
   *
   * @return bool
   *   TRUE if the email is valid, FALSE if not.
   */
  public function isEmailValid($email) {
    return $this->emailValidator->isValid($email);
  }

  /**
   * Checks if a user-supplied identikey is valid.
   *
   * @param string $identikey
   *   The CU Boulder IdentiKey to validate.
   *
   * @return bool
   *   TRUE if the CU Boulder IdentiKey is valid, FALSE if not.
   */
  public function isCuBoulderIdentiKeyValid($identikey) {
    // @todo Actual IdentiKey validation instead of validating as an email
    return $this->isEmailValid($this->cuBoulderIdentiKeyToEmail($identikey));
  }

  /**
   * Sends user invites.
   *
   * Emails users inviting them to log in to the site, and grants their
   * accounts a role.
   *
   * @param string[] $invitedUsers
   *   An array of CU Boulder IdentiKeys.
   * @param string[] $rids
   *   The roles to grant. Must be an array of Role entity ids.
   * @param string $customMessage
   *   A custom message to include in the invite email.
   * @param bool $mailConfirmation
   *   Whether to mail a confirmation back to the sender in addition to sending
   *   the invites. Defaults to TRUE.
   */
  public function invite(array $invitedUsers, $rids, $customMessage, $mailConfirmation = TRUE) {
    $roles = $this->entityTypeManager->getStorage($this->entityTypeRepository->getEntityTypeFromClass(Role::class))->loadMultiple($rids);
    // Data to pass to the email message template.
    $data = [
      'config_name' => $this->getConfigName(),
      'invite_role_label' => implode(', ', array_map(function ($role) {
        return $role->label();
      }, $roles)),
      'invite_role_id' => implode(', ', array_map(function ($role) {
        return $role->id();
      }, $roles)),
      'invite_custom_message' => $customMessage,
      'invite_user_list' => $invitedUsers,
      'invite_address_list' => array_map(
        function ($invitedUser) {
          return $this->cuBoulderIdentiKeyToEmail($invitedUser);
        }, $invitedUsers),
    ];
    $senderEmail = $this->user->getEmail();
    foreach ($invitedUsers as $invitedUser) {
      $invitedAddress = $this->cuBoulderIdentiKeyToEmail($invitedUser);
      // Mail the invite. Reply-To will be set to the email of the user sending
      // the invite.
      $output = $this->mailManager->mail('ucb_user_invite', 'invite', $invitedAddress, $this->user->getPreferredAdminLangcode(), $data, $senderEmail);
      if ($output['result']) {
        $this->messenger->addStatus('Invite sent to ' . $invitedAddress . '!');
        $this->createAccount($invitedUser, $invitedAddress, $rids);
      }
      else {
        $this->messenger->addError('Invite to ' . $invitedAddress . ' failed!');
      }
    }
    // Mail the confirmation back to the sender. Reply-To will be set to the
    // administration email of the site.
    if ($mailConfirmation) {
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
   * @param string[] $rids
   *   The roles to grant. Must be an array of Role entity ids.
   */
  protected function createAccount($invitedUser, $invitedAddress, $rids) {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeRepository->getEntityTypeFromClass(User::class));
    // Comes back as an array but there should be only one.
    $existingUserIds = $storage->getQuery()->accessCheck(FALSE)->condition('name', $invitedUser)->execute();
    /** @var \Drupal\user\UserInterface|null $existingUser */
    if ($existingUserIds && ($existingUser = $storage->load(array_keys($existingUserIds)[0]))) {
      // Trying to add `authenticated` role results in error, this avoids it.
      foreach ($rids as $rid) {
        if ($rid != Role::AUTHENTICATED_ID) {
          $existingUser->addRole($rid);
        }
      }
      // Update identikey field if it exists and is empty.
      if ($existingUser->hasField('field_identikey') && $existingUser->get('field_identikey')->isEmpty()) {
        $existingUser->set('field_identikey', $invitedUser);
      }
      $existingUser->save();
    }
    else {
      $user_data = [
        'name' => $invitedUser,
        'mail' => $invitedAddress,
        // This password isn't used to login, SSO is used instead.
        'pass' => 'password',
        'status' => 1,
        'roles' => $rids,
      ];
      // Add identikey field if it exists.
      $new_user = User::create($user_data);
      if ($new_user->hasField('field_identikey')) {
        $new_user->set('field_identikey', $invitedUser);
      }
      $new_user->enforceIsNew()->save();
    }
  }

}
