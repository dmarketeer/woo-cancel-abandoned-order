<?php
/**
 * Main class of the plugin
 *
 * @package RVOLA\WOO\CAO
 **/

namespace RVOLA\WOO\CAO;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAO
 *
 * @package RVOLA\WOO\CAO
 */
class CAO {

	/**
	 * Cron event name.
	 */
	const CRON_EVENT = 'woo_cao_cron';

	/**
	 * Action Scheduler group.
	 */
	const CRON_GROUP = 'woo-cancel-abandoned-order';

	/**
	 * Number of orders processed per query.
	 */
	const BATCH_SIZE = 50;

	/**
	 * Default maximum number of orders processed per run (each cancellation sends e-mails and restocks).
	 */
	const MAX_PER_RUN = 200;

	/**
	 * Single event used to continue once the per-run limit is reached.
	 */
	const CONTINUE_EVENT = 'woo_cao_cron_continue';

	/**
	 * Lock option, avoids concurrent runs.
	 */
	const LOCK_OPTION = 'woo_cao_lock';

	/**
	 * Lock lifetime in seconds.
	 */
	const LOCK_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Prefix of the options storing the WOOCAO settings of gateways configured in the WOOCAO tab.
	 */
	const OPTION_PREFIX = 'woo_cao_';

	/**
	 * Storage in the class of gateways
	 *
	 * @var gateways.
	 */
	private $gateways;

	/**
	 * CAO constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		$this->add_field_gateways();
		$this->add_event_cron();
	}

	/**
	 * Adds control fields in the gateway.
	 * Hook available: 'woo_cao_gateways' / Adds a payment gateway for the control.
	 * Gateways without a classic settings page (Stripe) are configured in the WOOCAO tab instead.
	 */
	private function add_field_gateways() {

		$gateways_default = array(
			'cheque',
			'bacs',
			'stripe_multibanco',                  // Multibanco by WooCommerce Stripe Gateway.
			'multibanco_ifthen_for_woocommerce',  // Multibanco by IfthenPay (Webdados).
		);

		$this->gateways = apply_filters( 'woo_cao_gateways', $gateways_default );
		if ( ! $this->gateways || ! is_array( $this->gateways ) ) {
			return;
		}

		$stripe_ui    = defined( 'WC_STRIPE_VERSION' ) && version_compare( WC_STRIPE_VERSION, '5.8.0', '>=' );
		$tab_gateways = array();

		foreach ( $this->gateways as $gateway ) {
			if ( $stripe_ui && Stripe::handles( $gateway ) ) {
				$tab_gateways[] = $gateway;
			} else {
				add_filter( 'woocommerce_settings_api_form_fields_' . $gateway, array( $this, 'add_fields' ) );
			}
		}

		if ( $tab_gateways ) {
			new Stripe( $tab_gateways );
		}
	}

