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


use Exception;
use OC\Core\Command\Base;
use OC\Entities\Classes\IEntities\User;
use OC\Entities\Exceptions\EntityAccountNotFoundException;
use OC\Entities\Exceptions\EntityNotFoundException;
use OCP\Entities\IEntitiesManager;
use OCP\Entities\Model\IEntity;
use OCP\Entities\Model\IEntityAccount;
use OCP\Entities\Model\IEntityMember;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class Details extends Base {


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
			$entity = $this->searchForEntity($itemId, $output);

//			if ($entity->getType() === User::TYPE) {
//				try {
//					$this->searchForEntityAccount($entity->getOwnerId(), $output);
//				} catch (EntityAccountNotFoundException $e) {
//				}
//			} else {
//				$output->writeln('### Owner');
//				$this->outputAccount($output, $entity->getOwner());
//				$output->writeln('');
//			}

			$output->writeln('### Members');



			return true;
		} catch (EntityNotFoundException $e) {
		}


		try {
			$this->searchForEntityAccount($itemId, $output);

			return true;
		} catch (EntityAccountNotFoundException $e) {
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
		$this->outputAccount($output, $entity->getOwner(), '  ');

		$output->writeln('- Members');
		$members = $entity->getMembers();
		foreach($members as $member) {
			echo '--- ' . json_encode($member) . "\n";
		}

		$this->outputAccount($output, $entity->getOwner(), '  ');

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
		$output->writeln('');

		$belongsTo = $account->belongsTo();
		$output->writeln('### Members of ' . count($belongsTo) . ' entities');
		$output->writeln('');
		foreach ($belongsTo as $member) {
			$this->outputMember($output, $member, '   ');
			$output->writeln('');
		}


		return $account;
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
		$output->writeln($prefix . '  - Access: <info>' . $entity->getAccess() . '</info>');
		$output->writeln($prefix . '  - Visibility: <info>' . $entity->getVisibility() . '</info>');
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
	 */
	private function outputMember(OutputInterface $output, IEntityMember $member, $prefix = '') {
		$output->writeln($prefix . '- Member Id: <info>' . $member->getId() . '</info>');

		$this->outputEntity($output, $member->getEntity(), $prefix . '  ');
//		$this->outputAccount($output, $member->getAccount(), $prefix . '  ');
		$output->writeln($prefix . '  - Account Id: <info>' . $member->getAccountId() . '</info>');
		$output->writeln(
			$prefix . '  - Slave Entity Id: <info>' . $member->getSlaveEntityId() . '</info>'
		);
		$output->writeln($prefix . '  - Status: <info>' . $member->getStatus() . '</info>');
		$output->writeln($prefix . '  - Level: <info>' . $member->getLevel() . '</info>');
		$output->writeln($prefix . '  - Creation: <info>' . $member->getCreation() . '</info>');
	}


}

