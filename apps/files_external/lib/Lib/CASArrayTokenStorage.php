<?php

namespace OCA\Files_External\Lib;

use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\Session;
use fkooman\OAuth\Client\TokenStorageInterface;

class CASArrayTokenStorage implements TokenStorageInterface
{
    public $dirty;

    public $map;

    public function __construct($map)
    {
        $this->dirty = false;
        $this->map = $map;
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getAccessTokenList($userId)
    {
        if (!array_key_exists('token', $this->map)) {
            return [];
        }

        return [AccessToken::fromJson($this->map['token'])];
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function storeAccessToken($userId, AccessToken $accessToken)
    {
        $this->map['token'] = $accessToken->toJson();
        $this->dirty = true;
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function deleteAccessToken($userId, AccessToken $accessToken)
    {
        foreach ($this->getAccessTokenList($userId) as $k => $v) {
            if ($accessToken->getProviderId() === $v->getProviderId()) {
                if ($accessToken->getToken() === $v->getToken()) {
                    $this->map['token'] = null;
                }
            }
        }
    }

}
