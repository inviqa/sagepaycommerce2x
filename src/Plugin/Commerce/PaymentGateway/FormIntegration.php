<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the Sagepay Form Integration payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "sagepay_form",
 *   label = @Translation("Sagepay (Form Integration)"),
 *   display_label = @Translation("Sagepay"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_sagepay\PluginForm\FormIntegrationForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 *   modes = {
 *    SAGEPAY_ENV_TEST = "Test", SAGEPAY_ENV_LIVE = "Live",
 *   },
 * )
 */
class FormIntegration extends OffsitePaymentGatewayBase implements FormIntegrationInterface {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Constructs a new PaymentGatewayBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, RequestStack $requestStack, LoggerChannelFactoryInterface $loggerChannelFactory, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
    $this->requestStack = $requestStack;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['vendor'] = [
      '#type' => 'textfield',
      '#title' => t('SagePay Vendor Name'),
      '#description' => t('This is the vendor name that SagePay sent you when
     you set up your account.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['vendor'],
    ];

    $form['enc_key'] = [
      '#type' => 'textfield',
      '#title' => t('Encryption Key'),
      '#description' => t('If you have requested form based integration, you will have received an encryption key from SagePay in a separate email.'),
      '#default_value' => (isset($this->configuration['enc_key'])) ? $this->configuration['enc_key'] : '',
    ];

    $form['test_enc_key'] = [
      '#type' => 'textfield',
      '#title' => t('Test Mode Encryption Key'),
      '#description' => t('If you have requested form based integration, you will have received an encryption key from SagePay in a separate email. The encryption key for the test server is different to the one used in your production environment.'),
      '#default_value' => (isset($this->configuration['test_enc_key'])) ? $this->configuration['test_enc_key'] : '',
    ];

    // Default transaction settings.
    // These can be overriden using hook_sagepay_order_data_alter.
    $form['transaction'] = [
      '#type' => 'fieldset',
      '#title' => 'Transaction Settings',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['transaction']['sagepay_order_description'] = [
      '#type' => 'textfield',
      '#title' => t('Order Description'),
      '#description' => $this->t('The description of the order that will appear in the SagePay transaction. (For example, Your order from sitename.com)'),
      '#default_value' => (isset($this->configuration['sagepay_order_description'])) ? $this->configuration['sagepay_order_description'] : $this->t('Your order from sitename.com'),
    ];

    $form['transaction']['sagepay_txn_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Transaction Code Prefix'),
      '#description' => $this->t('This allows you to add an optional prefix to all transaction codes.'),
      '#default_value' => (isset($this->configuration['sagepay_txn_prefix'])) ? $this->configuration['sagepay_txn_prefix'] : '',
    ];

    $form['transaction']['sagepay_account_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Account Type'),
      '#description' => $this->t('This optional flag is used to tell the SAGE PAY System which merchant account to use.'),
      '#options' => [
        'E' => $this->t('Use the e-commerce merchant account (default).'),
        'C' => $this->t('Use the continuous authority merchant account (if present).'),
        'M' => $this->t('Use the mail order, telephone order account (if present).'),
      ],
      '#default_value' => (isset($this->configuration['sagepay_account_type'])) ? $this->configuration['sagepay_account_type'] : 'E',
    ];

