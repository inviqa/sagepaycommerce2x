<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use SagepayBasket;
use SagepayCustomerDetails;
use SagepayItem;

/**
 * Trait SagepayCommon.
 */
trait SagepayCommon {

  /**
   * @param $order
   * @return \SagepayCustomerDetails
   */
  private function getBillingAddress(OrderInterface $order) {
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $billingAddress = $order->getBillingProfile()->get('address')->first();

    $billingDetails = [
      'BillingFirstnames' => $billingAddress->getGivenName(),
      'BillingSurname' => $billingAddress->getFamilyName(),
      'BillingAddress1' => $billingAddress->getAddressLine1(),
      'BillingAddress2' => $billingAddress->getAddressLine2(),
      'BillingCity' => $billingAddress->getLocality(),
      'BillingPostCode' => $billingAddress->getPostalCode(),
      'BillingCountry' => $billingAddress->getCountryCode(),
    ];

    return $this->createCustomerDetails($billingDetails, 'billing');
  }

  /**
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   * @return bool|\SagepayCustomerDetails
   */
  protected function getShippingAddress(ShipmentInterface $shipment) {

    if (!$shippingProfile = $shipment->getShippingProfile()) {
      return FALSE;
    }
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $shippingAddress = $shippingProfile->get('address')->first();
    $shippingDetails = [
      'DeliveryFirstnames' => $shippingAddress->getGivenName(),
      'DeliverySurname' => $shippingAddress->getFamilyName(),
      'DeliveryAddress1' => $shippingAddress->getAddressLine1(),
      'DeliveryAddress2' => $shippingAddress->getAddressLine2(),
      'DeliveryCity' => $shippingAddress->getLocality(),
      'DeliveryPostCode' => $shippingAddress->getPostalCode(),
      'DeliveryCountry' => $shippingAddress->getCountryCode(),
    ];

    return $this->createCustomerDetails($shippingDetails, 'delivery');
  }

  /**
   * Create and populate customer details.
   *
   * @param array $data
   * @param string $type
   * @return SagepayCustomerDetails
   */
  protected function createCustomerDetails($data, $type) {
    $customerdetails = new SagepayCustomerDetails();
    $keys = $this->getDefaultCustomerKeys($type);

    foreach ($keys as $key => $value) {
      if (isset($data[$key])) {
        $customerdetails->$value = $data[$key];
      }
      if (isset($data[ucfirst($key)])) {
        $customerdetails->$value = $data[ucfirst($key)];
      }
    }
    if ($type == 'billing' && isset($data['customerEmail'])) {
      $customerdetails->email = $data['customerEmail'];
    }
    return $customerdetails;
  }

  /**
   * Define default customer keys.
   *
   * @param string $type
   * @return string[]
   */
  protected function getDefaultCustomerKeys($type) {
    $result = array();
    $keys = array(
      'Firstnames' => 'firstname',
      'Surname' => 'lastname',
      'Address1' => 'address1',
      'Address2' => 'address2',
      'City' => 'city',
      'PostCode' => 'postcode',
      'Country' => 'country',
      'State' => 'state',
      'Phone' => 'phone',
    );

    foreach ($keys as $key => $value) {
      $result[$type . $key] = $value;
    }

    return $result;
  }

  /**
   * Get basket from products.
   *
   * @return SagepayBasket
   *    The sagepay basket object.
   */
  protected function getBasketFromProducts(OrderInterface $order) {
    $items = $order->getItems();
    $basket = FALSE;
    // Create basket from saved products.
    /** @var OrderItemInterface $item */
    foreach ($items as $item) {

      /** @var ProductVariationInterface $product */
      $product = $item->getPurchasedEntity();

      if ($basket === FALSE) {
        $basket = new SagepayBasket();
      }
      $basketItem = new SagepayItem();
      $basketItem->setDescription($item->label());
      $basketItem->setProductCode($product->id());
      $basketItem->setProductSku($product->getSku());
      $basketItem->setQuantity($item->getQuantity());
      $basketItem->setUnitNetAmount($item->getUnitPrice()->getNumber());
      $basket->addItem($basketItem);

    }
    return $basket;
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

}
