<?php
/**
 * OWD Statistics primary class. Handles all global functionality
 * such as versioning, management of the datetime class, configuration
 * settings and so on.
 *
 * @package OwDstatsReloaded
 * @subpackage Lib
 * @category OwDstats
 */

class owd_stats_reloaded {

	/**
	 * Our supplied version from the owd_stats_version option
	 * @var string
	 */

	public $version;

	/**
	 * The datetime object
	 * @var owd_stats_datetime
	 */

	public $datetime;

	/**
	 * Supplied by wp_options
	 * @var string
	 */

	public $blog_url;

	/**
	 * An array of configuration settings built from wp_option owd_stats_options
	 * @var array
	 */

	public $config;

	/**
	 * Placeholder for the upgrade check, holds either the upgrade number
	 * or false if none is available
	 * @var string|FALSE
	 */

	public $upgrade;

	/**
	 * Critical upgrades available
	 * @var array
	 */

	private $upgrades = array( '0.4.1',
							   '0.4.2',
							   '0.6.0',
							   '0.6.9',
							   '0.7.1'
							  );

	/**
	 * An array of access levels and the permissions group to check them against
	 * @var array
	 */

	public $access_levels = array( 'administrator' => 'create_users',
								   'editor' => 'publish_pages',
								   'author' => 'publish_posts',
								   'contributor' => 'edit_posts',
								   'subscriber' => 'read'
							      );

	/**
	 * An array of any internally set notices
	 * @var array
	 */

	private $notices = array();



	/**
	 * Our OwDstats object constructor, sets various required properties
	 * and loads the datetime class as an internal property. Since this
	 * is instantiated everytime the plugin is loaded, we also make
	 * sure our options are refreshed and if any upgrade is available.
	 *
	 * @since 0.4.6
	 */

