<?php
class Cobay_NginxCache_Helper_Ban extends Mage_Core_Helper_Abstract {
	/**
	 * Get the regex for banning a product page from the cache, including
	 * any parent products for configurable/group products
	 *
	 * @param  Mage_Catalog_Model_Product $product
	 * @return string
	 */
	public function getProductBanRegex($product) {
		$urlPatterns = array();
		foreach ($this->getParentProducts($product) as $parentProduct) {
			if ($parentProduct->getUrlKey()) {
				$tmpstr = $parentProduct->getUrlKey();
				// Do escape special characters for grep basic pattern
				$tmpstr = str_replace(
					['?', '+', '{', '}', '|', '(', ')', '"', '.', '*'],
					['\?', '\+', '\{', '\}', '\|', '\(', '\)', '\"', '\.', '\*'],
					$tmpstr
				);
				$urlPatterns[] = $tmpstr;
			}
		}
		if ($product->getUrlKey()) {
			$urlPatterns[] = $product->getUrlKey();
		}
		$pattern = sprintf('(%s)', implode('|', $urlPatterns));
		return $pattern;
	}
	/**
	 * Get parent products of a configurable or group product
	 *
	 * @param  Mage_Catalog_Model_Product $childProduct
	 * @return array
	 */
	public function getParentProducts($childProduct) {
		$parentProducts = array();
		foreach (array('configurable', 'grouped') as $pType) {
			foreach (Mage::getModel('catalog/product_type_'.$pType)
			             ->getParentIdsByChild($childProduct->getId()) as $parentId) {
				$parentProducts[] = Mage::getModel('catalog/product')
				                        ->load($parentId);
			}
		}
		return $parentProducts;
	}
}