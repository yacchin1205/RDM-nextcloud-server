<?php
/**
* ownCloud
*
* @author Bjoern Schiessle
* @copyright 2014 Bjoern Schiessle <schiessle@owncloud.com>
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @group DB
 * Class Test_Share_Helper
 */
class Test_Share_Helper extends \Test\TestCase {

	public function expireDateProvider() {
		return array(
			// no default expire date, we take the users expire date
			array(array('defaultExpireDateSet' => false), 2000000000, 2000010000, 2000010000),
			// no default expire date and no user defined expire date, return false
			array(array('defaultExpireDateSet' => false), 2000000000, null, false),
			// unenforced expire data and no user defined expire date, return false (because the default is not enforced)
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false), 2000000000, null, false),
			// enforced expire date and no user defined expire date, take default expire date
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true), 2000000000, null, 2000086400),
			// unenforced expire date and user defined date > default expire date, take users expire date
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false), 2000000000, 2000100000, 2000100000),
			// unenforced expire date and user expire date < default expire date, take users expire date
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false), 2000000000, 2000010000, 2000010000),
			// enforced expire date and user expire date < default expire date, take users expire date
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true), 2000000000, 2000010000, 2000010000),
			// enforced expire date and users expire date > default expire date, take default expire date
			array(array('defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true), 2000000000, 2000100000, 2000086400),
		);
	}

	/**
	 * @dataProvider expireDateProvider
	 */
	public function testCalculateExpireDate($defaultExpireSettings, $creationTime, $userExpireDate, $expected) {
		$result = \OC\Share\Helper::calculateExpireDate($defaultExpireSettings, $creationTime, $userExpireDate);
		$this->assertSame($expected, $result);
	}

	public function fixRemoteURLInShareWithData() {
		$userPrefix = ['test@', 'na/me@'];
		$protocols = ['', 'http://', 'https://'];
		$remotes = [
			'localhost',
			'test:foobar@localhost',
			'local.host',
			'dev.local.host',
			'dev.local.host/path',
			'127.0.0.1',
			'::1',
			'::192.0.2.128',
		];

		$testCases = [
			['test', 'test'],
			['na/me', 'na/me'],
			['na/me/', 'na/me'],
			['na/index.php', 'na/index.php'],
			['http://localhost', 'http://localhost'],
			['http://localhost/', 'http://localhost'],
			['http://localhost/index.php', 'http://localhost/index.php'],
			['http://localhost/index.php/s/token', 'http://localhost/index.php/s/token'],
			['http://test:foobar@localhost', 'http://test:foobar@localhost'],
			['http://test:foobar@localhost/', 'http://test:foobar@localhost'],
			['http://test:foobar@localhost/index.php', 'http://test:foobar@localhost'],
			['http://test:foobar@localhost/index.php/s/token', 'http://test:foobar@localhost'],
		];

		foreach ($userPrefix as $user) {
			foreach ($remotes as $remote) {
				foreach ($protocols as $protocol) {
					$baseUrl = $user . $protocol . $remote;

					$testCases[] = [$baseUrl, $baseUrl];
					$testCases[] = [$baseUrl . '/', $baseUrl];
					$testCases[] = [$baseUrl . '/index.php', $baseUrl];
					$testCases[] = [$baseUrl . '/index.php/s/token', $baseUrl];
				}
			}
		}
		return $testCases;
	}

	/**
	 * @dataProvider fixRemoteURLInShareWithData
	 */
	public function testFixRemoteURLInShareWith($remote, $expected) {
		$this->assertSame($expected, \OC\Share\Helper::fixRemoteURLInShareWith($remote));
	}

	/**
	 * @dataProvider dataTestCompareServerAddresses
	 *
	 * @param string $server1
	 * @param string $server2
	 * @param bool $expected
	 */
	public function testIsSameUserOnSameServer($user1, $server1, $user2, $server2, $expected) {
		$this->assertSame($expected,
			\OC\Share\Helper::isSameUserOnSameServer($user1, $server1, $user2, $server2)
		);
	}

	public function dataTestCompareServerAddresses() {
		return [
			['user1', 'http://server1', 'user1', 'http://server1', true],
			['user1', 'https://server1', 'user1', 'http://server1', true],
			['user1', 'http://serVer1', 'user1', 'http://server1', true],
			['user1', 'http://server1/',  'user1', 'http://server1', true],
			['user1', 'server1', 'user1', 'http://server1', true],
			['user1', 'http://server1', 'user1', 'http://server2', false],
			['user1', 'https://server1', 'user1', 'http://server2', false],
			['user1', 'http://serVer1', 'user1', 'http://serer2', false],
			['user1', 'http://server1/', 'user1', 'http://server2', false],
			['user1', 'server1', 'user1', 'http://server2', false],
			['user1', 'http://server1', 'user2', 'http://server1', false],
			['user1', 'https://server1', 'user2', 'http://server1', false],
			['user1', 'http://serVer1', 'user2', 'http://server1', false],
			['user1', 'http://server1/',  'user2', 'http://server1', false],
			['user1', 'server1', 'user2', 'http://server1', false],
		];
	}
}