	public function __construct() {

		// If no version is found in the supplied option, assume the earliest upgrade possible
		$this->version = get_option( 'owd_stats_version', '0.4.1' );

		$this->datetime = new owd_stats_datetime;

		$this->blog_url = get_bloginfo( 'url' );


		$this->refresh_options();

		$this->upgrade = $this->_check_for_upgrade();

		if ( $this->upgrade !== FALSE ) {

			// Only add our upgrade require notice on non owd_stats pages, as the upgrade
			// will kick in by itself on those.
			if ( substr( $_GET['page'], 0, 6 ) != 'owd_stats' ) 
				add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );

		}

	}


	/**
	 * Checks for an available upgrade by running the internal version (our
	 * OWD_stats_VERSION constant) against the supplied one (wp_option owd_stats_version).
	 * When none is found, upgrade is presumed handled and $this->upgrade is set
	 * to false.
	 *
	 * @since 0.4.7
	 * @return string|FALSE The available upgrade version, or false if none is found
	 */

	private function _check_for_upgrade() {

		// Check our internal version against the supplied one
		if ( version_compare( OWD_stats_VERSION, $this->version, '>' ) ) {

			// If the internal is greater, we need to see if there's an upgrade available
			if ( array_key_exists( $this->version, $this->upgrades ) ) {
				return $this->version;
			}
			else {

				// Iterate through the upgrades, and look for the next one up from our supplied version
				foreach ( $this->upgrades as $version ) {

					if ( version_compare( $version, $this->version, '>' ) )
						return $version;

				}

				// No specific upgrade found, update supplied version
				update_option( 'OWD_stats_VERSION', OWD_stats_VERSION );

			}

		}

		return FALSE;

	}


	/**
	 * Displays a notice alerting users of OwDstats that they're in need
	 * of an immediate upgrade for normal operation to continue.
	 *
	 * @since 0.6.0
	 */

	public function upgrade_notice() {
		echo '<div class="error"><p><strong>' . __( 'OwDstats Upgrade Required!', 'owd-site-statistics' ) . '</strong> ' . __( 'Please navigate to one of the OwDstats pages from the menu on your left and click on the "Upgrade Now!" button.', 'owd-site-statistics' ) . '</p></div>';
	}


	/**
	 * Builds the config array by first grabbing our options from the database
	 * then uses the first fragment as the config category title and the rest of
	 * the name as the description.
	 *
	 * @since 0.4.6
	 */

	public function refresh_options() {

		if ( $options = get_option( 'owd_stats_options' ) ) {

			$this->config['misc']['uas_opt_in'] = get_option( 'owd_stats_optin' );

			foreach ( $options as $option => $value ) {
				$parts = explode( '_', $option );
				$description = implode( '_', array_slice( $parts, 1 ) );
				$this->config[$parts[0]][$description] = $value;
			}

		}
		else {
			if ( ! defined( 'OWD_stats_DISABLED' ) )
				define( 'OWD_stats_DISABLED', 1 );
		}

	}


	/**
	 * Rudimentary internal messaging. This can be used to set warnings and other
	 * notices to be displayed to the user.
	 *
	 * @since 0.4.6
	 * @param string $type   The heading under which we're putting the notice
	 * @param string $notice The notice to store
	 */

	public function set_notice( $type, $notice ) {
		$this->notices[$type] = $notice;
	}


	/**
	 * Used to display a notice. Checks if notice is set and not empty before
	 * returning a value, so can be used regardless of notice status.
	 *
	 * @since 0.6.4
	 * @param string $type    The notice heading to look for
	 * @param string $class   The css class for the returned notice
	 * @param string $wrapper Should be one of p or div, but can be any HTML element
	 * @return string
	 */

	public function notices( $type, $class = 'error', $wrapper = 'p' ) {

		if ( isset( $this->notices[$type] ) && ! empty( $this->notices[$type] ) ) {
			$class = ( ! empty( $class ) ? ' class="' . $class . '"' : '' );
				
			return '<' . $wrapper.$class . '>' . $this->notices[$type] . '</' . $wrapper . '>';
		}

	}

	/**
	 * Initialize options array and set validation callback used by OwDstats
	 *
	 * @since 0.4.6
	 */
	 
	public function init_options() {
		register_setting( 'owd_stats_admin_options', 'owd_stats_options', array( $this, 'validate_options' ) );
	}


	/**
	 * Options validation ensures integrity of submitted data
	 *
	 * @since 0.4.6
	 * @param array $options Supplied by Wordpress, the form submission results
	 * @return array The validated and sanitized options array
	 */

	public function validate_options( $options ) {

		// Access levels validation - if submitted data isn't found in the
		// $access_levels property, defaults to 'subscriber'
		$options['access_minimum_to_view'] = ( array_key_exists( $options['access_minimum_to_view'], $this->access_levels ) ? $options['access_minimum_to_view'] : 'subscriber' );
		$options['access_minimum_to_edit'] = ( array_key_exists( $options['access_minimum_to_edit'], $this->access_levels ) ? $options['access_minimum_to_edit'] : 'subscriber' );


		// Validate database configuration, check the range against the supplied maximum limit.
		// These settings can be 0 to emulate unlimited
		$options['database_months_to_keep'] = ( is_numeric( $options['database_months_to_keep'] ) && (int) $options['database_months_to_keep'] >= 0 ? $options['database_months_to_keep'] : sprintf( '%d', $options['database_months_to_keep'] ) );
		$options['database_days_to_keep']   = ( is_numeric( $options['database_days_to_keep'] ) && (int) $options['database_days_to_keep'] >= 0 ? $options['database_days_to_keep'] : sprintf( '%d', $options['database_days_to_keep'] ) );
		$options['database_rows_to_keep']   = ( is_numeric( $options['database_rows_to_keep'] ) && (int) $options['database_rows_to_keep'] >= 0 ? $options['database_rows_to_keep'] : sprintf( '%d', $options['database_rows_to_keep'] ) );


		// Dashboard widget is on or off (bool)
		$options['dashboard_display_widget'] = ( $options['dashboard_display_widget'] == 1 ) ? 1 : 0;


		// These options must be within the expected range, and zero is not allowed
		$options['dashboard_days_to_show']       = $this->_validate_range( $options['dashboard_days_to_show'], 21 );
		$options['overview_days_to_show']        = $this->_validate_range( $options['overview_days_to_show'], 60 );
		$options['overview_recent_hits_to_show'] = $this->_validate_range( $options['overview_recent_hits_to_show'], 500 );

		// Our ignore list, might've been easier to do comma delimited, but it's more readable for the user
		// in the long run to put one item per line.
		if ( ! empty( $options['ignore_ip_list'] ) ) {

			$options['ignore_ip_list'] = explode( "\r\n", $options['ignore_ip_list'] );

			// IP fragments are allowed
			foreach ( $options['ignore_ip_list'] as $key => $ip ) {
				if ( ! owd_stats_validate_ip( $ip, FALSE ) )
					unset( $options['ignore_ip_list'][$key] );
			}

		}
		else {
			$option['ignore_ip_list'] = array();
		}

		return $options;

	}


	/**
	 * Used by our options validation callback to ensure that a given value
	 * is within a specific range. Defaults to the max setting when the supplied
	 * input falls outside of the range or is invalid.
	 *
	 * @since 0.4.6
	 * @param int  $input Our test value
	 * @param int  $high  The maximum allowed for our test value
	 * @return int The validated value or it's sanitized equivalent
	 */

	private function _validate_range( $input, $high ) {

		if ( function_exists( 'filter_var' ) ) {

			$options = array( 'options' => array( 'min_range' => 1, 'max_range' => $high ) );

			if ( filter_var( $input, FILTER_VALIDATE_INT, $options ) ) {
				return $input;
			}

		}
		else {

			$input = intval( $input ); // Force an integer

			if ( $input >= 1 && $input <= $high ) {
				return $input;
			}

		}

		return $high;
		
	}

}