	/**
	 * Returns the WOOCAO settings of a gateway.
	 * Own option first (gateways configured in the WOOCAO tab), then the gateway settings.
	 *
	 * @param string $gateway gateway ID.
	 *
	 * @return array
	 */
	public static function gateway_options( $gateway ) {
		$options = get_option( self::OPTION_PREFIX . $gateway . '_settings' );
		if ( ! is_array( $options ) ) {
			$options = get_option( 'woocommerce_' . $gateway . '_settings' );
		}

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Add the spot in the WordPress cron.
	 * The schedule is only verified on back-end requests (admin, cron, CLI) to avoid a query on every front-end page.
	 */
	private function add_event_cron() {

		add_action( self::CRON_EVENT, array( $this, 'check_order' ), 10, 0 );
		add_action( self::CONTINUE_EVENT, array( $this, 'check_order' ), 10, 0 );

		if ( ! ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
			return;
		}

		// Check if Action Scheduler exist.
		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_get_scheduled_actions' ) ) {
			$pending = $this->pending_actions( self::CRON_EVENT );

			if ( ! $pending ) {
				wp_clear_scheduled_hook( self::CRON_EVENT );
				// $unique (Action Scheduler 3.6+) avoids a duplicate when two requests schedule at the same time.
				as_schedule_recurring_action(
					strtotime( 'yesterday 0 hour' ),
					HOUR_IN_SECONDS,
					self::CRON_EVENT,
					array(),
					self::CRON_GROUP,
					true
				);
			} elseif ( count( $pending ) > 1 ) {
				$this->cancel_duplicates( $pending );
			}
		} elseif ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
			wp_schedule_event(
				strtotime( 'yesterday 0 hours' ),
				'hourly',
				self::CRON_EVENT
			);
		}
	}

	/**
	 * Returns the IDs of the pending actions of a hook, the next one first.
	 *
	 * @param string $hook action hook.
	 *
	 * @return int[]
	 */
	private function pending_actions( $hook ) {
		return array_map(
			'intval',
			(array) as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'orderby'  => 'date',
					'order'    => 'ASC',
					'per_page' => 10,
				),
				'ids'
			)
		);
	}

	/**
	 * Keep only the next pending action and cancel the other ones.
	 * Recurring duplicates (created by concurrent requests, e.g. with version 2.1.0) would otherwise run forever.
	 *
	 * @param int[] $action_ids pending actions, the next one first.
	 */
	private function cancel_duplicates( $action_ids ) {
		foreach ( array_slice( $action_ids, 1 ) as $action_id ) {
			try {
				\ActionScheduler::store()->cancel_action( $action_id );
			} catch ( \Exception $e ) {
				// Already cancelled or run in the meantime.
				continue;
			}
		}
	}

	/**
	 * Use when the extension is disabled to clean the cron spot.
	 */
	public static function clean_cron() {

		foreach ( array( self::CRON_EVENT, self::CONTINUE_EVENT ) as $hook ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook );
			}
			wp_clear_scheduled_hook( $hook );
		}
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Main method that tracks options and orders pending payment.
	 * If the elements match (activation for the gateway, lifetime, command on hold), the system will cancel the command if it exceeds its time.
	 * Uses the WooCommerce CRUD API only, so it works with both HPOS and the legacy posts storage.
	 * Hook available: 'woo_cao_max_per_run' / Maximum number of orders processed per run (0 = unlimited).
	 */
	public function check_order() {

		if ( empty( $this->gateways ) || ! is_array( $this->gateways ) || ! $this->lock() ) {
			return;
		}

		$max_per_run = (int) apply_filters( 'woo_cao_max_per_run', self::MAX_PER_RUN );
		$remaining   = $max_per_run > 0 ? $max_per_run : PHP_INT_MAX;

		// Status to cancel.
		$woo_status = $this->woo_status();

		try {
			foreach ( $this->gateways as $gateway ) {
				$remaining -= $this->check_gateway( $gateway, $woo_status, $remaining );

				if ( $remaining <= 0 ) {
					// Limit reached: the remaining orders are handled a few minutes later.
					$this->schedule_continue();
					break;
				}
			}
		} finally {
			$this->unlock();
		}
	}

	/**
	 * Cancel the abandoned orders of a gateway.
	 *
	 * @param string $gateway    gateway ID.
	 * @param array  $woo_status status to cancel.
	 * @param int    $limit      maximum number of orders to process.
	 *
	 * @return int Number of orders processed.
	 */
	private function check_gateway( $gateway, $woo_status, $limit ) {
		$options = self::gateway_options( $gateway );
		if ( ! isset( $options['woocao_enabled'] ) || 'yes' !== $options['woocao_enabled'] ) {
			return 0;
		}

		$mode     = isset( $options['woocao_mode'] ) && 'hourly' === $options['woocao_mode'] ? 'hourly' : 'daily';
		$old_date = $this->limit_timestamp( $options, $mode );
		$old_date = (int) apply_filters( 'woo_cao_date_order', $old_date, $gateway, $mode );

		if ( $old_date <= 0 ) {
			return 0;
		}

		$processed = 0;
		$page      = 1;
		do {
			$batch = (int) min( self::BATCH_SIZE, $limit - $processed );

			// A numeric value is interpreted by WooCommerce as a UTC timestamp.
			$order_ids = wc_get_orders(
				array(
					'type'           => 'shop_order',
					'status'         => $woo_status,
					'payment_method' => $gateway,
					'date_created'   => '<' . $old_date,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'limit'          => $batch,
					'paged'          => $page,
					'return'         => 'ids',
				)
			);

			$skipped = 0;
			foreach ( $order_ids as $order_id ) {
				$processed++;
				if ( ! $this->cancel_order( $order_id ) ) {
					$skipped++;
				}
			}

			// Cancelled orders leave the result set; only skip over the ones still matching.
			if ( $skipped >= $batch ) {
				$page++;
			}
		} while ( count( $order_ids ) === $batch && $processed < $limit );

		return $processed;
	}

	/**
	 * Schedule a single extra run to continue after the per-run limit.
	 */
	private function schedule_continue() {
		$timestamp = time() + ( 5 * MINUTE_IN_SECONDS );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			$scheduled = function_exists( 'as_has_scheduled_action' )
				? as_has_scheduled_action( self::CONTINUE_EVENT )
				: false !== as_next_scheduled_action( self::CONTINUE_EVENT );

			if ( ! $scheduled ) {
				as_schedule_single_action( $timestamp, self::CONTINUE_EVENT, array(), self::CRON_GROUP, true );
			}
		} elseif ( ! wp_next_scheduled( self::CONTINUE_EVENT ) ) {
			wp_schedule_single_event( $timestamp, self::CONTINUE_EVENT );
		}
	}

	/**
	 * Prevents two runs at the same time (recurring action and continuation).
	 * add_option() is atomic thanks to the unique option_name index.
	 *
	 * @return bool True if the lock has been acquired.
	 */
	private function lock() {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		// Stale lock (fatal error during a previous run).
		$locked_at = (int) get_option( self::LOCK_OPTION );
		if ( $locked_at < time() - self::LOCK_TTL ) {
			update_option( self::LOCK_OPTION, time(), false );

			return true;
		}

		return false;
	}

	/**
	 * Release the lock.
	 */
	private function unlock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Returns the UTC timestamp before which orders are considered abandoned.
	 *
	 * @param array  $options gateway options.
	 * @param string $mode    'daily' or 'hourly'.
	 *
	 * @return int
	 */
	private function limit_timestamp( $options, $mode ) {
		if ( 'hourly' === $mode ) {
			$hours = isset( $options['woocao_hours'] ) ? absint( $options['woocao_hours'] ) : 0;

			return $hours > 0 ? time() - ( $hours * HOUR_IN_SECONDS ) : 0;
		}

		$days = isset( $options['woocao_days'] ) ? absint( $options['woocao_days'] ) : 0;
		if ( $days <= 0 ) {
			return 0;
		}

		// Midnight in the site timezone, N days ago.
		$date = new \DateTimeImmutable( 'today', wp_timezone() );

		return $date->modify( '-' . $days . ' days' )->getTimestamp();
	}

	/**
	 * Cancel the order.
	 *
	 * @param int $order_id order ID.
	 *
	 * @return bool True if the order has been cancelled.
	 */
	private function cancel_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( true !== apply_filters( 'woo_cao_before_cancel_order', true, $order_id, $order ) ) {
			return false;
		}

		$message = esc_html(
			apply_filters(
				'woo_cao_message_cancel_order',
				esc_html__( 'Cancellation of the order because payment not received at time.', 'woo-cancel-abandoned-order' )
			)
		);

		$result = $order->update_status( 'cancelled', $this->woocao_icon() . $message );

		do_action( 'woo_cao_cancel_order', $order_id );

		return (bool) $result;
	}

	/**
	 * Returns the status of the orders to be canceled.
	 *
	 * @return array
	 */
	private function woo_status() {
		$woo_status            = array();
		$woo_status_authorized = wc_get_order_statuses();

		$default_status = apply_filters( 'woo_cao_statustocancel', array( 'wc-on-hold' ) );

		if ( $default_status && is_array( $default_status ) ) {
			foreach ( $default_status as $status ) {
				if ( array_key_exists( $status, $woo_status_authorized ) ) {
					$woo_status[] = $status;
				}
			}
		}

		if ( empty( $woo_status ) ) {
			$woo_status[] = 'wc-on-hold';
		}

		return $woo_status;
	}

	/**
	 * Return the icon for status messages.
	 *
	 * @return string
	 */
	private function woocao_icon() {
		return sprintf( '<span class="woocao-icon" title="%s"></span>', esc_html__( 'Cancel Abandoned Order', 'woo-cancel-abandoned-order' ) );
	}

	/**
	 * Adds fields for gateways.
	 * Hook available: 'woo_cao-default_days' / Default value of the number of days for order processing.
	 *
	 * @param array $fields options.
	 *
	 * @return array
	 */
	public function add_fields( $fields ) {

		$new_fields = array(
			'woocao'         => array(
				'title'       => esc_html__( 'Cancel Abandoned Order', 'woo-cancel-abandoned-order' ),
				'type'        => 'title',
				'description' => '',
				'default'     => '',
			),
			'woocao_enabled' => array(
				'title'       => esc_html__( 'Enable/Disable', 'woo-cancel-abandoned-order' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Activation the automatic cancellation of orders.', 'woo-cancel-abandoned-order' ),
				'default'     => 'no',
				'description' => esc_html__( 'Enable this option to automatically cancel all "on Hold" orders that you have not received payment for.', 'woo-cancel-abandoned-order' ),
			),
			'woocao_mode'    => array(
				'title'   => esc_html__( 'Mode', 'woo-cancel-abandoned-order' ),
				'type'    => 'select',
				'label'   => esc_html__( 'Activation the automatic cancellation of orders.', 'woo-cancel-abandoned-order' ),
				'default' => 'daily',
				'options' => array(
					'hourly' => esc_html__( 'Hourly', 'woo-cancel-abandoned-order' ),
					'daily'  => esc_html__( 'Daily', 'woo-cancel-abandoned-order' )
				),
				'class'   => 'woo_cao-field-mode',
			),
			'woocao_hours'   => array(
				'title'       => esc_html__( 'Lifetime in hour', 'woo-cancel-abandoned-order' ),
				'type'        => 'number',
				'description' => esc_html__( 'Enter the number of hours (whole number) during which the system must consider a "pending" command as canceled.', 'woo-cancel-abandoned-order' ),
				'default'     => apply_filters( 'woo_cao_default_hours', '1' ),
				'placeholder' => esc_html__( 'days', 'woo-cancel-abandoned-order' ),
				'class'       => 'woo_cao-field-hourly woo_cao-field-moded',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			),
			'woocao_days'    => array(
				'title'       => esc_html__( 'Lifetime in days', 'woo-cancel-abandoned-order' ),
				'type'        => 'number',
				'description' => esc_html__( 'Enter the number of days that the system must consider a "on Hold" order as canceled.', 'woo-cancel-abandoned-order' ),
				'default'     => apply_filters( 'woo_cao_default_days', '15' ),
				'placeholder' => esc_html__( 'days', 'woo-cancel-abandoned-order' ),
				'class'       => 'woo_cao-field-daily woo_cao-field-moded',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			),
		);

		return array_merge( $fields, $new_fields );

	}

	/**
	 * Load assets CSS & JS only on the screens that need them.
	 *
	 * @param string $hook current admin page.
	 */
	public function assets( $hook ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? $screen->id : '';

		$is_settings = 'woocommerce_page_wc-settings' === $hook;
		$is_order    = in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );

		if ( ! $is_settings && ! $is_order ) {
			return;
		}

		wp_enqueue_style( 'woo_cao', plugins_url( 'assets/woo_cao.css', WOOCAO_FILE ), array(), WOOCAO_VERSION, 'all' );

		if ( $is_settings ) {
			wp_enqueue_script(
				'woo_cao',
				plugins_url( 'assets/woo_cao.js', WOOCAO_FILE ),
				array( 'jquery' ),
				WOOCAO_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}
}
