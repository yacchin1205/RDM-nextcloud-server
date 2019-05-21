<?php
declare(strict_types=1);


/**
 * Entities - Entity & Groups of Entities
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
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


namespace OC\Entities\Helper;


use OC\Entities\Classes\IEntitiesAccounts\LocalUser;
use OC\Entities\Classes\IEntities\Account;
use OC\Entities\Classes\IEntities\AdminGroup;
use OC\Entities\Classes\IEntities\Group;
use OC\Entities\Classes\IEntities\User;
use OC\Entities\Classes\IEntitiesAccounts\MailAddress;
use OC\Entities\Db\EntitiesAccountsRequest;
use OC\Entities\Db\EntitiesMembersRequest;
use OC\Entities\Db\EntitiesRequest;
use OC\Entities\Db\EntitiesTypesRequest;
use OC\Entities\Exceptions\EntityAccountAlreadyExistsException;
use OC\Entities\Exceptions\EntityAccountCreationException;
use OC\Entities\Exceptions\EntityAccountNotFoundException;
use OC\Entities\Exceptions\EntityAlreadyExistsException;
use OC\Entities\Exceptions\EntityCreationException;
use OC\Entities\Exceptions\EntityMemberAlreadyExistsException;
use OC\Entities\Exceptions\EntityNotFoundException;
use OC\Entities\Model\Entity;
use OC\Entities\Model\EntityAccount;
use OC\Entities\Model\EntityMember;
use OC\Entities\Model\EntityType;
use OCP\Entities\Helper\IEntitiesHelper;
use OCP\Entities\IEntitiesManager;
use OCP\Entities\Implementation\IEntities\IEntities;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccounts;
use OCP\Entities\Model\IEntity;
use OCP\Entities\Model\IEntityMember;


/**
 * Class EntitiesManager
 *
 * @package OCP\Entities\Helper
 */
class EntitiesHelper implements IEntitiesHelper {


	/** @var IEntitiesManager */
	private $entitiesManager;

	/** @var EntitiesRequest */
	private $entitiesRequest;

	/** @var EntitiesAccountsRequest */
	private $entitiesAccountsRequest;

	/** @var EntitiesMembersRequest */
	private $entitiesMembersRequest;

	/** @var EntitiesTypesRequest */
	private $entitiesTypesRequest;


	/**
	 * EntitiesHelper constructor.
	 *
	 * @param IEntitiesManager $entitiesManager
	 * @param EntitiesRequest $entitiesRequest
	 * @param EntitiesAccountsRequest $entitiesAccountsRequest
	 * @param EntitiesMembersRequest $entitiesMembersRequest
	 * @param EntitiesTypesRequest $entitiesTypesRequest
	 */
	public function __construct(
		IEntitiesManager $entitiesManager,
		EntitiesRequest $entitiesRequest,
		EntitiesAccountsRequest $entitiesAccountsRequest,
		EntitiesMembersRequest $entitiesMembersRequest,
		EntitiesTypesRequest $entitiesTypesRequest
	) {
		$this->entitiesManager = $entitiesManager;
		$this->entitiesRequest = $entitiesRequest;
		$this->entitiesAccountsRequest = $entitiesAccountsRequest;
		$this->entitiesMembersRequest = $entitiesMembersRequest;
		$this->entitiesTypesRequest = $entitiesTypesRequest;
	}


	/**
	 * @param string $userId
	 *
	 * @return IEntity
	 * @throws EntityAccountCreationException
	 * @throws EntityCreationException
	 * @throws EntityAlreadyExistsException
	 * @throws EntityAccountAlreadyExistsException
	 * @throws EntityMemberAlreadyExistsException
	 */
	public function createLocalUser(string $userId): IEntity {

		$account = new EntityAccount();
		$account->setType(LocalUser::TYPE);
		$account->setAccount($userId);

		$this->entitiesManager->saveAccount($account);

		$entity = new Entity();
		$entity->setVisibility(IEntity::VISIBILITY_NONE);
		$entity->setAccess(IEntity::ACCESS_LIMITED);

		$entity->setOwnerId($account->getId());
		$entity->setType(User::TYPE);
		$entity->setName($userId);
		$this->entitiesManager->saveEntity($entity);

		$member = new EntityMember();
		$member->setEntityId($entity->getId());
		$member->setAccountId($account->getId());
		$member->setStatus(IEntityMember::STATUS_MEMBER);
		$member->setLevel(IEntityMember::LEVEL_OWNER);
		$this->entitiesManager->saveMember($member);

		$entity->addMember($member);

		return $entity;
	}


	/**
	 * @param string $entityId
	 * @param string $userId
	 * @param int $level
	 * @param string $status
	 *
	 * @return IEntityMember
	 * @throws EntityAccountNotFoundException
	 * @throws EntityNotFoundException
	 * @throws EntityMemberAlreadyExistsException
	 */
	public function addLocalMember(
		string $entityId, string $userId, int $level = IEntityMember::LEVEL_MEMBER,
		string $status = ''
	): IEntityMember {

		$entity = $this->entitiesManager->getEntity($entityId);
		$account = $this->entitiesManager->getLocalAccount($userId);

		$entityMember = new EntityMember();
		$entityMember->setEntityId($entity->getId());
		$entityMember->setAccountId($account->getId());
		$entityMember->setLevel($level);

		$entityMember->setAccount($account);

		$this->entitiesManager->saveMember($entityMember);

		return $entityMember;
	}


	public function inviteLocalMember(string $entityId, string $userId): IEntityMember {
	}

	public function addVirtualMember(string $entityId, string $type, string $account
	): IEntityMember {
	}


	/**
	 *
	 */
	public function refreshInstall(): void {
		$this->entitiesRequest->clearAll();
		$this->entitiesAccountsRequest->clearAll();
		$this->entitiesMembersRequest->clearAll();
		$this->entitiesTypesRequest->clearAll();

		$entityTypes = [
			new EntityType(IEntities::INTERFACE, User::TYPE, User::class),
			new EntityType(IEntities::INTERFACE, Account::TYPE, Account::class),
			new EntityType(IEntities::INTERFACE, Group::TYPE, Group::class),
			new EntityType(IEntities::INTERFACE, AdminGroup::TYPE, AdminGroup::class),

			new EntityType(IEntitiesAccounts::INTERFACE, LocalUser::TYPE, LocalUser::class),
			new EntityType('IEntitiesAccounts', MailAddress::TYPE, MailAddress::class)
		];

		foreach ($entityTypes as $entityType) {
			$this->entitiesTypesRequest->create($entityType);
		}
	}


}

