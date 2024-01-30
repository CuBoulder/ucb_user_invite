<?php

namespace Drupal\ucb_user_invite\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ucb_user_invite\UserInviteHelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

    // Define roles that users can have.
    $role_options = $this->helper->getAllowedRoleNames();
    
    $form['role'] = [
      '#title' => $this->t('Role'),
      '#type' => 'radios',
      '#options' => $role_options,
      '#default_value' => $config->get('default_role') ?? '',
      '#required' => TRUE,
    ];
    $form['identikeys'] = [
      '#title' => $this->t('CU Boulder User IdentiKeys'),
      '#type' => 'textfield',
      '#description' => $this->t('Comma-separated list of users that are invited and granted the above role.'),
      '#required' => TRUE,
    ];
    $form['custom_message'] = [
      '#title' => $this->t('Custom message'),
      '#type' => 'textarea',
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t('The custom message will be included before the standard template. Tokens are supported.'),
      '#default_value' => $config->get('default_custom_message'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send invite'),
    ];

    if (empty($role_options)) {
      $this->messenger()->addError($this->t('Your site is not yet configured to invite users. Contact the site administrator to <a href="@adminlink">configure the invite feature</a>.', ['@adminlink' => $this->helper->getAdminFormLink()]));
      $form['rid']['#options'] = ['' => '(no roles avaliable)'];
      $form['rid']['#disabled'] = TRUE;
      $form['email']['#disabled'] = TRUE;
      $form['custom_message']['#disabled'] = TRUE;
      $form['submit']['#disabled'] = TRUE;
    } else {
      $form['submit']['#button_type'] = 'primary';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invitedUsers = $form_state->getValue('invited_users');
    $roleId = $form_state->getValue('role');
    $customMessage = $form_state->getValue('custom_message');
    $this->helper->invite($invitedUsers, $roleId, $customMessage);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $invitedUsers = preg_split("/[,\s+]/", $form_state->getValue('identikeys'), -1, PREG_SPLIT_NO_EMPTY);
    foreach($invitedUsers as $invitedUser) {
      if (!$this->helper->isCUBoulderIdentiKeyValid($invitedUser)) {
        $form_state->setErrorByName('identikeys', $this->t('Invalid CU Boulder IdentiKey: ' . $invitedUser .''));
      }
    }
    $form_state->setValue('invited_users', $invitedUsers);
  }
}
