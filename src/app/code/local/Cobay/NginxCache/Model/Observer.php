<?php
class Cobay_NginxCache_Model_Observer {

	/**
	 * Check when nginx caching should be enabled.
	 */
	public function processPreDispatch(Varien_Event_Observer $observer)
	{

		$helper = Mage::helper('nginxcache'); /* @var $helper Cobay_NginxCache_Helper_Data */
		$event = $observer->getEvent(); /* @var $event Varien_Event */
		$controllerAction = $event->getControllerAction(); /* @var $controllerAction Mage_Core_Controller_Varien_Action */
		$fullActionName = $controllerAction->getFullActionName();

		$lifetime = $helper->isCacheableAction($fullActionName);

		$response = $controllerAction->getResponse(); /* @var $response Mage_Core_Controller_Response_Http */
		if ($lifetime) {
			// allow caching
			$response->setHeader('X-Magento-Lifetime', $lifetime, true); // Only for debugging and information
			$response->setHeader('Cache-Control', 'max-age='. $lifetime, true);
			$response->setHeader('cobay-nginx-cache', 'cache', true);
		} else {
			// do not allow caching
			$cookie = Mage::getModel('core/cookie'); /* @var $cookie Mage_Core_Model_Cookie */

			$name = '';
			$loggedIn = false;
			$session = Mage::getSingleton('customer/session'); /* @var $session Mage_Customer_Model_Session  */
			if ($session->isLoggedIn()) {
				$loggedIn = true;
				$name = $session->getCustomer()->getName();
            }
			$response->setHeader('X-Magento-LoggedIn', $loggedIn ? '1' : '0', true); // Only for debugging and information
            $cookie->set('cobaynginxcache_customername', $name, '3600', '/');
		}
		$response->setHeader('X-Magento-Action', $fullActionName, true); // Only for debugging and information

		return $this;
	}

	/**
	 * Add layout handle 'cobaynginxcache_cacheable' or 'cobaynginxcache_notcacheable'
	 */
	public function beforeLoadLayout(Varien_Event_Observer $observer)
	{
		$helper = Mage::helper('nginxcache'); /* @var $helper Cobay_NginxCache_Helper_Data */
		$event = $observer->getEvent(); /* @var $event Varien_Event */
		$controllerAction = $event->getAction(); /* @var $controllerAction Mage_Core_Controller_Varien_Action */
		$fullActionName = $controllerAction->getFullActionName();

		$lifetime = $helper->isCacheableAction($fullActionName);

		$handle = $lifetime ? 'nginxcache_cacheable' : 'nginxcache_notcacheable';

		$observer->getEvent()->getLayout()->getUpdate()->addHandle($handle);
	}
}
