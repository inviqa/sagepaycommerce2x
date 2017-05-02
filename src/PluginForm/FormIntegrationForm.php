<?php

namespace Drupal\commerce_sagepay\PluginForm;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway\FormIntegrationInterface;
use Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway\FormInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use SagepayApiFactory;
use SagepaySettings;

/**
 * Class FormIntegrationForm.
 */
class FormIntegrationForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var FormIntegrationInterface $payment_gateway_plugin */
    $paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();

    $request = $paymentGatewayPlugin->buildTransaction($payment);

    foreach ($request as $name => $value) {
      if (!empty($value)) {
        $form[$name] = array('#type' => 'hidden', '#value' => $value);
      }
    }

    $redirectUrl = $paymentGatewayPlugin->getUrl();
    return $this->buildRedirectForm($form, $form_state, $redirectUrl, $request, BasePaymentOffsiteForm::REDIRECT_POST);
  }

  /**
   * Builds the URL to the "return" page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The "return" page url.
   */
  protected function buildReturnUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => FALSE])->toString();
  }

  /**
   * Builds the URL to the "cancel" page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The "cancel" page url.
   */
  protected function buildCancelUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => FALSE])->toString();
  }

}
