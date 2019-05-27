<?php

namespace OCA\Files_External\Lib\Storage;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OCP\Constants;
use GuzzleHttp\Exception\ClientException;
use OCA\Files_External\Lib\WaterButler;
use OC\Files\Filesystem;
use fkooman\OAuth\Client\AccessToken;

class OSF extends \OC\Files\Storage\Common {
	private $wb;

	/** @var CappedMemoryCache|Result[] */
	private $objectCache;

	/** @var CappedMemoryCache|Result[] */
	private $idOrPathCache;

	public function needsPartFile() {
		return false;
	}

	public function __construct($params) {
		parent::__construct($params);
		$accessToken = AccessToken::fromJson($params['token']);
		$this->wb = new WaterButler($params['serviceurl'], $params['nodeId'],
		                            $params['storagetype'], $accessToken->getToken());
		$this->objectCache = new CappedMemoryCache();
		$this->idOrPathCache = new CappedMemoryCache();
	}

	private function invalidateCache($key) {
		$this->invalidateObjectCache($key);
		$this->invalidateIdOrPathCache($key);
	}

	private function invalidateObjectCache($key) {
		$this->objectCache->remove($key);
		$keys = array_keys($this->objectCache->getData());
		$keyLength = strlen($key);
		foreach ($keys as $existingKey) {
			if (substr($existingKey, 0, $keyLength) === $keys) {
				$this->objectCache->remove($existingKey);
			}
		}
	}

