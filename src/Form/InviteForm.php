<?php

namespace Drupal\ucb_user_invite\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ucb_user_invite\UserInviteHelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for inviting a user.
 */
class InviteForm extends FormBase {

  /**
   * The user invite helper service defined in this module.
   *
   * @var \Drupal\ucb_user_invite\UserInviteHelperService
   */
  protected $helper;

  /**
   * Constructs an InviteForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\ucb_user_invite\UserInviteHelperService $helper
   *   The user invite helper service defined in this module.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserInviteHelperService $helper) {
    $this->setConfigFactory($config_factory);
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container that allows getting any needed services.
   *
   * @link https://www.drupal.org/node/2133171 For more on dependency injection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('ucb_user_invite.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ucb_user_invite_invite_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->helper->getConfigName());

    $roles = $this->helper->getAllowedRoles();
    $defaultCustomMessage = $config->get('default_custom_message');
    $form_state->setStorage(['rids' => array_keys($roles), 'default_custom_message' => $defaultCustomMessage]);

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
    ];
    foreach ($roles as $rid => $role) {
      $form['roles']['role_' . $rid . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $role['label'],
        '#description' => $role['description'],
        '#default_value' => $role['default'],
      ];
    }
    $form['identikeys'] = [
      '#title' => $this->t('User IdentiKeys'),
      '#type' => 'textfield',
      '#description' => $this->t('Comma-separated list of <a target="_blank" href="@identikey_about_link">IdentiKeys</a> for users to be invited and given the selected roles. The users will be able to securely log in using their CU Boulder-provided credentials. Don\'t include "@colorado.edu" after the IdentiKeys.', ['@identikey_about_link' => 'https://oit.colorado.edu/services/identity-access-management/identikey']),
      '#required' => TRUE,
    ];
    $form['custom_message'] = [
      '#title' => $this->t('Custom message'),
      '#type' => 'textarea',
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t('The custom message will be included before the standard template. Tokens are supported.'),
      '#placeholder' => $defaultCustomMessage,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send invite'),
    ];

    if (empty($roles)) {
      $this->messenger()->addError($this->t('Your site is not yet configured to invite users. Contact the site administrator to <a href="@adminlink">configure the invite feature</a>.', ['@adminlink' => $this->helper->getAdminFormLink()]));
      $form['email']['#disabled'] = TRUE;
      $form['custom_message']['#disabled'] = TRUE;
      $form['submit']['#disabled'] = TRUE;
    }
    else {
      $form['submit']['#button_type'] = 'primary';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $rids = array_filter($storage['rids'], function ($rid) use ($form_state) {
      return $form_state->getValue('role_' . $rid . '_enabled');
    });
    if (empty($rids)) {
      $form_state->setErrorByName('roles', $this->t('At least one role must be selected.'));
      return;
    }
    $form_state->setValue('roles', $rids);

    $invitedUsers = preg_split("/[,\s+]/", $form_state->getValue('identikeys'), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($invitedUsers as $invitedUser) {
      if (!$this->helper->isCuBoulderIdentiKeyValid($invitedUser)) {
        $form_state->setErrorByName('identikeys', $this->t('Invalid IdentiKey: @value', ['@value' => $invitedUser]));
      }
    }
    $form_state->setValue('invited_users', $invitedUsers);
    $customMessage = $form_state->getValue('custom_message');
    $form_state->setValue('custom_message', $customMessage ? $customMessage : $storage['default_custom_message']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invitedUsers = $form_state->getValue('invited_users');
    $rids = $form_state->getValue('roles');
    $customMessage = $form_state->getValue('custom_message');
    $this->helper->invite($invitedUsers, $rids, $customMessage);
  }

}
