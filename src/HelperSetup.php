<?php

namespace Drupal\commerce_sagepay;

use Exception;

/**
 * Class HelperSetup.
 */
class HelperSetup {

  private static function checkPhpExtensionIsLoaded($name) {
    if (!extension_loaded($name)) {
      throw new SetupException("$name not loaded.");
    }
  }

  public static function checkDependencies() {
    HelperSetup::checkPhpExtensionIsLoaded('mcrypt');
    HelperSetup::checkPhpExtensionIsLoaded('curl');
  }

}

class SetupException extends Exception {

}