	private function invalidateIdOrPathCache($key) {
		$this->idOrPathCache->remove($key);
		$keys = array_keys($this->idOrPathCache->getData());
		$keyLength = strlen($key);
		foreach ($keys as $existingKey) {
			if (substr($existingKey, 0, $keyLength) === $keys) {
				$this->idOrPathCache->remove($key);
			}
		}
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
		return $this->wb->getStorageId();
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
		\OCP\Util::writeLog('external_storage', "mkdir($path)", \OCP\Util::INFO);
		try {
			$parts = explode('/', $path);
			$name = array_pop($parts);
			$parentDir = implode('/', $parts);
			$parentId = $this->findIdOrPath($parentDir);

			$this->wb->createDirectory($parentId, $name);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}

		$this->invalidateCache($path);

		return true;
	}

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function rmdir($path) {
		\OCP\Util::writeLog('external_storage', "rmdir($path)", \OCP\Util::INFO);
		try {
			$id = $this->findIdOrPath($path);
			$this->wb->deleteDirectory($id);
		} catch(\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
		$this->invalidateCache($path);
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
		\OCP\Util::writeLog('external_storage', "opendir($path)", \OCP\Util::INFO);
		try {
			$id = $this->findIdOrPath($path);
			$objects = $this->wb->getList($id);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}

		$files = [];
		foreach ($objects as $object) {
			$files[] = $object['attributes']['name'];
		}

		\OCP\Util::writeLog('external_storage', "files = ".implode(',', $files), \OCP\Util::INFO);

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
		\OCP\Util::writeLog('external_storage', "stat($path)", \OCP\Util::INFO);
//		\OCP\Util::writeLog('external_storage', "file type is ".$this->filetype($path), \OCP\Util::INFO);
		$stat = [];

		$filetype = $this->filetype($path);
		if ($filetype === 'dir') {
			//folders don't really exist
			$stat['size'] = -1; //unknown
			$stat['mtime'] = time() - 10 * 1000; // ?
		} else if ($filetype === 'file') {
			try {
				$id = $this->findIdOrPath($path);
				$object = $this->headObject($id);
			} catch (\Exception $e) {
				$this->_dumpErrorLog($e);
				return false;
			}
			$stat['size'] = $object['attributes']['size'];
			$stat['mtime'] = strtotime($object['attributes']['modified']);
		} else {
			return false;
		}

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
//		\OCP\Util::writeLog('external_storage', "filetype($path)", \OCP\Util::INFO);
		if ($this->isRoot($path) || substr($path, -1) === '/') {
			return 'dir';
		}
		try {
			$id = $this->findIdOrPath($path);
			if ($id === null) {
				\OCP\Util::writeLog('external_storage', "$path is not found", \OCP\Util::WARN);
				return false;
			} else if (substr($id, -1) === '/') {
				return 'dir';
			} else {
				return 'file';
			}
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
	}

	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function file_exists($path) {
		\OCP\Util::writeLog('external_storage', "file_exists($path)", \OCP\Util::INFO);
		return $this->filetype($path) !== false;
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function unlink($path) {
		\OCP\Util::writeLog('external_storage', "unlink($path)", \OCP\Util::INFO);
		try {
			$id = $this->findIdOrPath($path);
			$this->wb->deleteObject($id);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
		$this->invalidateCache($path);
		return true;
	}

	public function rename($path1, $path2) {
		\OCP\Util::writeLog('external_storage', "rename($path1, $path2)", \OCP\Util::INFO);

		$id1 = $this->findIdOrPath($path1);
		$parts1 = explode('/', $path1);
		$name1 = array_pop($parts1);
		$parentDir1 = implode('/', $parts1);

		$parts2 = explode('/', $path2);
		$name2 = array_pop($parts2);
		$parentDir2 = implode('/', $parts2);

		try {
			if ($parentDir1 === $parentDir2) {
				// rename
				\OCP\Util::writeLog('external_storage', "rename($path1, $path2) (rename)", \OCP\Util::INFO);
				$this->wb->rename($id1, $name2);
			} else if ($name1 === $name2) {
				// move
				\OCP\Util::writeLog('external_storage', "rename($path1, $path2) (move)", \OCP\Util::INFO);
				$parentDirId2 = $this->findIdOrPath($parentDir2);
				$this->wb->move($id1, $parentDirId2);
			} else {
				// move & rename
				\OCP\Util::writeLog('external_storage', "rename($path1, $path2) (move & rename)", \OCP\Util::INFO);
				$parentDirId2 = $this->findIdOrPath($parentDir2);
				$this->wb->move($id1, $parentDirId2, $name2);
			}
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}

		$this->invalidateCache($path1);

		return true;
	}

	public function copy($path1, $path2) {
		\OCP\Util::writeLog('external_storage', "copy($path1, $path2)", \OCP\Util::INFO);

		$id1 = $this->findIdOrPath($path1);
		$parts2 = explode('/', $path2);
		$parentDir2 = implode('/', $parts2);

		try {
			if ($this->is_dir($path1)) {
				$this->removeCacheDir($path1);
			} else {
				$this->removeCachedFile($path2);
			}
			$parentDirId2 = $this->findIdOrPath($parentDir2);
			$this->wb->copy($id1, $parentDirId2);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
		$this->invalidateCache($path2);
	}

	private function removeCacheDir($path) {
		$dir = $this->opendir($path);
		while ($file = readdir($dir)) {
			$child = "$dir/$path";
			if ($this->is_dir($path)) {
				$this->removeCacheDir($child);
			} else {
				$this->removeCachedFile($child);
			}
		}
		closedir($dir);
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
		\OCP\Util::writeLog('external_storage', "fopen($path, $mode)", \OCP\Util::INFO);
		switch ($mode) {
			case 'a':
			case 'ab':
			case 'a+':
				return false;
			case 'r':
			case 'rb':
				try {
					$id = $this->findIdOrPath($path);
					$stream = $this->wb->readObject($id);
					$this->invalidateCache($path);
					return $stream;
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
				if (strrpos($path, '.') !== false) {
					$ext = substr($path, strrpos($path, '.'));
				} else {
					$ext = '';
				}
				$tmpFile = \OCP\Files::tmpFile($ext);
				$handle = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile, $mode) {
					try {
						$id = $this->findIdOrPath($path);
						$source = fopen($tmpFile, 'rb');
						if ($id) {
							// update
							$this->wb->updateObject($id, $source);
						} else {
							// upload (new)
							$parts = explode('/', $path);
							$name = array_pop($parts);
							$parentDir = implode('/', $parts);
							$parentId = $this->findIdOrPath($parentDir);
							$this->wb->writeObject($parentId, $name, $source);
						}
						fclose($source);
						unlink($tmpFile);
						$this->invalidateCache($path);
						return true;
					} catch (\Exception $e) {
						$this->_dumpErrorLog($e);
						return false;
					}
				});
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
		\OCP\Util::writeLog('external_storage', "touch($path, $mtime)", \OCP\Util::INFO);
		// TODO: Implement touch() method.
		return false;
	}

	public function test() {
		\OCP\Util::writeLog('external_storage', "test()", \OCP\Util::INFO);
		try {
			$this->wb->getList();
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
		return true;
	}

	private function _dumpErrorLog($e) {
		\OC::$server->getLogger()->logException($e, [
			'level' => \OCP\Util::ERROR,
			'app' => 'files_external',
		]);
	}

	private function isRoot($path) {
		return $path === '' || $path === '.' || $path === './' || $path === '/';
	}

	private function findIdOrPath($path) {
		if (!$this->idOrPathCache->hasKey($path)) {
			try {
				$val = $this->wb->findIdOrPath($path);
				$this->idOrPathCache->set($path, $val);
			} catch (ClientException $e) {
				if ($e->getResponse()->getStatusCode() >= 500) {
					throw $e;
				}
				$this->idOrPathCache->set($path, false);
			}
		}

		return $this->idOrPathCache->get($path);
	}

	private function headObject($path) {
		if (!$this->objectCache->hasKey($path)) {
			try {
				$val = $this->wb->headObject($path);
				$this->objectCache->set($path, $val);
			} catch (ClientException $e) {
				if ($e->getResponse()->getStatusCode() >= 500) {
					throw $e;
				}
				$this->objectCache->set($path, false);
			}
		}

		return $this->objectCache->get($path);
	}
}
