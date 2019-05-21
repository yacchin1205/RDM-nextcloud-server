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


namespace OC\Entities\Db;


use OC;
use OC\Entities\Model\EntityAccount;
use OCP\Entities\Model\IEntityAccount;
use OCP\IDBConnection;


/**
 * Class CoreRequestBuilder
 *
 * @package OC\Entities\Db
 */
class CoreRequestBuilder {


	const TABLE_ENTITIES = 'entities';
	const TABLE_ENTITIES_ACCOUNTS = 'entities_accounts';
	const TABLE_ENTITIES_MEMBERS = 'entities_members';
	const TABLE_ENTITIES_TYPES = 'entities_types';

	const LEFT_JOIN_PREFIX_ENTITIES_ACCOUNT = 'entityaccount_';



	/** @var IDBConnection */
	protected $dbConnection;


	/**
	 * CoreRequestBuilder constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->dbConnection = $connection;
	}


	/**
	 *
	 */
	public function getQueryBuilder(): EntitiesQueryBuilder {
		return new EntitiesQueryBuilder(
			$this->dbConnection,
			OC::$server->getSystemConfig(),
			OC::$server->getLogger()
		);
	}


	/**
	 * @param array $data
	 *
	 * @return IEntityAccount
	 */
	public function parseEntityAccountLeftJoin(array $data): IEntityAccount {
		$new = [];
		foreach ($data as $k => $v) {
			if (substr($k, 0, 14) === self::LEFT_JOIN_PREFIX_ENTITIES_ACCOUNT) {
				$new[substr($k, 14)] = $v;
			}
		}

		$account = new EntityAccount();
		$account->importFromDatabase($new);

		return $account;
	}

}

