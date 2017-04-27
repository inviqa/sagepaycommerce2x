<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_sagepay\CommonHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

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
    $decryptedSagepayResponse = $this->decryptSagepayResponse();
    $this->createPayment($decryptedSagepayResponse, $order);
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
   * @param  array $result
   * @param  string $state
   * @param  OrderInterface $order
   * @param  string $remote_state
   */
  public function createPayment(array $decryptedSagepayResponse, OrderInterface $order) {
    // Get and check the VendorTxCode.
    $vendorTxCode = isset($decryptedSagepayResponse['VendorTxCode']) ? $decryptedSagepayResponse['VendorTxCode'] : FALSE;
    if (empty($vendorTxCode)) {
      \Drupal::logger('commerce_sagepay')->error('No VendorTxCode returned.');
      throw new PaymentGatewayException('No VendorTxCode returned.');
    }

    $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');

    $requestTime = \Drupal::service('commerce.time')->getRequestTime();
    $payment = $paymentStorage->create([
      'state' => 'authorization',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'test' => $this->getMode() == 'test',
      'remote_id' => $decryptedSagepayResponse['VendorTxCode'],
      'remote_state' => SAGEPAY_REMOTE_STATUS_OK,
      'authorized' => $requestTime,
    ]);

    // Check for a valid status callback.
    switch ($decryptedSagepayResponse['Status']) {
      case 'ABORT':
        \Drupal::logger('commerce_sagepay')
          ->alert('ABORT error from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Your SagePay transaction was aborted.'), 'error');
        return FALSE;

      case 'NOTAUTHED':
        \Drupal::logger('commerce_sagepay')
          ->alert('NOTAUTHED error from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Your transaction was not authorised by SagePay'), 'error');
        return FALSE;

      case 'REJECTED':
        \Drupal::logger('commerce_sagepay')
          ->alert('REJECTED error from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Your transaction was rejected by SagePay'), 'error');
        return FALSE;

      case 'MALFORMED':
        \Drupal::logger('commerce_sagepay')
          ->alert('MALFORMED error from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Sorry the transaction has failed.'), 'error');
        return FALSE;

      case 'INVALID':
        \Drupal::logger('commerce_sagepay')
          ->error('INVALID error from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Sorry the transaction has failed.'), 'error');
        return FALSE;

      case 'ERROR':
        \Drupal::logger('commerce_sagepay')
          ->error('System ERROR from SagePay for order %order_id with message %msg', [
            '%order_id' => $order->id(),
            '%msg' => $decryptedSagepayResponse['StatusDetail'],
          ]);
        drupal_set_message(t('Sorry an error occurred while processing your transaction.'), 'error');
        return FALSE;

      case 'OK':
        \Drupal::logger('commerce_sagepay')
          ->info('OK Payment callback received from SagePay for order %order_id with status code %status', [
            '%order_id' => $order->id(),
            '%status' => $decryptedSagepayResponse['Status'],
          ]);
        $payment->remote_state = SAGEPAY_REMOTE_STATUS_OK;
        $payment->state = 'capture_completed';
        break;

//      case 'AUTHENTICATED':
//        \Drupal::logger('commerce_sagepay')
//          ->info('AUTHENTICATED Payment callback received from SagePay for order %order_id with status code %status', [
//            '%order_id' => $order->id(),
//            '%status' => $decryptedSagepayResponse['Status'],
//          ]);
//        $payment->remote_state = SAGEPAY_REMOTE_STATUS_AUTHENTICATE;
////        $transaction_status = COMMERCE_PAYMENT_STATUS_SUCCESS;
//        $payment->state = 'capture_completed';
//        break;

      case 'REGISTERED':
        \Drupal::logger('commerce_sagepay')
          ->info('REGISTERED Payment callback received from SagePay for order %order_id with status code %status', [
            '%order_id' => $order->id(),
            '%status' => $decryptedSagepayResponse['Status'],
          ]);
        $payment->remote_state = SAGEPAY_REMOTE_STATUS_REGISTERED;
//        $transaction_status = COMMERCE_PAYMENT_STATUS_PENDING;
        $payment->state = 'capture_completed';
        break;

      default:
        // If the status code is anything other than those above, log an error.
        \Drupal::logger('commerce_sagepay')
          ->error('Unrecognised Status response from SagePay for order %order_id (%response_code)', [
            '%order_id' => $order->id(),
            '%response_code' => $decryptedSagepayResponse['Status'],
          ]);
        return FALSE;

    }

    return $payment->save();
  }

  /**
   * @return array
   */
  private function decryptSagepayResponse() {
    $formPassword = $this->configuration['test_enc_key'];
    $encryptedResponse = \Drupal::request()->query->get('crypt');
    $decrypt = \SagepayUtil::decryptAes($encryptedResponse, $formPassword);
    $decryptArray = \SagepayUtil::queryStringToArray($decrypt);
    if (!$decrypt || empty($decryptArray)) {
      throw new PaymentGatewayException('Crypt data missing for this Sagepay Form transaction.');
    }
    return $decryptArray;
  }

}
