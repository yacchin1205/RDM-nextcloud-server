<?php

namespace OCA\Files_External\Lib;

use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\Session;
use fkooman\OAuth\Client\TokenStorageInterface;

class CASSessionTokenStorage implements TokenStorageInterface
{
    private $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getAccessTokenList($userId)
    {
        if (!$this->session->exists(sprintf('osf_oauth_token_%s', $userId))) {
            return [];
        }

        return [AccessToken::fromJson($this->session->get(sprintf('osf_oauth_token_%s', $userId)))];
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function storeAccessToken($userId, AccessToken $accessToken)
    {
        $this->session->set(sprintf('osf_oauth_token_%s', $userId), $accessToken->toJson());
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
                    $this->session->remove(sprintf('osf_oauth_token_%s', $userId));
                }
            }
        }
    }

}
