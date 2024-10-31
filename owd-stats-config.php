<?php



// Our database tables
define( 'OWD_stats_TABLE_RAW',       $wpdb->prefix . 'owd_stats_raw' ); // Raw data, like the name suggests
define( 'OWD_stats_TABLE_TOTALS',    $wpdb->prefix . 'owd_stats_totals' ); // Aggregate data, including totals, monthly and daily
define( 'OWD_stats_TABLE_CHARTS',    $wpdb->prefix . 'owd_stats_charts' ); // Clone of the raw data table for holding our aggregate chart data

// Path and URL definitions
define( 'OWD_stats_PLUGIN_BASE',  WP_PLUGIN_DIR . '/owd-site-statistics/' );
define( 'OWD_stats_PLUGIN_URL',   plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/' );
define( 'OWD_stats_PLUGIN_MEDIA', OWD_stats_PLUGIN_URL . 'media/' );

define( 'OWD_stats_ADMIN_BASE',     OWD_stats_PLUGIN_BASE . 'admin/' );
define( 'OWD_stats_DEF_BASE',       OWD_stats_PLUGIN_BASE . 'definitions/' );
define( 'OWD_stats_LIB_BASE',       OWD_stats_PLUGIN_BASE . 'lib/' );
define( 'OWD_stats_UTILITIES_BASE', OWD_stats_PLUGIN_BASE . 'utilities/' );

// Numerical Chart Codes for the Top Charts
define( 'OWD_stats_CHARTS_AGENTS',    10 );
define( 'OWD_stats_CHARTS_REFERRERS', 20 );
define( 'OWD_stats_CHARTS_SEARCHES',  30 );
define( 'OWD_stats_CHARTS_SPIDERS',   40 );

define( 'OWD_stats_INI_CACHE', OWD_stats_DEF_BASE . 'cache.ini' );
define( 'OWD_stats_INI_UA', OWD_stats_DEF_BASE . 'agents.ini' );
define( 'OWD_stats_INI_QUERY', OWD_stats_DEF_BASE . 'query.ini' );

define( 'OWD_stats_DEBUG', 0 );

if ( get_option( 'owd_stats_uninstalled' ) )
	define( 'OWD_stats_DISABLED', 1 );


// This lets us change the version information for testing purposes
if ( defined( 'OWD_stats_DEBUG' ) && OWD_stats_DEBUG == TRUE ) {

	if ( isset( $_POST['owd_stats_debug'], $_POST['owd_stats_version'] ) )
		update_option( 'owd_stats_version', $_POST['owd_stats_version'] );

	$wpdb->show_errors();

}




_owd_stats_load_library( OWD_stats_LIB_BASE );
_owd_stats_load_library( OWD_stats_ADMIN_BASE );

$owd_Stats = new owd_stats_reloaded;


/**
 * Add and manage all hooks
 */

if ( $owd_Stats->upgrade === FALSE  && ! defined( 'OWD_stats_DISABLED' ) ) {

	add_action( 'owd_stats_aggregate_hook', 'owd_stats_cleanup_handler' );
	add_action( 'send_headers', 'owd_stats_collector' ); // Collect them hits
}

// views.php
add_action( 'wp_dashboard_setup', 'owd_stats_add_dashboard_widget' );
add_action( 'admin_menu', 'owd_stats_add_pages' );


if ( ! isset( $_POST['owd_stats_upgrade'] ) )
	add_action( 'admin_init', array( $owd_Stats, 'init_options' ) ); // Init our options/settings

// widgets.php
add_action( 'widgets_init', 'owd_stats_load_widgets' );



/**
 * Check for any specific requirements of operation
 */

$ini_notice  = __( "Your OWD Stats INI files aren't currently writable.", 'owd-site-statistics' ) . '<br />';
$ini_notice .= '<br />' . sprintf( __( 'Please make sure that all %1$s files in your definitions directory %2$s are writable (chmod 0777) before continuing.', 'owd-site-statistics' ), '<code>.ini</code>', '<code>' . OWD_stats_DEF_BASE . '</code>' );

if ( ! is_writable( OWD_stats_INI_CACHE ) && ! chmod( OWD_stats_INI_CACHE, 0777 ) ) {
	$owd_Stats->set_notice( 'definitions', $ini_notice );
}

if ( ! is_writable( OWD_stats_INI_UA ) && ! chmod( OWD_stats_INI_UA, 0777 ) ) {
	$owd_Stats->set_notice( 'definitions', $ini_notice );
}


/**
 * Responsible for parsing and loading a library of files
 *
 * @since 0.5.0
 * @category Main
 * @subcategory Setup
 */

function _owd_stats_load_library( $library_folder = '' ) {

	if ( empty( $library_folder ) || ! file_exists( $library_folder ) )
		return FALSE;

	$library_files = array();

	if ( $library_dir = opendir( $library_folder ) ) {

		while ( ( $library_file = readdir( $library_dir ) ) !== FALSE ) {

			if ( substr( $library_file, 0, 1 ) == '.' || substr( $library_file, 0, 1 ) == '_' )
				continue;

			if ( is_dir( $library_folder . $library_file ) ) {

				if ( $library_subdir = opendir( $library_folder . $library_file ) ) {

					while ( ($library_subfile = readdir( $library_subdir ) ) !== false ) {

						if ( substr( $library_file, 0, 1 ) == '.' || substr( $library_file, 0, 1 ) == '_' )
							continue;

						if ( substr( $library_subfile, -4 ) == '.php' )
							$library_files[] = $library_file . '/' . $library_subfile;

					}

					closedir( $library_subdir );

				}

			}
			else {

				if ( substr($library_file, -4) == '.php' )
					$library_files[] = $library_file;

			}


		}

		closedir( $library_dir );

	}

	if ( empty( $library_files ) )
		return;

	sort( $library_files );

	foreach ( $library_files as $file )
		require_once( $library_folder . $file );

}
