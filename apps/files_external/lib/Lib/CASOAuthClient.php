<?php

namespace OCA\Files_External\Lib;

use fkooman\OAuth\Client\Exception\TokenException;
use fkooman\OAuth\Client\Http\CurlHttpClient;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\Client\SessionTokenStorage;

class CASOAuthClient {
	public $client;

	private $resource_url;

	private $nextcloud_base_url;

	private $requestScope;

	public function __construct($config, $userId) {
		$this->requestScope = 'osf.full_read osf.full_write';
		$this->client = new OAuthClient(
	        // for DEMO purposes we store the AccessToken in the user session
	        // data...
	        new SessionTokenStorage(),
	        // for DEMO purposes we also allow connecting to HTTP URLs, do **NOT**
	        // do this in production
	        new CurlHttpClient(['allowHttp' => true])
	    );
		$client_id = $config->getAppValue('files_external', 'osf_oauth_client_id', '');
		$client_secret = $config->getAppValue('files_external', 'osf_oauth_client_secret', '');
		$oauth_token_url = $config->getAppValue('files_external', 'osf_oauth_token_url', '');
		$oauth_authorize_url = $config->getAppValue('files_external', 'osf_oauth_authorize_url', '');
		if($client_id == '' || $client_secret == '' || $oauth_token_url == '' || $oauth_authorize_url == '') {
			throw new \Exception('Credentials are not set');
		}
		$this->resource_url = $config->getAppValue('files_external', 'osf_serviceurl', '');
		if($this->resource_url == '') {
			throw new \Exception('serviceurl is not set');
		}
		$this->nextcloud_base_url = $config->getAppValue('files_external', 'osf_nextcloud_base_url', '');
		if($this->nextcloud_base_url == '') {
			throw new \Exception('nextcloud_base_url is not set');
		}

		$this->client->setProvider(
	        new Provider(
	            $client_id, $client_secret, $oauth_authorize_url, $oauth_token_url
	        )
	    );
		$this->client->setUserId($userId);

		\OCP\Util::writeLog('external_storage', "CASOAuthClient($userId)", \OCP\Util::INFO);
	}

	public function isAuthorized() {
		return $this->client->get($this->requestScope, $this->resource_url) !== false;
	}

	public function getAuthorizeUri() {
		return $this->client->getAuthorizeUri($this->requestScope,
		         $this->nextcloud_base_url.'/index.php/apps/files_external/ajax/osfCallback.php');
	}

}
