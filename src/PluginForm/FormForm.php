<?php

namespace Drupal\commerce_sagepay\PluginForm;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_sagepay\Plugin\Commerce\PaymentGateway\FormInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use SagepayBasket;
use SagepayCustomerDetails;
use SagepayItem;
use SagepayApiFactory;
use SagepaySettings;

/**
 * Class FormForm.
 */
class FormForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var PaymentInterface $payment */
    $payment = $this->entity;

    /** @var OrderInterface $order */
    $order = $payment->getOrder();

    /** @var FormInterface $paymentGatewayPlugin */
    $paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();

    $gatewayConfig = $paymentGatewayPlugin->getConfiguration();

    $sagepayConfig = SagepaySettings::getInstance([
      'env' => ($gatewayConfig['mode']) ? $gatewayConfig['mode'] : SAGEPAY_ENV_TEST,
      'vendorName' => $gatewayConfig['vendor'],
      'website' => 'http://www.google.com',
      'formPassword' => [
        SAGEPAY_ENV_LIVE => $gatewayConfig['enc_key'],
        SAGEPAY_ENV_TEST => $gatewayConfig['test_enc_key'],
      ],
      'siteFqdns' => ['test' => 'http://commerce.dd:8083'],
//      'formSuccessUrl' => $form['#return_url'],
      'formSuccessUrl' => $this->buildReturnUrl($order),
//      'formFailureUrl' => $form['#cancel_url'],
      'formFailureUrl' => $this->buildCancelUrl($order),
      'surcharges' => [],
      'allowGiftAid' => 0,
    ]);

    /** @var \SagepayFormApi $api */
    $sagepayFormApi = SagepayApiFactory::create('form', $sagepayConfig);

    $redirectUrl = $paymentGatewayPlugin->getUrl();

    if (!$basket = $this->getBasketFromProducts($order)) {
      die('no basket');
    }

    $basket->setDescription($gatewayConfig['sagepay_order_description']);

    $sagepayFormApi->setBasket($basket);

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

    $address1 = $this->createCustomerDetails($billingDetails, 'billing');
    $sagepayFormApi->addAddress($address1);



    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('commerce_shipping')) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
      $shipments = $order->get('shipments')->referencedEntities();
      /** @var ShipmentInterface $shipment */
      $delivery = 0;
      if (!empty(($shipments))) {

        $first_shipment = reset($shipments);
        if ($shippingProfile = $first_shipment->getShippingProfile()) {
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

          $address2 = $this->createCustomerDetails($shippingDetails, 'delivery');
          $sagepayFormApi->addAddress($address2);
        }

        foreach ($shipments as $shipment) {
          $delivery = $delivery + (float) $shipment->getAmount()->getNumber();
        }
      }
      $basket->setDeliveryNetAmount($delivery);
//    $basket->setDeliveryTaxAmount(0.05);
    }

    $request = $sagepayFormApi->createRequest();

    $order->setData('sagepay_form', [
      'request' => $request,
    ]);
    $order->save();

    foreach ($request as $name => $value) {
      if (!empty($value)) {
        $form[$name] = array('#type' => 'hidden', '#value' => $value);
      }
    }

    return $this->buildRedirectForm($form, $form_state, $redirectUrl, $request, BasePaymentOffsiteForm::REDIRECT_POST);
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

    // Check if we need to encode cart.
//    if (isset($gatewayConfig['sagepay_send_basket_contents']) && $gatewayConfig['sagepay_send_basket_contents'] == '1') {
      $items = $order->getItems();
//    }

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
//      $basketItem->setUnitTaxAmount($item->getTotalPrice()->getNumber());
      $basketItem->setQuantity($item->getQuantity());
      $basketItem->setUnitNetAmount($item->getUnitPrice()->getNumber());
      $basket->addItem($basketItem);

    }
    return $basket;
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
