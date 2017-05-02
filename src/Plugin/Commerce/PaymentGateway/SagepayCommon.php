<?php

namespace Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use SagepayBasket;
use SagepayCustomerDetails;
use SagepayItem;

/**
 * Trait SagepayCommon.
 */
trait SagepayCommon {

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

}
