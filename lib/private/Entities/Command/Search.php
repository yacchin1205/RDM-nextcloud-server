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
use OCP\Entities\Helper\IEntitiesHelper;
use OCP\Entities\IEntitiesManager;
use OCP\Entities\Implementation\IEntities\IEntities;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccounts;
use OCP\Entities\Model\IEntityType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class Search extends Base {


	/** @var IEntitiesManager */
	private $entitiesManager;

	/** @var IEntitiesHelper */
	private $entitiesHelper;

	/** @var OutputInterface */
	private $output;


	public function __construct(IEntitiesHelper $entitiesHelper, IEntitiesManager $entitiesManager
	) {
		parent::__construct();

		$this->entitiesHelper = $entitiesHelper;
		$this->entitiesManager = $entitiesManager;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();
		$this->setName('entities:search')
			 ->addArgument('needle', InputArgument::OPTIONAL, 'needle', '')
			 ->addOption('accounts', '', InputOption::VALUE_NONE, 'search for accounts')
			 ->addOption('type', '', InputOption::VALUE_REQUIRED, 'limit to a type', '')
			 ->setDescription('Search for entities');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->output = $output;

		$needle = $input->getArgument('needle');
		$type = $input->getOption('type');

		if ($input->getOption('accounts')) {
			$this->searchAccounts($needle, $type);
		} else {
			$this->searchEntities($needle, $type);
		}
		$this->output->writeln('');
	}


	/**
	 * @param string $needle
	 * @param string $type
	 *
	 * @throws Exception
	 */
	private function searchAccounts(string $needle, $type = ''): void {

		if ($type !== '') {
			$this->verifyType(IEntitiesAccounts::INTERFACE, $type);
		}

		if ($needle === '') {
			$accounts = $this->entitiesManager->getAllAccounts($type);
		} else {
			$accounts = $this->entitiesManager->searchAccounts($needle, $type);
		}

		foreach ($accounts as $account) {
			$this->output->writeln(
				'- <info>' . $account->getId() . '</info> - ' . $account->getType() . ' - '
				. $account->getAccount()
			);
		}
	}


	/**
	 * @param string $needle
	 * @param string $type
	 *
	 * @throws Exception
	 */
	private function searchEntities(string $needle, $type = ''): void {

		if ($type !== '') {
			$this->verifyType(IEntities::INTERFACE, $type);
		}

		if ($needle === '') {
			$entities = $this->entitiesManager->getAllEntities($type);
		} else {
			$entities = $this->entitiesManager->searchEntities($needle, $type);
		}

		foreach ($entities as $entity) {
			$this->output->writeln(
				'- <info>' . $entity->getId() . '</info> - ' . $entity->getType() . ' - '
				. $entity->getName()
			);
		}
	}


	/**
	 * @param string $interface
	 * @param string $type
	 *
	 * @throws Exception
	 */
	private function verifyType(string $interface, string $type) {

		$entityTypes = $this->entitiesHelper->getEntityTypes($interface);
		$types = array_map(
			function(IEntityType $item) {
				return $item->getType();
			}, $entityTypes
		);

		if (!in_array($type, $types)) {
			throw new Exception('Please specify a type: ' . implode(', ', $types));
		}

	}

}

