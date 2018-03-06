<?php

namespace OCA\Files_External\Lib;

use GuzzleHttp\Client;
use OCP\Files\ObjectStore\IObjectStore;


class WaterButler implements IObjectStore {
	private $wbUrl;
	private $nodeId;
	private $providerId;
	private $token;
	private $id;
	private $baseUrl;
	private $client;
	private $requestOptions;

	public function __construct($wbUrl, $nodeId, $providerId, $token) {
		$this->wbUrl = $wbUrl;
		$this->nodeId = $nodeId;
		$this->providerId = $providerId;
		$this->token = $token;
		$this->id = "waterbutler::$this->nodeId/$this->providerId";
		$this->baseUrl = str_replace('//', '/', "$wbUrl/v1/resources/$nodeId/providers/$providerId/");
		$this->client = new Client(['base_uri' => $this->baseUrl]);
		$this->requestOptions = [
			'headers' => [
				'Authorization' => "Bearer $token"
			]
		];
	}

	/**
	 * @return string the container or bucket name where objects are stored
	 * @since 7.0.0
	 */
	public function getStorageId() {
		return $this->id;
	}

	/**
	 * @param string $path the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	public function readObject($path) {
		$path = $this->normalizePath($path);
		$res = $this->request('GET', $path);
		return $res->getBody();
	}

	public function headObject($path) {
		$path = $this->normalizePath($path);
		$res = $this->request('GET', "$path?meta=");
		$body = json_decode($res->getBody(), true);
		return $body['data'];
	}

	// return [file]
	public function getList($path = '') {
		$path = $this->normalizePath($path);
		$res = $this->request('GET', "$path?meta=");
		$body = json_decode($res->getBody(), true);
		return $body['data'];
	}

	/**
	 * @param string $path the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	public function writeObject($path, $stream) {
		$path = $this->normalizePath($path);
		// TODO: exists?
		// TODO: name?
		$this->request('PUT', "$path?kind=file&name=test.png", ['body' => $stream]);
	}

	/**
	 * @param string $path the unified resource name used to identify the object
	 * @return void
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	public function deleteObject($path) {
		$path = $this->normalizePath($path);
		$this->request('DELETE', $path);
	}

	protected function request($method, $path, array $options = []) {
		return $this->client->request($method, $path, array_merge($options, $this->requestOptions));
	}

	protected function normalizePath($path) {
		return ltrim($path, '/') ?: './';
	}
}
