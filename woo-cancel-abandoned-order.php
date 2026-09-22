<?php
/*
Plugin Name:		    Cancel Abandoned Order
Plugin URI:			    https://github.com/rvola/woo-cancel-abandoned-order

Description:		    Cancel "on hold" orders after a certain number of days or by hours

Version:			    2.2.0
Revision:			    2026-09-22
Creation:               2017-10-28

Author:				    RVOLA
Author URI:			    https://rvola.com

Text Domain:		    woo-cancel-abandoned-order
Domain Path:		    /languages

Requires Plugins:       woocommerce
Requires at least:      6.5
Tested up to:           7.1
Requires PHP:           7.4

WC requires at least:   8.2
WC tested up to:        11.1

License:                GNU General Public License v3.0
License URI:            https://www.gnu.org/licenses/gpl-3.0.html
*/

namespace RVOLA\WOO\CAO;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOOCAO_FILE', __FILE__ );
define( 'WOOCAO_VERSION', '2.2.0' );

// Declare compatibility with WooCommerce features (HPOS & Cart/Checkout blocks).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOCAO_FILE, true );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WOOCAO_FILE, true );
		}
	}
);

// Boot once all plugins are loaded, so WooCommerce is available whatever its folder name.
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once dirname( WOOCAO_FILE ) . '/includes/class-wp.php';
		WP::instance();
	}
);

register_deactivation_hook(
	WOOCAO_FILE,
	function () {
		require_once dirname( WOOCAO_FILE ) . '/includes/class-cao.php';
		CAO::clean_cron();
	}
);
