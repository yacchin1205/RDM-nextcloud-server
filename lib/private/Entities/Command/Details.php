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
use OCP\Entities\IEntitiesManager;
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
		$entity = $this->entitiesManager->getEntity($input->getArgument('entityId'));

		echo json_encode($entity, JSON_PRETTY_PRINT) . "\n";

		if ($entity->getType() === User::TYPE) {

			echo '>> ' . json_encode($entity->belongsTo(), JSON_PRETTY_PRINT);
		}
	}

}