    $form['security'] = [
      '#type' => 'fieldset',
      '#title' => 'Security Checks',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['security']['sagepay_apply_avs_cv2'] = [
      '#type' => 'radios',
      '#title' => t('AVS / CV2 Mode'),
      '#description' => t('CV2 validation mode used by default on all transactions.'),
      '#options' => [
        '0' => t('If AVS/CV2 enabled then check them. If rules apply, use rules. (default)'),
        '1' => t('Force AVS/CV2 checks even if not enabled for the account. If rules apply, use rules.'),
        '2' => t('Force NO AVS/CV2 checks even if enabled on account.'),
        '3' => t('Force AVS/CV2 checks even if not enabled for the account but DO NOT apply any rules.'),
      ],
      '#default_value' => (isset($this->configuration['sagepay_apply_avs_cv2'])) ? $this->configuration['sagepay_apply_avs_cv2'] : 0,
    ];

//    $form['order_settings'] = [
//      '#type' => 'fieldset',
//      '#title' => 'Order Settings',
//      '#collapsible' => TRUE,
//      '#collapsed' => TRUE,
//    ];
//
//    $form['order_settings']['sagepay_send_basket_contents'] = [
//      '#type' => 'select',
//      '#title' => t('Send cart contents to SagePay'),
//      '#description' => t('Send the order lines to SagePay as well as the order total.'),
//      '#options' => [
//        '0' => t('Do not send basket contents'),
//        '1' => t('Send as text'),
//        '2' => t('Send as XML'),
//      ],
//      '#default_value' => (isset($this->configuration['sagepay_send_basket_contents'])) ? $this->configuration['sagepay_send_basket_contents'] : 0,
//    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['vendor'] = $values['vendor'];
      $this->configuration['enc_key'] = $values['enc_key'];
      $this->configuration['test_enc_key'] = $values['test_enc_key'];
      $this->configuration['sagepay_order_description'] = $values['transaction']['sagepay_order_description'];
      $this->configuration['sagepay_txn_prefix'] = $values['transaction']['sagepay_txn_prefix'];
      $this->configuration['sagepay_account_type'] = $values['transaction']['sagepay_account_type'];
      $this->configuration['sagepay_apply_avs_cv2'] = $values['security']['sagepay_apply_avs_cv2'];
//      $this->configuration['sagepay_send_basket_contents'] = $values['order_settings']['sagepay_send_basket_contents'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $decryptedSagepayResponse = $this->decryptSagepayResponse($this->configuration['test_enc_key'], $this->requestStack->getCurrentRequest()->query->get('crypt'));

    if (!$decryptedSagepayResponse) {
      die('couldnt decrypt sagepay response');
    }

    // Get and check the VendorTxCode.
    $vendorTxCode = isset($decryptedSagepayResponse['VendorTxCode']) ? $decryptedSagepayResponse['VendorTxCode'] : FALSE;
    if (empty($vendorTxCode)) {
      $this->loggerChannelFactory->get('commerce_sagepay')
        ->error('No VendorTxCode returned.');
      throw new PaymentGatewayException('No VendorTxCode returned.');
    }

    if (FALSE && in_array($decryptedSagepayResponse['Status'], [
      SAGEPAY_REMOTE_STATUS_OK,
      SAGEPAY_REMOTE_STATUS_REGISTERED,
    ])) {
      $payment = $this->createPayment($decryptedSagepayResponse, $order);
      $payment->remote_state = $decryptedSagepayResponse['Status'];
      $payment->state = 'capture_completed';
      $payment->save();
      $logLevel = 'info';
      $logMessage = 'OK Payment callback received from SagePay for order %order_id with status code %status';
      $logContext = [
        '%order_id' => $order->id(),
        '%status' => $decryptedSagepayResponse['Status'],
      ];

      $this->loggerChannelFactory->get('commerce_sagepay')
        ->log($logLevel, $logMessage, $logContext);
    }
    else {
      $sagepayError = $this->decipherSagepayError($order, $decryptedSagepayResponse);
      $logLevel = $sagepayError['logLevel'];
      $logMessage = $sagepayError['logMessage'];
      $logContext = $sagepayError['logContext'];
      $this->loggerChannelFactory->get('commerce_sagepay')
        ->log($logLevel, $logMessage, $logContext);
      drupal_set_message($sagepayError['drupalMessage'], $sagepayError['drupalMessageType']);
      throw new PaymentGatewayException('ERROR result from Sagepay for order ' . $decryptedSagepayResponse['VendorTxCode']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    $url = SAGEPAY_FORM_SERVER_TEST;
    if ($this->getMode() == SAGEPAY_ENV_LIVE) {
      $url = SAGEPAY_FORM_SERVER_LIVE;
    }
    return $url;
  }

  /**
   * Create a Commerce Payment from a Sagepay form request successful result.
   *
   * @return PaymentInterface $payment
   *    The commerce payment record.
   */
  public function createPayment(array $decryptedSagepayResponse, OrderInterface $order) {

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $paymentStorage */
    $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');

    /** @var PaymentInterface $payment */
    $payment = $paymentStorage->create([
      'state' => 'authorization',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'test' => $this->getMode() == 'test',
      'remote_id' => $decryptedSagepayResponse['VendorTxCode'],
      'remote_state' => SAGEPAY_REMOTE_STATUS_OK,
      'authorized' => $this->time->getRequestTime(),
    ]);

    $payment->save();

    return $payment;
  }

  /**
   * Decrypt the Sagepay response.
   *
   * @return array
   *    An array of the decrypted Sagepay response.
   */
  private function decryptSagepayResponse($formPassword, $encryptedResponse) {
    $decrypt = \SagepayUtil::decryptAes($encryptedResponse, $formPassword);
    $decryptArray = \SagepayUtil::queryStringToArray($decrypt);
    if (!$decrypt || empty($decryptArray)) {
      throw new PaymentGatewayException('Crypt data missing for this Sagepay Form transaction.');
    }
    return $decryptArray;
  }

  /**
   * Decipher the type of error returned by Sagepay.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *    The commerce order instance.
   * @param array $decryptedSagepayResponse
   *    The decrypted Sagepay response.
   *
   * @return array
   *    The array of statuses and messages.
   */
  private function decipherSagepayError(OrderInterface $order, array $decryptedSagepayResponse = []) {

    // Check for a valid status callback.
    switch ($decryptedSagepayResponse['Status']) {
      case 'ABORT':
        $logLevel = 'alert';
        $logMessage = 'ABORT error from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Your SagePay transaction was aborted.');
        $drupalMessageType = 'error';
        break;

      case 'NOTAUTHED':
        $logLevel = 'alert';
        $logMessage = 'NOTAUTHED error from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Your transaction was not authorised by SagePay.');
        $drupalMessageType = 'error';
        break;

      case 'REJECTED':
        $logLevel = 'alert';
        $logMessage = 'REJECTED error from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Your transaction was rejected by SagePay.');
        $drupalMessageType = 'error';
        break;

      case 'MALFORMED':
        $logLevel = 'alert';
        $logMessage = 'MALFORMED error from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Sorry the transaction has failed.');
        $drupalMessageType = 'error';
        break;

      case 'INVALID':
        $logLevel = 'error';
        $logMessage = 'INVALID error from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Sorry the transaction has failed.');
        $drupalMessageType = 'error';
        break;

      case 'ERROR':

        $logLevel = 'error';
        $logMessage = 'System ERROR from SagePay for order %order_id with message %msg';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Sorry an error occurred while processing your transaction.');
        $drupalMessageType = 'error';

        break;

      default:
        $logLevel = 'error';
        $logMessage = 'Unrecognised Status response from SagePay for order %order_id (%response_code)';
        $logContext = [
          '%order_id' => $order->id(),
          '%msg' => $decryptedSagepayResponse['StatusDetail'],
        ];
        $drupalMessage = $this->t('Sorry an error occurred while processing your transaction.');
        $drupalMessageType = 'error';
    }

    return [
      'logLevel' => $logLevel,
      'logMessage' => $logMessage,
      'logContext' => $logContext,
      'drupalMessage' => $drupalMessage,
      'drupalMessageType' => $drupalMessageType,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('datetime.time')
    );
  }

}
