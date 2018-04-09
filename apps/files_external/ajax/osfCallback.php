<?php

use OCA\Files_External\Lib\CASOAuthClient;

OCP\JSON::checkAppEnabled('files_external');
OCP\JSON::checkLoggedIn();

$l = \OC::$server->getL10N('files_external');
$config = \OC::$server->getConfig();
$activityManager = \OC::$server->getActivityManager();
$session = \OC::$server->getSession();
$lockingProvider = \OC::$server->getLockingProvider();

$client = new CASOAuthClient($config, $session, $lockingProvider,
                             $activityManager->getCurrentUserId());
$client->client->handleCallback($_GET);

\OCP\Util::writeLog('external_storage', "CASOAuthClient.handleCallback", \OCP\Util::INFO);

$nextcloud_base_url = $config->getAppValue('files_external', 'osf_nextcloud_base_url', '');
if($nextcloud_base_url == '') {
  throw new \Exception('nextcloud_base_url is not set');
}

http_response_code(302);
header(sprintf('Location: %s', $nextcloud_base_url.'/settings/personal#external-storage'));
