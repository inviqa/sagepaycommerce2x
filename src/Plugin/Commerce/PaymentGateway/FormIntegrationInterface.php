<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Interface FormIntegrationInterface.
 */
interface FormIntegrationInterface extends OffsitePaymentGatewayInterface, SupportsRefundsInterface {
  public function getUrl();
}
