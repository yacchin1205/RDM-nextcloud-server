<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OC\Federation;

use OC\AppFramework\Http;
use OCP\App\IAppManager;
use OCP\Federation\Exceptions\ProviderDoesNotExistsException;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\ILogger;

/**
 * Class Manager
 *
 * Manage Cloud Federation Providers
 *
 * @package OC\Federation
 */
class CloudFederationProviderManager implements ICloudFederationProviderManager {

	/** @var array list of available cloud federation providers */
	private $cloudFederationProvider;

	/** @var IAppManager */
	private $appManager;

	/** @var IClientService */
	private $httpClientService;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/** @var ILogger */
	private $logger;

	private $supportedAPIVersion = '1.0-proposal1';

	/**
	 * CloudFederationProviderManager constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IClientService $httpClientService
	 * @param ICloudIdManager $cloudIdManager
	 * @param ILogger $logger
	 */
	public function __construct(IAppManager $appManager,
								IClientService $httpClientService,
								ICloudIdManager $cloudIdManager,
								ILogger $logger) {
		$this->cloudFederationProvider= [];
		$this->appManager = $appManager;
		$this->httpClientService = $httpClientService;
		$this->cloudIdManager = $cloudIdManager;
		$this->logger = $logger;
	}


	/**
	 * Registers an callback function which must return an cloud federation provider
	 *
	 * @param string $shareType which share type does the provider handles
	 * @param string $displayName user facing name of the federated share provider
	 * @param callable $callback
	 */
	public function addCloudFederationProvider($shareType, $displayName, callable $callback) {
		\OC::$server->getRemoteApiFactory();

		$this->cloudFederationProvider[$shareType] = [
			'shareType' => $shareType,
			'displayName' => $displayName,
			'callback' => $callback,
		];

	}

	/**
	 * remove cloud federation provider
	 *
	 * @param string $providerId
	 */
	public function removeCloudFederationProvider($providerId) {
		unset($this->cloudFederationProvider[$providerId]);
	}

	/**
	 * get a list of all cloudFederationProviders
	 *
	 * @return array [id => ['id' => $id, 'displayName' => $displayName, 'callback' => callback]]
	 */
	public function getAllCloudFederationProviders() {
		return $this->cloudFederationProvider;
	}

	/**
	 * get a specific cloud federation provider
	 *
	 * @param string $shareType
	 * @return ICloudFederationProvider
	 * @throws ProviderDoesNotExistsException
	 */
	public function getCloudFederationProvider($shareType) {
		if (isset($this->cloudFederationProvider[$shareType])) {
			return call_user_func($this->cloudFederationProvider[$shareType]['callback']);
		} else {
			throw new ProviderDoesNotExistsException($shareType);
		}
	}

	public function sendShare(ICloudFederationShare $share) {
		$cloudID = $this->cloudIdManager->resolveCloudId($share->getShareWith());
		$ocmEndPoint = $this->getOCMEndPoint($cloudID->getRemote());

		if (empty($ocmEndPoint)) {
			return false;
		}

		$client = $this->httpClientService->newClient();
		try {
			$response = $client->post($ocmEndPoint . '/shares', [
				'body' => $share->getShare(),
				'timeout' => 10,
				'connect_timeout' => 10,
			]);

			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				return true;
			}

		} catch (\Exception $e) {
			// if flat re-sharing is not supported by the remote server
			// we re-throw the exception and fall back to the old behaviour.
			// (flat re-shares has been introduced in Nextcloud 9.1)
			if ($e->getCode() === Http::STATUS_INTERNAL_SERVER_ERROR) {
				throw $e;
			}
		}

		return false;

	}

	/**
	 * @param string $url
	 * @param ICloudFederationNotification $notification
	 * @return mixed
	 */
	public function sendNotification($url, ICloudFederationNotification $notification) {
		$ocmEndPoint = $this->getOCMEndPoint($url);

		if (empty($ocmEndPoint)) {
			return false;
		}

		$client = $this->httpClientService->newClient();
		try {
			$response = $client->post($ocmEndPoint . '/notifications', [
				'body' => $notification->getMessage(),
				'timeout' => 10,
				'connect_timeout' => 10,
			]);
			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				$result = json_decode($response->getBody(), true);
				return (is_array($result)) ? $result : [];
			}
		} catch (\Exception $e) {
			// log the error and return false
			$this->logger->error('error while sending notification for federated share: ' . $e->getMessage());
		}

		return false;
	}

	/**
	 * check if the new cloud federation API is ready to be used
	 *
	 * @return bool
	 */
	public function isReady() {
		return $this->appManager->isEnabledForUser('cloud_federation_api', false);
	}
	/**
	 * check if server supports the new OCM api and ask for the correct end-point
	 *
	 * @param string $url full base URL of the cloud server
	 * @return string
	 */
	protected function getOCMEndPoint($url) {
		$client = $this->httpClientService->newClient();
		try {
			$response = $client->get($url . '/ocm-provider/', ['timeout' => 10, 'connect_timeout' => 10]);
		} catch (\Exception $e) {
			return '';
		}

		$result = $response->getBody();
		$result = json_decode($result, true);

		$supportedVersion = isset($result['apiVersion']) && $result['apiVersion'] === $this->supportedAPIVersion;

		if (isset($result['endPoint']) && $supportedVersion) {
			return $result['endPoint'];
		}

		return '';
	}


}
