<?php

namespace Drupal\commerce_sagepay\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway\FormIntegrationInterface;
use Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway\FormInterface;
use Drupal\Core\Form\FormStateInterface;

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

    /** @var FormIntegrationInterface $paymentGatewayPlugin */
    $paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();

    $request = $paymentGatewayPlugin->buildTransaction($payment);

    foreach ($request as $name => $value) {
      if (!empty($value)) {
        $form[$name] = array('#type' => 'hidden', '#value' => $value);
      }
    }

    return $this->buildRedirectForm($form, $form_state, $paymentGatewayPlugin->getUrl(), $request, BasePaymentOffsiteForm::REDIRECT_POST);
  }

}
