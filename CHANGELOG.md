# Changelog

## [2.2.0](https://github.com/dmarketeer/woo-cancel-abandoned-order/tree/2.2.0) - 2026-09-22
[Full Changelog](https://github.com/dmarketeer/woo-cancel-abandoned-order/compare/e2a12f5...2.2.0)

* ✔︎ Compatibility WP 7.1
* ✔︎ Compatibility WOO 11.1
* Fix / Hourly mode: the cut-off date was passed as a string, which `wc_get_orders()` reduces to day precision (orders were only cancelled from the previous day onwards)
* Fix / Dates are now computed as UTC timestamps (site timezone respected in daily mode)
* Fix / Use `wc_get_order()` with order IDs instead of the deprecated `$order->ID` magic property ("doing it wrong" notice for each order)
* Fix / Translations were never loaded (textdomain registered after `init`)
* Fix / Deactivation and uninstall now also remove the Action Scheduler action
* Perf / Orders are fetched by ID in batches of 50 instead of loading every order at once
* Perf / Removed `wp_cache_flush()` which emptied the whole object cache on every run
* Perf / Schedule check only on back-end requests, assets only on WooCommerce settings and order screens
* New / Multibanco support: Stripe (`stripe_multibanco`, WOOCAO tab) and IfthenPay by Webdados (`multibanco_ifthen_for_woocommerce`)
* New / Maximum of 200 orders per run (filter `woo_cao_max_per_run`), the rest is processed 5 minutes later
* New / Lock to avoid two runs at the same time
* New / WOOCAO tab supports several Stripe gateways; mode selector works per section
* New / Boot on `plugins_loaded` with `class_exists( 'WooCommerce' )` instead of `is_plugin_active()`
* New / Declare Cart & Checkout blocks compatibility
* Requirements: WP 6.5, WooCommerce 8.2, PHP 7.4

## [2.1.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/2.1.0) - 2025-07-15
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/2.0.0...2.1.0)

* Remove trademark
* HPOS Compatibility (by Radoš)


## [2.0.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/2.0.0) - 2021-11-19
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.9.0...2.0.0)

* New / Class Stripe - working basis for the next versions and to quickly fix the new version of Stripe with WOOCAO
* ✔ Compatibility WOO 5.9
* ✔ Compatibility WP 5.8

## [1.9.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.9.0) - 2021-02-10
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.8.1...1.9.0)

* ✔︎ Compatibility WOO 5.0
* ✔︎ Compatibility WP 5.7
* New / If the ActionScheduler extension exists (included in WooCommerce), use this library rather than wp-cron #11
* Updated / Allow status to be saved in the WooCommerce universe with the WooCommerce filter 'wc_order_statuses' #9

## [1.8.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.8.1) - 2020-08-24
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.8.0...1.8.1)

* ✔︎ Compatibility WOO 4.4
* ✔︎ Compatibility WP 5.5

## [1.8.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.8.0) - 2020-04-11
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.7.2...1.8.0)

* NEW / Filter 'woo_cao_message_cancel_order' to modify the order note for cancellation. Useful if you use the filter 'woo_cao_before_cancel_order'
* MOVE / filter #7 and rename clean + add WC_Order class in filter (more possibility)
* woo_cao_order_id filter added by Pexle Chris

## [1.7.2](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.7.2) - 2020-03-09
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.7.1...1.7.2)

* ✔︎ Compatibility WOO 4.0
* ✔︎ Compatibility WP 5.4

## [1.7.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.7.1) - 2020-01-22
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.7.0...1.7.1)

* ✔︎ Compatibility WOO 3.8

## [1.7.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.7.0) - 2019-10-23
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.6.1...1.7.0)

* ✔︎ Compatibility WP 5.3
* ✔︎ Compatibility WOO 3.8
* PHPDock missing
* Added an icon in the order notes to identify the author (WOOCAO) - not retroactive
* Escape i18n html
* DELETED / Restock option / WooCommerce the management since June 2018 ...

## [1.6.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.6.1) - 2019-06-04
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.6.0...1.6.1)

* FIX / Incorrect date format with time cancellation.

## [1.6.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.6.0) - 2019-05-07
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.5.1...1.6.0)

* NEW / Order status hook for the cancel process.
* MINOR / code style call Class external.

## [1.5.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.5.1) - 2019-04-03
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.5.0...1.5.1)

* ✔︎ Compatibility WP 5.2
* ✔︎ Compatibility WOO 3.6

## [1.5.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.5.0) - 2018-10-22
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.4.1...1.5.0)

* NEW / Filter 'woo_cao_date_order'. Change the calculation date for pending orders.
* ✔︎ Compatibility WP 5.0
* CHECK / End of support PHP 5.6 http://php.net/supported-versions.php
* USELESS / Options 'value' in field checkbox.

## [1.4.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.4.1) - 2018-10-03
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.4.0...1.4.1)

* UPDATED / Rename file updater
* FIX / Updater crash with older PHP < 7.0

## [1.4.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.4.0) - 2018-10-02
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.3.2...1.4.0)

* NEW / Class Updater for modifications
* NEW / The plugin can work in hours
* NEW / Method 'required' for class WP
* UPDATED / Explain in admin for restock
* NEW / Load assets by file
* ✔︎ Compatibility WooCommerce 3.5

## [1.3.2](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.3.2) - 2018-06-01
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.3.1...1.3.2)

* Minor / change requires version format
* New / translate file es_AR
* Fix / call translate files

## [1.3.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.3.1) - 2018-05-23
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.3.0...1.3.1)

* ✔︎ Compatibility WooCommerce 3.4.0

## [1.3.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.3.0) - 2018-03-01
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.2.1...1.3.0)

* NEW restock
* NEW hook do_action ‘woo_cao_cancel_order’
* NEW method ‘cancel_order’
* Rename var ‘orders_id’ by’ ‘orders’
* Rename method ‘cancel_order’ by ‘check_order’
* Delete notice deprecated hook
* Refactor class
* Refactor new instance
* Changelog WP

## [1.2.1](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.2.1) - 2018-02-24
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.2.0...1.2.1)

* Rename class + namespace
* Update change methode ‘load’ > ‘intance'
* FIX / plugin_row_meta (class in includes)
* MINOR / delete link github plugin_row_meta
* MINOR / update readme

## [1.2.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.2.0) - 2018-02-22
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.1.3...1.2.0)

* Deprecated hook `woo_cao-gateways` by `woo_cao_gateways`
* Deprecated hook `woo_cao-default_days` by `woo_cao_default_days`
* Wordpress conventions
* Move Class in includes`

## [1.1.3](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.1.3) - 2018-01-31
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.1.2...1.1.3)

* ✔︎ Compatibility WooCommerce 3.3.0

## [1.1.2](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.1.2) - 2017-10-30
[Full Changelog](https://github.com/rvola/woo-cancel-abandoned-order/compare/1.0.0...1.1.2)

* Add extension licence files
* Rename path plugin (WP)
* Move additional link (plugin_row_meta)
* Fix translate domain WP
* Fix translate domain WP (folder)

## [1.0.0](https://github.com/rvola/woo-cancel-abandoned-order/tree/1.0.0) - 2017-10-29

* Launch
