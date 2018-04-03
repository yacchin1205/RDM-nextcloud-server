<?php

use OCA\Files_External\Lib\CASOAuthClient;

OCP\JSON::checkAppEnabled('files_external');
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();

$l = \OC::$server->getL10N('files_external');
$config = \OC::$server->getConfig();
$activityManager = \OC::$server->getActivityManager();
$session = \OC::$server->getSession();

$serviceurl = $config->getAppValue('files_external', 'osf_serviceurl', '');
if($serviceurl != '') {
	$client = new CASOAuthClient($config, $session, $activityManager->getCurrentUserId());
	$authorized = $client->isAuthorized();
	$authorize_uri = null;
	$token = null;
	if($authorized) {
		$token = $client->getAccessToken()->toJson();
	}else{
		$authorize_uri = $client->getAuthorizeUri();
	}

  OCP\JSON::success(array('serviceurl' => $serviceurl, 'authorize_uri' => $authorize_uri,
                          'authorized' => $authorized, 'token' => $token));
} else {
	OCP\JSON::error(array('data' => array('message' => $l->t('Error configuring OSF'))));
}
