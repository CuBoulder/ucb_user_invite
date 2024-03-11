<?php

namespace Drupal\ucb_user_invite\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ucb_user_invite\UserInviteHelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The administration form for the User Invite module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The user invite helper service defined in this module.
   *
   * @var \Drupal\ucb_user_invite\UserInviteHelperService
   */
  protected $helper;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\ucb_user_invite\UserInviteHelperService $helper
   *   The user invite helper service defined in this module.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserInviteHelperService $helper) {
    parent::__construct($config_factory);
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
  protected function getEditableConfigNames() {
    return [$this->helper->getConfigName()];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ucb_user_invite_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->helper->getConfigName());

    $roles = $this->helper->getAllRoleNames();
    $roleSettings = $config->get('roles') ?? [];

    $form_state->setStorage(['rids' => array_keys($roles)]);
    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles users can be invited to join'),
    ];
    foreach ($roles as $rid => $roleName) {
      $form['roles'][$rid . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $roleName,
        '#default_value' => isset($roleSettings[$rid]['status']) && $roleSettings[$rid]['status'],
      ];
      $form['roles'][$rid . '_description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Role description'),
        '#description' => $this->t('Optionally set a description for this role to appear when sending an invite.'),
        '#default_value' => $roleSettings[$rid]['description'] ?? $roleSettings[$rid]['description'],
        '#maxlength' => 1024,
        '#states' => [
          'visible' => [
            ':input[name="' . $rid . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['default_role'] = [
      '#title' => $this->t('Default role'),
      '#description' => $this->t('Choose the default role you wish to have selected on the invite page.'),
      '#type' => 'radios',
      '#options' => $roles,
      '#default_value' => $config->get('default_role') ?? '',
      '#required' => TRUE,
    ];

    $form['default_custom_message'] = [
      '#title' => $this->t('Default custom message'),
      '#description' => $this->t('Set a default custom message to appear before the standard template. This can be edited on the invite page to personalize each invite. Tokens are supported.'),
      '#type' => 'textarea',
      '#cols' => 40,
      '#rows' => 5,
      '#default_value' => $config->get('default_custom_message'),
    ];

    // Message templates.
    $form['invite_subject'] = [
      '#title' => $this->t('Invitation email subject'),
      '#type' => 'textfield',
      '#default_value' => $config->get('invite_subject'),
      '#required' => FALSE,
    ];
    $form['invite_template'] = [
      '#title' => $this->t('Invitation email template'),
      '#type' => 'textarea',
      '#cols' => 40,
      '#rows' => 5,
      '#default_value' => $config->get('invite_template'),
      '#description' => $this->t('Message sent to user being invited. Tokens are supported.'),
      '#required' => TRUE,
    ];

    $form['confirmation_subject'] = [
      '#title' => $this->t('Confirmation email subject'),
      '#type' => 'textfield',
      '#default_value' => $config->get('confirmation_subject') ?? '',
      '#required' => FALSE,
    ];
    $form['confirmation_template'] = [
      '#title' => $this->t('Confirmation email template'),
      '#type' => 'textarea',
      '#cols' => 40,
      '#rows' => 5,
      '#default_value' => $config->get('confirmation_template'),
      '#description' => $this->t('Confirmation message sent to user who initiated the invitation confirming the invitation was sent. Tokens are supported.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $rids = $form_state->getStorage()['rids'];
    $roles = [];
    $defaultRole = $form_state->getValue('default_role');
    $defaultRoleValid = FALSE;
    foreach ($rids as $rid) {
      $roles[$rid] = [
        'status' => (bool) $form_state->getValue($rid . '_enabled'),
        'description' => $form_state->getValue($rid . '_description'),
      ];
      if ($rid == $defaultRole && $roles[$rid]['status']) {
        $defaultRoleValid = TRUE;
      }
    }
    $form_state->setValue('roles', $roles);

    if (!$defaultRoleValid) {
      $form_state->setErrorByName('default_role', $this->t('Default role can only be one of the roles selected to invite.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->helper->getConfigName())
      ->set('roles', $form_state->getValue('roles'))
      ->set('default_role', $form_state->getValue('default_role'))
      ->set('default_custom_message', $form_state->getValue('default_custom_message'))
      ->set('invite_subject', $form_state->getValue('invite_subject'))
      ->set('invite_template', $form_state->getValue('invite_template'))
      ->set('confirmation_subject', $form_state->getValue('confirmation_subject'))
      ->set('confirmation_template', $form_state->getValue('confirmation_template'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
