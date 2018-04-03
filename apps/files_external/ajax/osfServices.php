<?php

use OCA\Files_External\Lib\CASOAuthClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

OCP\JSON::checkAppEnabled('files_external');
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();

$l = \OC::$server->getL10N('files_external');
$config = \OC::$server->getConfig();
$activityManager = \OC::$server->getActivityManager();
$session = \OC::$server->getSession();

$api_uri = $config->getAppValue('files_external', 'osf_api_uri', '');
if($api_uri != '') {
	$client = new CASOAuthClient($config, $session, $activityManager->getCurrentUserId());
	$accessToken = $client->getAccessToken();
	$authorize_uri = null;
	$token = null;
	$authorized = false;
	$nodes = null;
	if($accessToken != null) {
		$httpClient = $client->createClient($api_uri);
		$req = $httpClient->createRequest('GET', '/v2/nodes/');
		try {
			$resp = $client->send($httpClient, $req);
			$data = json_decode($resp->getBody(), true)['data'];
			$nodes = [];
			foreach($data as $node) {
				$nodeobj = ['id' => $node['id'], 'title' => $node['attributes']['title']];

				$req = $httpClient->createRequest('GET', '/v2/nodes/'.$nodeobj['id'].'/files/');
				$resp = $client->send($httpClient, $req);
				$files = json_decode($resp->getBody(), true)['data'];
				$providers = [];
				foreach($files as $provider) {
					$waterbutler_uri = $provider['links']['upload'];
					$waterbutler_uri = substr($waterbutler_uri, 0, strpos($waterbutler_uri, '/v1/resources/'));
					$providers[] = ['provider' => $provider['attributes']['provider'],
				                  'base_uri' => $waterbutler_uri];
				}
				$nodeobj['providers'] = $providers;
				$nodes[] = $nodeobj;
			}
			$token = $accessToken->toJson();
			$authorized = true;
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === 401) {
				\OCP\Util::writeLog('external_storage', "Unauthorized: OSF API", \OCP\Util::WARN);
				$authorized = false;
			} else {
				throw $e;
			}
		}
	}
	if(! $authorized){
		$authorize_uri = $client->getAuthorizeUri();
	}

  OCP\JSON::success(array('nodes' => $nodes, 'authorize_uri' => $authorize_uri,
                          'authorized' => $authorized, 'token' => $token));
} else {
	OCP\JSON::error(array('data' => array('message' => $l->t('Error configuring OSF'))));
}
