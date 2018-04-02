<?php

OCP\JSON::checkAppEnabled('files_external');
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();

$l = \OC::$server->getL10N('files_external');
$config = \OC::$server->getConfig();
$userSession = \OC::$server->getUserSession();

$serviceurl = $config->getAppValue('files_external', 'osf_serviceurl', '');
if($serviceurl != '') {
	$provider = new \League\OAuth2\Client\Provider\GenericProvider([
	    'clientId'                => 'demoapp',    // The client ID assigned to you by the provider
	    'clientSecret'            => 'demopass',   // The client password assigned to you by the provider
	    'redirectUri'             => 'http://example.com/your-redirect-url/',
	    'urlAuthorize'            => 'http://brentertainment.com/oauth2/lockdin/authorize',
	    'urlAccessToken'          => 'http://brentertainment.com/oauth2/lockdin/token',
	    'urlResourceOwnerDetails' => 'http://brentertainment.com/oauth2/lockdin/resource'
	]);

  OCP\JSON::success(array('serviceurl' => $serviceurl));
} else {
	OCP\JSON::error(array('data' => array('message' => $l->t('Error configuring OSF'))));
}
