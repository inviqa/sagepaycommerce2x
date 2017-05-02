<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;

/**
 * Interface FormIntegrationInterface.
 */
interface FormIntegrationInterface extends OffsitePaymentGatewayInterface {

  /**
   * Get the Sagepay form integration url.
   */
  public function getUrl();

  /**
   * Builds the transaction data.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The commerce payment object.
   *
   * @return array
   *   Transaction data.
   */
  public function buildTransaction(PaymentInterface $payment);

}
