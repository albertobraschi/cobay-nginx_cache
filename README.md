# Magento 1.9 full page cache working with nginx fastcgi_cache

![nginx cache management magento](https://i.imgur.com/Ez0Z6l0.png)

![nginx cache system config magento](https://i.imgur.com/8BtrgvW.png)

- Nginx 1.13.9

    - http/2
    - tls (https) with Let's Encrypt
    - fastcgi_cache
        - fastcgi_cache_use_stale updating
        - fastcgi_cache_background_update on

- Magento 1.9.3.8

    - full page cache
        - product list pages
        - product view pages
        - home page and miscellaneous cms pages

    - a punching hole (dynamic parts) based on Aoe_Static extension
        - use ajax method not varnish ESI method.
        - ref: http://fbrnc.net/blog/2011/05/make-your-magento-store-fly-using-varnish
        - menu block on the top-left corner
        - cart block on the right sidebar
        - and so on... as you are define on app/design/frontend/base/default/layout/nginxcache/nginxcache.xml

    - purge nginx cache
        - same event observer of turpentie varnish extension
        - ref: https://github.com/nexcess/magento-turpentine/blob/master/app/code/community/Nexcessnet/Turpentine/etc/config.xml#L320
        - In addition this extension utilize a nginx-cache-purge bash script internally
        - ref: https://github.com/perusio/nginx-cache-purge
        - <MAGENTO_ROOT>/app/shell/ngx_cache_purge.sh
        - purge event
            - magento cache clear
            - cache storage clear
            - each cache type refresh
            - product save event
            - cataloginventory stock item save
            - category save
            - cms page save
            - product review save

    - automatically crawl with/without purge
