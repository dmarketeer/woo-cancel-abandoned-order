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
	 * Hook available: 'woo_cao-gateways' / Adds a payment gateway for the control.
	 */
	private function add_field_gateways() {

		$gateways_default = array(
			'cheque',
			'bacs',
		);

		$this->gateways = apply_filters( 'woo_cao_gateways', $gateways_default );
		if ( $this->gateways && is_array( $this->gateways ) ) {
			foreach ( $this->gateways as $gateway ) {
				if ( 'stripe' === $gateway && defined( 'WC_STRIPE_VERSION' ) && version_compare( WC_STRIPE_VERSION, '5.8.0', '>=' ) ) {
					new Stripe();
				} else {
					add_filter( 'woocommerce_settings_api_form_fields_' . $gateway, array( $this, 'add_fields' ) );
				}
			}
		}
	}

	/**
	 * Add the spot in the WordPress cron.
	 * The schedule is only verified on back-end requests (admin, cron, CLI) to avoid a query on every front-end page.
	 */
	private function add_event_cron() {

		add_action( self::CRON_EVENT, array( $this, 'check_order' ), 10 );

		if ( ! ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
			return;
		}

		// Check if Action Scheduler exist.
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$scheduled = function_exists( 'as_has_scheduled_action' )
				? as_has_scheduled_action( self::CRON_EVENT )
				: false !== as_next_scheduled_action( self::CRON_EVENT );

			if ( ! $scheduled ) {
				wp_clear_scheduled_hook( self::CRON_EVENT );
				as_schedule_recurring_action(
					strtotime( 'yesterday 0 hour' ),
					HOUR_IN_SECONDS,
					self::CRON_EVENT,
					array(),
					self::CRON_GROUP
				);
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
	 * Use when the extension is disabled to clean the cron spot.
	 */
	public static function clean_cron() {

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CRON_EVENT );
		}
		wp_clear_scheduled_hook( self::CRON_EVENT );
	}

	/**
	 * Main method that tracks options and orders pending payment.
	 * If the elements match (activation for the gateway, lifetime, command on hold), the system will cancel the command if it exceeds its time.
	 * Uses the WooCommerce CRUD API only, so it works with both HPOS and the legacy posts storage.
	 */
	public function check_order() {

		if ( empty( $this->gateways ) || ! is_array( $this->gateways ) ) {
			return;
		}

		// Status to cancel.
		$woo_status = $this->woo_status();

		foreach ( $this->gateways as $gateway ) {
			$options = get_option( 'woocommerce_' . $gateway . '_settings' );
			if ( ! is_array( $options ) || ! isset( $options['woocao_enabled'] ) || 'yes' !== $options['woocao_enabled'] ) {
				continue;
			}

			$mode     = isset( $options['woocao_mode'] ) && 'hourly' === $options['woocao_mode'] ? 'hourly' : 'daily';
			$old_date = $this->limit_timestamp( $options, $mode );
			$old_date = (int) apply_filters( 'woo_cao_date_order', $old_date, $gateway, $mode );

			if ( $old_date <= 0 ) {
				continue;
			}

			$page = 1;
			do {
				// A numeric value is interpreted by WooCommerce as a UTC timestamp.
				$order_ids = wc_get_orders(
					array(
						'type'           => 'shop_order',
						'status'         => $woo_status,
						'payment_method' => $gateway,
						'date_created'   => '<' . $old_date,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'limit'          => self::BATCH_SIZE,
						'paged'          => $page,
						'return'         => 'ids',
					)
				);

				$skipped = 0;
				foreach ( $order_ids as $order_id ) {
					if ( ! $this->cancel_order( $order_id ) ) {
						$skipped++;
					}
				}

				// Cancelled orders leave the result set; only skip over the ones still matching.
				if ( $skipped >= self::BATCH_SIZE ) {
					$page++;
				}
			} while ( count( $order_ids ) === self::BATCH_SIZE );
		}
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
			),
			'woocao_days'    => array(
				'title'       => esc_html__( 'Lifetime in days', 'woo-cancel-abandoned-order' ),
				'type'        => 'number',
				'description' => esc_html__( 'Enter the number of days that the system must consider a "on Hold" order as canceled.', 'woo-cancel-abandoned-order' ),
				'default'     => apply_filters( 'woo_cao_default_days', '15' ),
				'placeholder' => esc_html__( 'days', 'woo-cancel-abandoned-order' ),
				'class'       => 'woo_cao-field-daily woo_cao-field-moded',
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
