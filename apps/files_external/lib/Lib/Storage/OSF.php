<?php

namespace OCA\Files_External\Lib\Storage;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OCP\Constants;
use GuzzleHttp\Exception\ClientException;
use OCA\Files_External\Lib\WaterButler;

class OSF extends \OC\Files\Storage\Common {
	private $wb;

	public function __construct($params) {
		parent::__construct($params);
		$this->wb = new WaterButler($params['url'], $params['nodeId'], $params['providerId'], $params['token']);
	}

	/**
	 * Get the identifier for the storage,
	 * the returned id should be the same for every storage object that is created with the same parameters
	 * and two storage objects with the same id should refer to two storages that display the same files.
	 *
	 * @return string
	 * @since 6.0.0
	 */
	public function getId() {
		return $this->wb;
	}

	/**
	 * see http://php.net/manual/en/function.mkdir.php
	 * implementations need to implement a recursive mkdir
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function mkdir($path) {
		// TODO: Implement mkdir() method.
		return false;
	}

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function rmdir($path) {
		// TODO: Implement rmdir() method.
		return false;
	}

	/**
	 * see http://php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|false
	 * @since 6.0.0
	 */
	public function opendir($path) {
		try {
			$objects = $this->wb->getList($path);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}

		$files = [];
		foreach ($objects as $object) {
			$files[] = $object['attributes']['path'];
		}

		return IteratorDirectory::wrap($files);
	}

	/**
	 * see http://php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|false
	 * @since 6.0.0
	 */
	public function stat($path) {
		try {
			$object = $this->wb->headObject($path);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}

		$stat = [];
		// TODO: dirとfileで処理をわける？
		$stat['size'] = $object['attributes']['size'];
		$stat['mtime'] = strtotime($object['attributes']['modified']);
		$stat['atime'] = time();

		return $stat;
	}

	/**
	 * see http://php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return string|false
	 * @since 6.0.0
	 */
	public function filetype($path) {
		// TODO: たぶんpathを見るだけでわかるんだけど、どうだろう？
		return substr($path, -1) === '/' ? 'dir' : 'file';
	}

	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function file_exists($path) {
		try {
			$this->wb->headObject($path);
			return true;
		} catch (ClientException $e) {
			if (!$e->getResponse()->getStatusCode() === 404) {
				$this->_dumpErrorLog($e);
			}
			return false;
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function unlink($path) {
		try {
			$this->wb->deleteObject($path);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|false
	 * @since 6.0.0
	 */
	public function fopen($path, $mode) {
		switch ($mode) {
			case 'a':
			case 'ab':
			case 'a+':
				return false;
			case 'r':
			case 'rb':
				try {
					return $this->wb->readObject($path);
				} catch (\Exception $e) {
					$this->_dumpErrorLog($e);
					return false;
				}
			case 'w':
			case 'wb':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				// TODO: implement write object process.
				return false;
		}

		return false;
	}

	/**
	 * see http://php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 * @since 6.0.0
	 */
	public function touch($path, $mtime = null) {
		// TODO: Implement touch() method.
		return false;
	}

	private function _dumpErrorLog($e) {
		\OC::$server->getLogger()->logException($e, [
			'level' => \OCP\Util::ERROR,
			'app' => 'files_external',
		]);
	}
}
