<?php
class Cobay_NginxCache_Model_Observer_Ban extends Varien_Event_Observer {

	/**
	 * bash script for Nginx fastcgi_cache purge
	 */
	const NGX_CACHE_PURGE_SHELL = 'ngx_cache_purge.sh';

	public function banCacheType($eventObject) {
		if($eventObject->getType() !== Mage::helper('nginxcache')->getMageCacheName()) return;
		$this->banAllCache($eventObject);
	}

	public function banAllCache($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
			$purge_shell = Mage::getBaseDir( 'base' ) . '/shell/' . self::NGX_CACHE_PURGE_SHELL;
			$cachepath   = Mage::helper( 'nginxcache' )->getNginxCachePath();
			$output      = `sudo $purge_shell "(.+)" $cachepath`;
			//$output = `rm -rf $cachepath/*`;
			Mage::dispatchEvent( 'nginxcache_ban_all_cache', [] );
		}
	}

	public function banProductPageCache($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		$purge_shell = Mage::getBaseDir('base').'/shell/'.self::NGX_CACHE_PURGE_SHELL;
		$cachepath = Mage::helper('nginxcache')->getNginxCachePath();
		$banHelper = Mage::helper('nginxcache/ban');
		$product = $eventObject->getProduct();
		$urlPattern = $banHelper->getProductBanRegex($product);
		//d($urlPattern);
		if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
			$output = `$purge_shell "$urlPattern" $cachepath`;
			Mage::dispatchEvent( 'nginxcache_ban_product_cache', [$output] );
		}

		// crawl for caching automatically
		$cronHelper = Mage::helper('nginxcache/cron');
		if ($cronHelper->getCrawlerEnabled()) {
			$cronHelper->addProductToCrawlerQueue($product);
			foreach ($banHelper->getParentProducts($product) as $parentProduct) {
				$cronHelper->addProductToCrawlerQueue($parentProduct);
			}
		}
	}

	public function banProductReview($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
			$purge_shell = Mage::getBaseDir( 'base' ) . '/shell/' . self::NGX_CACHE_PURGE_SHELL;
			$cachepath   = Mage::helper( 'nginxcache' )->getNginxCachePath();
			$patterns    = array();
			/* @var $review \Mage_Review_Model_Review */
			$review = $eventObject->getObject();
			/* @var $productCollection \Mage_Review_Model_Resource_Review_Product_Collection */
			$productCollection = $review->getProductCollection();
			$products          = $productCollection->addEntityFilter( (int) $review->getEntityPkValue() )->getItems();
			$productIds        = array_unique( array_map(
				function ( $p ) {
					return $p->getEntityId();
				},
				$products
			) );

			$patterns[]      = sprintf( '/review/product/list/id/(%s)/category/',
				implode( '|', array_unique( $productIds ) ) );
			$patterns[]      = sprintf( '/review/product/view/id/%d/',
				$review->getEntityId() );
			$productPatterns = array();
			foreach ( $products as $p ) {
				$urlKey = $p->getUrlModel()->formatUrlKey( $p->getName() );
				if ( $urlKey ) {
					// Do escape special characters for grep basic pattern
					$urlKey            = str_replace(
						[ '?', '+', '{', '}', '|', '(', ')', '"', '.', '*' ],
						[ '\?', '\+', '\{', '\}', '\|', '\(', '\)', '\"', '\.', '\*' ],
						$urlKey
					);
					$productPatterns[] = $urlKey;
				}
			}
			if ( ! empty( $productPatterns ) ) {
				$productPatterns = array_unique( $productPatterns );
				$patterns[]      = implode( '|', $productPatterns );
			}
			$urlPattern = sprintf( '(%s)', implode( '|', $patterns ) );
			//d($urlPattern);
			$output = `$purge_shell "$urlPattern" $cachepath`;
		}
	}

	public function banProductPageCacheCheckStock($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		$purge_shell = Mage::getBaseDir('base').'/shell/'.self::NGX_CACHE_PURGE_SHELL;
		$cachepath = Mage::helper('nginxcache')->getNginxCachePath();
		$item = $eventObject->getItem();
		if ($item->getStockStatusChangedAutomatically() ||
		    ($item->getOriginalInventoryQty() <= 0 &&
		     $item->getQty() > 0 &&
		     $item->getQtyCorrection() > 0)) {
			$banHelper = Mage::helper('nginxcache/ban');
			$product = Mage::getModel('catalog/product')
			               ->load($item->getProductId());
			$urlPattern = sprintf('(%s)', $banHelper->getProductBanRegex($product));
			//d($urlPattern);
			if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
				$output = `$purge_shell "$urlPattern" $cachepath`;
				Mage::dispatchEvent( 'nginxcache_ban_product_cache_check_stock', [$output] );
			}

			// crawl for caching automatically
			$cronHelper = Mage::helper('nginxcache/cron');
			if ($cronHelper->getCrawlerEnabled()) {
				$cronHelper->addProductToCrawlerQueue($product);
				foreach ($banHelper->getParentProducts($product)
					as $parentProduct) {
					$cronHelper->addProductToCrawlerQueue($parentProduct);
				}
			}
		}
	}

	public function banCategoryCache($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		$purge_shell = Mage::getBaseDir('base').'/shell/'.self::NGX_CACHE_PURGE_SHELL;
		$cachepath = Mage::helper('nginxcache')->getNginxCachePath();
		$category = $eventObject->getCategory();
		$urlPattern = $category->getUrlKey();
		$urlPattern = sprintf('(%s)', $urlPattern);
		//d($urlPattern);
		if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
			$output = `$purge_shell "$urlPattern" $cachepath`;
			Mage::dispatchEvent( 'nginxcache_ban_category_cache', [$output] );
		}

		// crawl for caching automatically
		$cronHelper = Mage::helper('nginxcache/cron');
		if ($cronHelper->getCrawlerEnabled()) {
			$cronHelper->addCategoryToCrawlerQueue($category);
		}
	}

	public function banCmsPageCache($eventObject) {
		if( !Mage::helper('nginxcache')->getNginxCacheEnabled() ) return;

		$purge_shell = Mage::getBaseDir('base').'/shell/'.self::NGX_CACHE_PURGE_SHELL;
		$cachepath = Mage::helper('nginxcache')->getNginxCachePath();
		$pageId = $eventObject->getDataObject()->getIdentifier();
		$urlPattern = sprintf('(%s)', $pageId);
		//d($urlPattern);
		if( Mage::helper('nginxcache')->getAutoPurgeEnabled() ) {
			$output = `$purge_shell "$urlPattern" $cachepath`;
			Mage::dispatchEvent( 'nginxcache_ban_cms_page_cache', [$output] );
		}

		// crawl for caching automatically
		$cronHelper = Mage::helper('nginxcache/cron');
		if ($cronHelper->getCrawlerEnabled()) {
			$cronHelper->addCmsPageToCrawlerQueue($pageId);
		}
	}

}

