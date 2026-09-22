<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'woo_cao_version' );
delete_option( 'woo_cao_lock' );
delete_option( 'woo_cao_stripe_multibanco_settings' );

foreach ( array( 'woo_cao_cron', 'woo_cao_cron_continue' ) as $woo_cao_hook ) {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $woo_cao_hook );
	}
	wp_clear_scheduled_hook( $woo_cao_hook );
}
