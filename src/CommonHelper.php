<?php

namespace Drupal\commerce_sagepay;

class CommonHelper {
  /**
   * Generates an internal or external URL
   *
   * @param array|string $query
   * @param string $siteFqdn
   *
   * @return string Description
   */
  static public function url($query, $siteFqdn = '')
  {
    if (is_array($query))
    {
      $query = implode('/', array_values($query));
    }

    $base = BASE_PATH;
    if (!empty($siteFqdn))
    {
      $base = $siteFqdn;
    }
    return $base . $query;
  }

  /**
   * Add a value into session
   *
   * @param string $key
   * @param mixed $value
   */
  static public function setStore($key, $value)
  {
    if (gettype($value) == "object")
    {
      $_SESSION[$key] = serialize($value);
    }
    else
    {
      $_SESSION[$key] = $value;
    }
  }

  /**
   * Get a value from session by key
   *
   * @param string $key
   * @param string $subKey
   * @return mixed
   */
  static public function getStore($key, $subKey = null)
  {
    if (isset($_SESSION[$key]))
    {
      if ($subKey !== null)
      {
        if (isset($_SESSION[$key][$subKey]))
        {
          return $_SESSION[$key][$subKey];
        }
      }
      else
      {
        return $_SESSION[$key];
      }
    }

    return false;
  }

  /**
   * Removes a value from session by key or unset all session if the key is NULL
   *
   * @param array|string $key
   */
  static public function clearStore($key = null)
  {
    if ($key === null)
    {
      foreach (array_keys($_SESSION) as $value)
      {
        unset($_SESSION[$value]);
      }
    }
    else if (is_array($key))
    {
      foreach ($key as $id)
      {
        unset($_SESSION[$id]);
      }
    }
    else
    {
      unset($_SESSION[$key]);
    }
  }

}
