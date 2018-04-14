<?php

namespace OCA\Files_External\Lib;

use fkooman\OAuth\Client\SessionInterface;
use fkooman\OAuth\Client\Exception\SessionException;

class DummySession implements SessionInterface
{

    private $session;

    public function __construct()
    {
      $this->session = array();
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function set($key, $value)
    {
        $session[$key] = $value;
    }

    /**
     * Get value, delete key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function take($key)
    {
        if (!array_key_exists($key, $session)) {
            throw new SessionException(sprintf('key "%s" not found in session', $key));
        }
        $value = $session[$key];
        unset($session[$key]);

        return $value;
    }
}
