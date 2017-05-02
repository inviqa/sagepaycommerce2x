CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation


INTRODUCTION
------------

This module allows Drupal Commerce customers to pay using the Sagepay Form integration.
https://www.sagepay.co.uk/support/12/36/sage-pay-form


REQUIREMENTS
------------
This module requires the following:
* Submodules of Drupal Commerce package (https://drupal.org/project/commerce)
  - Commerce core,
  - Commerce Payment (and its dependencies);
* Sagepay Merchant account (https://applications.sagepay.com/apply).

INSTALLATION
------------
* This module needs to be installed via Composer, which will download
  the required libraries.
  composer require "drupal/commerce_sagepay"
  https://www.drupal.org/docs/8/extending-drupal-8/installing-modules-composer-dependencies
* Enable the module under /admin/modules within the group 'Commerce (contrib)'.
* Add a new Payment gateway under 'admin/commerce/config/payment-gateways'
  - You will need your Sagepay Vendor Name and encryption keys for your Test and Live environments.
