<?php

namespace OCA\Files_External\Lib\Auth\OSF;

use \OCP\IL10N;
use \OCA\Files_External\Lib\DefinitionParameter;
use \OCA\Files_External\Lib\Auth\AuthMechanism;

/**
 * personal access token authentication of osf.io
 */
class PersonalAccessToken extends AuthMechanism {
	const SCHEME_OSF_PERSONALACCESSTOKEN = 'osf_personalaccesstoken';

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('osf::personalaccesstoken')
			->setScheme(self::SCHEME_OSF_PERSONALACCESSTOKEN)
			->setText($l->t('Personal access token'))
			->addParameters([
				(new DefinitionParameter('token', $l->t('Token')))
					->setType(DefinitionParameter::VALUE_PASSWORD),
			]);
	}
}
