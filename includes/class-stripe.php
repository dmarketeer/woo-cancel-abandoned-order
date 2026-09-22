<?php

namespace RVOLA\WOO\CAO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Stripe
 * Settings tab for the Stripe gateways, which do not display the classic WooCommerce settings form.
 *
 * @package RVOLA\WOO\CAO
 */
class Stripe {

	/**
	 * Slug page options
	 */
	const SLUG = 'woocao';

	/**
	 * Stripe gateways handled by the tab, with the option storing their WOOCAO settings.
	 * The main Stripe gateway keeps its historical storage; the others use an own option
	 * so the Stripe plugin never overwrites them.
	 */
	const GATEWAYS = array(
		'stripe'            => 'woocommerce_stripe_settings',
		'stripe_multibanco' => CAO::OPTION_PREFIX . 'stripe_multibanco_settings',
	);

	/**
	 * Gateways displayed in the tab.
	 *
	 * @var array
	 */
	private $gateways;

	/**
	 * Stripe constructor
	 *
	 * @param array $gateways gateways IDs to display.
	 */
	public function __construct( $gateways = array( 'stripe' ) ) {
		$this->gateways = array_values( array_filter( (array) $gateways, array( __CLASS__, 'handles' ) ) );

		add_action( 'wc_stripe_gateway_admin_options_wrapper', array( $this, 'messageNewStripe' ) );
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addTab' ), 50, 1 );
		add_action( 'woocommerce_settings_tabs_' . self::SLUG, array( $this, 'display' ) );
		add_action( 'woocommerce_update_options_' . self::SLUG, array( $this, 'save' ) );
	}

	/**
	 * Is the gateway configured in the WOOCAO tab?
	 *
	 * @param string $gateway gateway ID.
	 *
	 * @return bool
	 */
	public static function handles( $gateway ) {
		return is_string( $gateway ) && isset( self::GATEWAYS[ $gateway ] );
	}

	/**
	 * Display a message if the user is using Stripe
	 */
	public function messageNewStripe() {
		printf( '<h2>%s</h2>', esc_html__( 'Cancel Abandoned Order', 'woo-cancel-abandoned-order' ) );
		printf(
			wp_kses_post(
			/*translators:%s link To Stripe tab*/
				__( 'We have moved the settings from WOOCAO to Stripe %s', 'woo-cancel-abandoned-order' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . self::SLUG ) ),
				esc_html__( 'here', 'woo-cancel-abandoned-order' )
			)
		);
	}

	/**
	 * Add WOOCAO in WooCommerce options
	 *
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function addTab( $tabs ) {
		$tabs[ self::SLUG ] = esc_html__( 'WOOCAO', 'woo-cancel-abandoned-order' );

		return $tabs;
	}

	/**
	 *Displays the settings fields
	 */
	public function display() {
		woocommerce_admin_fields( $this->fields() );
	}

	/**
	 *Save fields
	 */
	public function save() {
		woocommerce_update_options( $this->fields() );
	}

	/**
	 * Section title of a gateway.
	 *
	 * @param string $gateway gateway ID.
	 *
	 * @return string
	 */
	private function title( $gateway ) {
		if ( 'stripe_multibanco' === $gateway ) {
			return esc_html__( 'Stripe - Multibanco', 'woo-cancel-abandoned-order' );
		}

		return esc_html__( 'Stripe', 'woo-cancel-abandoned-order' );
	}

	/**
	 * WOOCAO fields for the Stripe gateways, one section per gateway.
	 *
	 * @return array
	 */
	private function fields() {
		$fields = array();

		foreach ( $this->gateways as $gateway ) {
			$base   = self::GATEWAYS[ $gateway ] . '[%s]';
			$prefix = 'woocao_' . $gateway . '_';

			$fields[ $prefix . 'title' ]      = array(
				'name'    => $this->title( $gateway ),
				'type'    => 'title',
				'desc'    => '',
				'default' => '',
			);
			$fields[ $prefix . 'enabled' ]    = array(
				'title'   => esc_html__( 'Enable/Disable', 'woo-cancel-abandoned-order' ),
				'type'    => 'checkbox',
				'desc'    => esc_html__( 'Enable this option to automatically cancel all "on Hold" orders that you have not received payment for.', 'woo-cancel-abandoned-order' ),
				'default' => 'no',
				'id'      => sprintf( $base, 'woocao_enabled' ),
			);
			$fields[ $prefix . 'mode' ]       = array(
				'title'   => esc_html__( 'Mode', 'woo-cancel-abandoned-order' ),
				'type'    => 'select',
				'default' => 'daily',
				'options' => array(
					'hourly' => esc_html__( 'Hourly', 'woo-cancel-abandoned-order' ),
					'daily'  => esc_html__( 'Daily', 'woo-cancel-abandoned-order' ),
				),
				'class'   => 'woo_cao-field-mode',
				'id'      => sprintf( $base, 'woocao_mode' ),
			);
			$fields[ $prefix . 'hours' ]      = array(
				'title'             => esc_html__( 'Lifetime in hour', 'woo-cancel-abandoned-order' ),
				'type'              => 'number',
				'desc'              => esc_html__( 'Enter the number of hours (whole number) during which the system must consider a "pending" command as canceled.', 'woo-cancel-abandoned-order' ),
				'default'           => apply_filters( 'woo_cao_default_hours', '1' ),
				'class'             => 'woo_cao-field-hourly woo_cao-field-moded',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
				'id'                => sprintf( $base, 'woocao_hours' ),
			);
			$fields[ $prefix . 'days' ]       = array(
				'title'             => esc_html__( 'Lifetime in days', 'woo-cancel-abandoned-order' ),
				'type'              => 'number',
				'desc'              => esc_html__( 'Enter the number of days that the system must consider a "on Hold" order as canceled.', 'woo-cancel-abandoned-order' ),
				'default'           => apply_filters( 'woo_cao_default_days', '15' ),
				'class'             => 'woo_cao-field-daily woo_cao-field-moded',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
				'id'                => sprintf( $base, 'woocao_days' ),
			);
			$fields[ $prefix . 'sectionend' ] = array(
				'type' => 'sectionend',
			);
		}

		return $fields;
	}

}
