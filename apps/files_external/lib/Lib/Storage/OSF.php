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

	public function needsPartFile() {
		return false;
	}

	public function __construct($params) {
		parent::__construct($params);
		$this->wb = new WaterButler($params['serviceurl'], $params['nodeId'], 'osfstorage', $params['token']);
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
			$parentId = $this->wb->findIdOrPath($parentDir);

			$this->wb->createDirectory($parentId, $name);
		} catch (\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
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
			$id = $this->wb->findIdOrPath($path);
			$this->wb->deleteDirectory($id);
		} catch(\Exception $e) {
			$this->_dumpErrorLog($e);
			return false;
		}
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
			$id = $this->wb->findIdOrPath($path);
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

		if ($this->is_dir($path)) {
			//folders don't really exist
			$stat['size'] = -1; //unknown
			$stat['mtime'] = time() - 10 * 1000; // ?
		} else {
			try {
				$id = $this->wb->findIdOrPath($path);
				$object = $this->wb->headObject($id);
			} catch (\Exception $e) {
				$this->_dumpErrorLog($e);
				return false;
			}
			$stat['size'] = $object['attributes']['size'];
			$stat['mtime'] = strtotime($object['attributes']['modified']);
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
			$id = $this->wb->findIdOrPath($path);
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
			$id = $this->wb->findIdOrPath($path);
			$this->wb->deleteObject($id);
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
		\OCP\Util::writeLog('external_storage', "fopen($path, $mode)", \OCP\Util::INFO);
		switch ($mode) {
			case 'a':
			case 'ab':
			case 'a+':
				return false;
			case 'r':
			case 'rb':
				try {
					$id = $this->wb->findIdOrPath($path);
					$stream = $this->wb->readObject($id);
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
						$id = $this->wb->findIdOrPath($path);
						$source = fopen($tmpFile, 'rb');
						if ($id) {
							// update
							$this->wb->updateObject($id, $source);
						} else {
							// upload (new)
							$parts = explode('/', $path);
							$name = array_pop($parts);
							$parentDir = implode('/', $parts);
							$parentId = $this->wb->findIdOrPath($parentDir);
							$this->wb->writeObject($parentId, $name, $source);
						}
						fclose($source);
						unlink($tmpFile);
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
}
