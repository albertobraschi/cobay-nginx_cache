<?php
class Cobay_NginxCache_Model_Observer_Cron extends Varien_Event_Observer {

	/**
	 * Max time to crawl URLs if max_execution_time is 0 (unlimited) in seconds
	 *
	 * @var int
	 */
	const MAX_CRAWL_TIME = 300;

	/**
	 * Amount of time of execution time to leave for other cron processes
	 *
	 * @var int
	 */
	const EXEC_TIME_BUFFER = 15;

	/**
	 * Crawl available URLs in the queue until we get close to max_execution_time
	 * (up to MAX_CRAWL_TIME)
	 *
	 * @param  Varien_Object $eventObject
	 * @return null
	 */
	public function crawlUrls($eventObject) {
		$helper = Mage::helper('nginxcache/cron');
		if ($helper->getCrawlerEnabled()) {
			$maxRunTime = $helper->getAllowedRunTime();
			if ($maxRunTime === 0) {
				$maxRunTime = self::MAX_CRAWL_TIME;
			}

			$batchSize = $helper->getCrawlerBatchSize();
			$timeout = $helper->getCrawlerWaitPeriod();
			$crawlCount = 0;

			// just in case we have a silly short max_execution_time
			$maxRunTime = abs($maxRunTime - self::EXEC_TIME_BUFFER);
			while (($helper->getRunTime() < $maxRunTime) &&
			       $url = $helper->getNextUrl()) {
				if ( ! $this->_crawlUrl($url)) {
					Mage::helper('nginxcache/debug')->logWarn(
						'Failed to crawl URL: %s', $url );
				}

				if ($crawlCount > 0
				    && $timeout > 0
				    && $batchSize > 0
				    && $crawlCount % $batchSize == 0
				) {
					Mage::helper('nginxcache/debug')->logDebug('Crawled '.$crawlCount.' urls, sleeping for '.$timeout.' seconds');
					sleep($timeout);
				}
				$crawlCount++;
			}
		}
	}

	/**
	 * Queue all URLs
	 *
	 * @param  Varien_Object $eventObject
	 * @return null
	 */
	public function queueAllUrls($eventObject) {
		$helper = Mage::helper('nginxcache/cron');
		if ($helper->getCrawlerEnabled()) {
			$helper->addUrlsToCrawlerQueue($helper->getAllUrls());
		}
	}

	/**
	 * Request a single URL, returns whether the request was successful or not
	 *
	 * @param  string $url
	 * @return bool
	 */
	protected function _crawlUrl($url) {
		$client = Mage::helper('nginxcache/cron')->getCrawlerClient();
		$client->setUri($url);
		Mage::helper('nginxcache/debug')->logDebug('Crawling URL: %s', $url);
		try {
			$response = $client->request();
		} catch (Exception $e) {
			Mage::helper('nginxcache/debug')->logWarn(
				'Error crawling URL (%s): %s',
				$url, $e->getMessage() );
			return false;
		}
		return $response->isSuccessful();
	}
}