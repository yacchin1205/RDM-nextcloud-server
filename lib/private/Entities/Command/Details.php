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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Details
 *
 * @package OC\Entities\Command
 */
class Details extends Base {


	use TArrayTools;


	/** @var IEntitiesManager */
	private $entitiesManager;

	/** @var InputInterface */
	private $input;

	/** @var OutputInterface */
	private $output;

	/** @var bool */
	private $short;


	/**
	 * Details constructor.
	 *
	 * @param IEntitiesManager $entitiesManager
	 */
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
			 ->addOption('short', 's', InputOption::VALUE_NONE, 'short result')
			 ->setDescription('Details about an entity');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$this->output = $output;
		$this->input = $input;

		$this->short = $input->getOption('short');

		if (!$this->search()) {
			throw new Exception('no item were found with this id, please use entities:search');
		}
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return bool
	 */
	private function search(): bool {
		$itemId = $this->input->getArgument('entityId');

		try {
			$this->searchForEntity($itemId);

			return true;
		} catch (EntityNotFoundException $e) {
		}

		try {
			$this->searchForEntityAccount($itemId);

			return true;
		} catch (EntityAccountNotFoundException $e) {
		}

		try {
			$this->searchForEntityMember($itemId);

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
	private function searchForEntity(string $itemId): IEntity {

		$entity = $this->entitiesManager->getEntity($itemId);
		$this->outputEntity($entity);

		if (!$this->short) {
			$this->output('- Owner');
			if ($entity->getOwnerId() === '') {
				$this->output('  (no owner)');
			} else {
				$this->outputAccount($entity->getOwner(), '  ');
			}
		}

		$members = $entity->getMembers();
		$this->output('- getMembers (' . count($members) . ')');
		foreach ($members as $member) {
			$this->outputMember(
				$member, '  ',
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
	 *
	 * @return IEntityAccount
	 * @throws EntityAccountNotFoundException
	 */
	private function searchForEntityAccount(string $itemId): IEntityAccount {

		$account = $this->entitiesManager->getEntityAccount($itemId);
		$this->outputAccount($account);

		$belongsTo = $account->belongsTo();
		$this->output('- belongsTo (' . count($belongsTo) . ')');
		foreach ($belongsTo as $member) {
			$this->outputMember(
				$member, '  ', [
						   'account' => ($member->getAccountId() !== $account->getId())
					   ]
			);
		}

		return $account;
	}


	/**
	 * @param string $itemId
	 *
	 * @return IEntityMember
	 * @throws EntityMemberNotFoundException
	 */
	private function searchForEntityMember(string $itemId): IEntityMember {

		$member = $this->entitiesManager->getEntityMember($itemId);
		$this->outputMember($member);

		return $member;
	}


	/**
	 * @param IEntity $entity
	 * @param string $prefix
	 */
	private function outputEntity(IEntity $entity, $prefix = '') {
		$this->output($prefix . '- Entity Id: <info>' . $entity->getId() . '</info>');
		$this->output($prefix . '  - Type: <comment>' . $entity->getType() . '</comment>');
		$this->output($prefix . '  - Name: <comment>' . $entity->getName() . '</comment>');
		$this->output(
			$prefix . '  - Access: <info>' . $entity->getAccess() . '</info> ('
			. $entity->getAccessString() . ')', true
		);
		$this->output(
			$prefix . '  - Visibility: <info>' . $entity->getVisibility() . '</info> ('
			. $entity->getVisibilityString() . ')', true
		);

		$this->output($prefix . '  - Creation: <info>' . $entity->getCreation() . '</info>', true);
	}


	/**
	 * @param IEntityAccount $account
	 * @param string $prefix
	 */
	private function outputAccount(IEntityAccount $account, $prefix = '') {
		$this->output($prefix . '- Account Id: <info>' . $account->getId() . '</info>');
		$this->output($prefix . '  - Type: <comment>' . $account->getType() . '</comment>');
		$this->output(
			$prefix . '  - Account: <comment>' . $account->getAccount() . '</comment>'
		);
		$this->output($prefix . '  - Creation: <info>' . $account->getCreation() . '</info>', true);
	}


	/**
	 * @param IEntityMember $member
	 * @param string $prefix
	 * @param array $details
	 */
	private function outputMember(IEntityMember $member, string $prefix = '', array $details = []) {
		$this->output($prefix . '- Member Id: <info>' . $member->getId() . '</info>');

		if ($this->getBool('entity', $details, true)) {
			$this->outputEntity($member->getEntity(), $prefix . '  ');
		} else {
			$this->output(
				$prefix . '  - Entity Id: <info>' . $member->getEntityId() . '</info>', true
			);
		}

		if ($this->getBool('account', $details, true)) {
			$this->outputAccount($member->getAccount(), $prefix . '  ');
		} else {
			$this->output(
				$prefix . '  - Account Id: <info>' . $member->getAccountId() . '</info>', true
			);

		}

		$this->output(
			$prefix . '  - Slave Entity Id: <info>' . $member->getSlaveEntityId() . '</info>'
		);
		$this->output($prefix . '  - Status: <info>' . $member->getStatus() . '</info>', true);
		$this->output($prefix . '  - Level: <info>' . $member->getLevel() . '</info>', true);
		$this->output($prefix . '  - Creation: <info>' . $member->getCreation() . '</info>', true);
	}


	/**
	 * @param string $line
	 * @param bool $optional
	 */
	private function output(string $line, bool $optional = false) {
		if ($optional && $this->short) {
			return;
		}
		$this->output->writeln($line);
	}

}

