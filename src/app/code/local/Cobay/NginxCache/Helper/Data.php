<?php
class Cobay_NginxCache_Helper_Data extends Mage_Core_Helper_Abstract {

	const MAGE_CACHE_NAME = 'nginx_cache';

	/**
	 * Contains a newly generated v4 uuid whenever read, possibly not available
	 * on all kernels
	 */
	const UUID_SOURCE = '/proc/sys/kernel/random/uuid';
	/**
	 * Compression level for serialization compression
	 *
	 * Testing showed no significant (size) difference between levels 1 and 9
	 * so using 1 since it's faster
	 */
	const COMPRESSION_LEVEL = 1;
	/**
	 * Hash algorithm to use in various cryptographic methods
	 */
	const HASH_ALGORITHM = 'sha256';
	/**
	 * Cookie name for the Varnish bypass
	 *
	 * @var string
	 */
	const BYPASS_COOKIE_NAME = 'varnish_bypass';
	/**
	 * encryption singleton thing
	 *
	 * @var Mage_Core_Model_Encryption
	 */
	protected $_crypt = null;

	public function getNginxCacheEnabled() {
		return Mage::app()->useCache($this->getMageCacheName());
	}

	public function getAutoPurgeEnabled() {
		return Mage::getStoreConfig('system/nginxcache/autopurge_action');
	}

	public function getMageCacheName() {
		return self::MAGE_CACHE_NAME;
	}

	public function getNginxCachePath() {
		$cachedir_path = Mage::getStoreConfig('system/nginxcache/cachedir_path');
		if(empty($cachedir_path)){
			Mage::throwException($this->__('Please input a cache dir path in system > configuration > Nginx fastcgi_cache integration > Cache dir path'));
		}
		if(!is_dir($cachedir_path)) {
			Mage::throwException($this->__('Not exist a cache dir path in system > configuration > Nginx fastcgi_cache integration > Cache dir path'));
		}
		if(!is_writable($cachedir_path)) {
			Mage::throwException($this->__("$cachedir_path Must writable!!!"));
		}
		return $cachedir_path;
	}

    public function isCacheableAction($fullActionName)
    {
        $cacheActionsString = Mage::getStoreConfig('system/nginxcache/cache_actions');
        foreach (explode(',', $cacheActionsString) as $singleActionConfiguration) {
            list($actionName, $lifeTime) = explode(';', $singleActionConfiguration);
            if (trim($actionName) == $fullActionName) {
                return intval(trim($lifeTime));
            }
        }
        return false;
	}

	/* ------------------- */

	/**
	 * Get the NginxCache version
	 *
	 * @return string
	 */
	public function getVersion() {
		return Mage::getConfig()
		           ->getModuleConfig('Cobay_NginxCache')->version;
	}

	/**
	 * Generate a v4 UUID
	 *
	 * @return string
	 */
	public function generateUuid() {
		if (is_readable(self::UUID_SOURCE)) {
			$uuid = trim(file_get_contents(self::UUID_SOURCE));
		} elseif (function_exists('mt_rand')) {
			/**
			 * Taken from stackoverflow answer, possibly not the fastest or
			 * strictly standards compliant
			 * @link http://stackoverflow.com/a/2040279
			 */
			$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				// 16 bits for "time_mid"
				mt_rand(0, 0xffff),
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand(0, 0x0fff) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand(0, 0x3fff) | 0x8000,
				// 48 bits for "node"
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
		} else {
			// chosen by dice roll, guaranteed to be random
			$uuid = '4';
		}
		return $uuid;
	}
}