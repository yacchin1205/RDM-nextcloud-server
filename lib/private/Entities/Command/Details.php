<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
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


namespace OC\Entities\Command;


use daita\NcSmallPhpTools\Traits\TArrayTools;
use Exception;
use OC\Core\Command\Base;
use OC\Entities\Exceptions\EntityAccountNotFoundException;
use OC\Entities\Exceptions\EntityMemberNotFoundException;
use OC\Entities\Exceptions\EntityNotFoundException;
use OCP\Entities\IEntitiesManager;
use OCP\Entities\Model\IEntity;
use OCP\Entities\Model\IEntityAccount;
use OCP\Entities\Model\IEntityMember;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class Details extends Base {


	use TArrayTools;


	/** @var IEntitiesManager */
	private $entitiesManager;


	public function __construct(IEntitiesManager $entitiesManager) {
		parent::__construct();

		$this->entitiesManager = $entitiesManager;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();
		$this->setName('entities:details')
			 ->addArgument('entityId', InputArgument::REQUIRED, 'entity Id')
			 ->setDescription('Details about an entity');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$this->search($input, $output)) {
			throw new Exception('no item were found with this id, please use entities:search');
		}
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return bool
	 */
	private function search(InputInterface $input, OutputInterface $output): bool {

		$itemId = $input->getArgument('entityId');

		try {
			$this->searchForEntity($itemId, $output);

			return true;
		} catch (EntityNotFoundException $e) {
		}

		try {
			$this->searchForEntityAccount($itemId, $output);

			return true;
		} catch (EntityAccountNotFoundException $e) {
		}

		try {
			$this->searchForEntityMember($itemId, $output);

			return true;
		} catch (EntityMemberNotFoundException $e) {
		}

		return false;
	}


	/**
	 * @param string $itemId
	 * @param OutputInterface $output
	 *
	 * @return IEntity
	 * @throws EntityNotFoundException
	 */
	private function searchForEntity(string $itemId, OutputInterface $output): IEntity {

		$entity = $this->entitiesManager->getEntity($itemId);

		$this->outputEntity($output, $entity);

		$output->writeln('- Owner');
		if ($entity->getOwnerId() === '') {
			$output->writeln('  (no owner)');
		} else {
			$this->outputAccount($output, $entity->getOwner(), '  ');
		}

		$members = $entity->getMembers();
		$output->writeln('- getMembers (' . count($members) . ')');
		foreach ($members as $member) {
			$this->outputMember(
				$output, $member, '  ',
				[
					'entity'  => ($member->getEntityId() !== $entity->getId()),
					'account' => ($member->getAccountId() !== $entity->getOwnerId()),
				]
			);
		}

		return $entity;
	}


	/**
	 * @param string $itemId
	 * @param OutputInterface $output
	 *
	 * @return IEntityAccount
	 * @throws EntityAccountNotFoundException
	 */
	private function searchForEntityAccount(string $itemId, OutputInterface $output
	): IEntityAccount {

		$account = $this->entitiesManager->getEntityAccount($itemId);
		$this->outputAccount($output, $account);

		$belongsTo = $account->belongsTo();
		$output->writeln('- belongsTo (' . count($belongsTo) . ')');
		foreach ($belongsTo as $member) {
			$this->outputMember(
				$output, $member, '  ', [
						   'account' => ($member->getAccountId() !== $account->getId())
					   ]
			);
		}

		return $account;
	}




	/**
	 * @param string $itemId
	 * @param OutputInterface $output
	 *
	 * @return IEntityMember
	 * @throws EntityMemberNotFoundException
	 */
	private function searchForEntityMember(string $itemId, OutputInterface $output
	): IEntityMember {

		$member = $this->entitiesManager->getEntityMember($itemId);
		$this->outputMember($output, $member);

//		$belongsTo = $account->belongsTo();
//		$output->writeln('- belongsTo (' . count($belongsTo) . ')');
//		foreach ($belongsTo as $member) {
//			$this->outputMember(
//				$output, $member, '  ', [
//						   'account' => ($member->getAccountId() !== $account->getId())
//					   ]
//			);
//		}
//
		return $member;
	}




	/**
	 * @param OutputInterface $output
	 * @param IEntity $entity
	 * @param string $prefix
	 */
	private function outputEntity(OutputInterface $output, IEntity $entity, $prefix = '') {
		$output->writeln($prefix . '- Entity Id: <info>' . $entity->getId() . '</info>');
		$output->writeln($prefix . '  - Type: <info>' . $entity->getType() . '</info>');
		$output->writeln($prefix . '  - Name: <info>' . $entity->getName() . '</info>');
		$output->writeln(
			$prefix . '  - Access: <info>' . $entity->getAccess() . '</info> ('
			. $entity->getAccessString() . ')'
		);
		$output->writeln(
			$prefix . '  - Visibility: <info>' . $entity->getVisibility() . '</info> ('
			. $entity->getVisibilityString() . ')'
		);
		$output->writeln($prefix . '  - Creation: <info>' . $entity->getCreation() . '</info>');
	}


	/**
	 * @param OutputInterface $output
	 * @param IEntityAccount $account
	 * @param string $prefix
	 */
	private function outputAccount(OutputInterface $output, IEntityAccount $account, $prefix = '') {
		$output->writeln($prefix . '- Account Id: <info>' . $account->getId() . '</info>');
		$output->writeln($prefix . '  - Type: <info>' . $account->getType() . '</info>');
		$output->writeln($prefix . '  - Account: <info>' . $account->getAccount() . '</info>');
		$output->writeln($prefix . '  - Creation: <info>' . $account->getCreation() . '</info>');
	}


	/**
	 * @param OutputInterface $output
	 * @param IEntityMember $member
	 * @param string $prefix
	 * @param array $details
	 */
	private function outputMember(
		OutputInterface $output, IEntityMember $member, string $prefix = '', array $details = []
	) {
		$output->writeln($prefix . '- Member Id: <info>' . $member->getId() . '</info>');

		if ($this->getBool('entity', $details, true)) {
			$this->outputEntity($output, $member->getEntity(), $prefix . '  ');
		} else {
			$output->writeln(
				$prefix . '  - Entity Id: <info>' . $member->getEntityId() . '</info>'
			);
		}

		if ($this->getBool('account', $details, true)) {
			$this->outputAccount($output, $member->getAccount(), $prefix . '  ');
		} else {
			$output->writeln(
				$prefix . '  - Account Id: <info>' . $member->getAccountId() . '</info>'
			);

		}

		$output->writeln(
			$prefix . '  - Slave Entity Id: <info>' . $member->getSlaveEntityId() . '</info>'
		);
		$output->writeln($prefix . '  - Status: <info>' . $member->getStatus() . '</info>');
		$output->writeln($prefix . '  - Level: <info>' . $member->getLevel() . '</info>');
		$output->writeln($prefix . '  - Creation: <info>' . $member->getCreation() . '</info>');
	}


}

