<?php
namespace OCA\Files_External\Lib\Backend;


use \OCP\IL10N;
use \OCA\Files_External\Lib\DefinitionParameter;
use \OCA\Files_External\Service\BackendService;
use \OCA\Files_External\Lib\Auth\NullMechanism;
use \OCA\Files_External\Lib\Auth\OSF\PersonalAccessToken;

class OSF extends Backend {
	public function __construct(IL10N $l, NullMechanism $legacyAuth) {
		$this
			->setIdentifier('osf')
			->addIdentifierAlias('\OC\Files\Storage\OSF') // legacy compat
			->setStorageClass('\OCA\Files_External\Lib\Storage\OSF')
			->setText($l->t('Open Science Framework'))
			->addParameters([
				(new DefinitionParameter('serviceurl', $l->t('Service URL'))),
			])
			->setPriority(BackendService::PRIORITY_DEFAULT + 50)
			->addAuthScheme(PersonalAccessToken::SCHEME_OSF_PERSONALACCESSTOKEN)
			->setLegacyAuthMechanism($legacyAuth)
		;
	}
}
