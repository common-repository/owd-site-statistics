<?php

/**
 * Update the locally cached INI files used by the Trap methods
 *
 * @package OwDstatsReloaded
 * @subpackage Utilities
 */

/**
 * @ignore
 */

if ( ! defined( 'OWD_stats_VERSION' ) ) die( 'Aaaaaaaaaarrrggghhh!' );

	// Display the postbox
	owd_stats_postbox_ini_update();


/**
 * Display the INI update utility used for downloading new definitions files from
 * the remote server. At present files are downloaded directly from user-agent-string.info
 * however in the near future when other updates are made available, all files will
 * be downloaded directly from our servers.
 *
 * @since 0.6.0
 * @category Options
 */

function owd_stats_postbox_ini_update() {
	global $owd_Stats;

	$output = '';

	// Check if a notice has been set, and append it
	$output .= $owd_Stats->notices( 'definitions' );


	// Display a notification if update has been performed
	if ( isset( $_POST['owd_stats_update_def'] ) ) {

		if ( owd_stats_utility_ini_update() )
			$output .= '<p class="updated">' . __( 'Your INI files have been updated successfully.', 'owd-site-statistics' ) . '</p>';
		else
			$output .= '<p class="error">' . __( 'There was a problem downloading the new INI files. Please try again later.', 'owd-site-statistics' ) . '</p>';

	}
	else if ( isset( $_POST['owd_stats_update_optin'] ) ) {

		check_admin_referer( 'owd_stats_optin' );

		$optin = ( $_POST['owd_stats_optin'] == 1 ? 1 : 0 );
		update_option( 'owd_stats_optin', $optin );

	}

	$owd_stats_parser_cache = parse_ini_file( OWD_stats_INI_CACHE );
	$last_updated = date( 'D, F jS \a\t g:ia', $owd_stats_parser_cache['lastupdate'] );

	$output .= '<form action="" method="post">';

	$output .= '<input type="hidden" name="owd_stats_admin" value="true" />';
	$output .= '<p class="aside">' . __( 'Last Update: ', 'owd-site-statistics' ) . '<strong>' . $last_updated . '</strong></p>';
	$output .= '<p>' . __( 'All INI files are downloaded from a remote server, allowing you to keep your definitions up to date without having to upgrade OwDstats itself.', 'owd-site-statistics' ) . '</p>';
	$output .= '<p class="owd_stats_submit center"><input type="submit" name="owd_stats_update_def" class="button-secondary" value="' . __( 'Update Definitions', 'owd-site-statistics' ) . '" /></p>';
	$output .= '</form>';


	$output .= '<form action="" method="post" style="margin-top: 2em">';
	$output .= wp_nonce_field( 'owd_stats_optin', '_wpnonce', 1, 0 );

	$output .= '<div class="aside"><p style="margin-top: 0"><input type="checkbox" name="owd_stats_optin" value="1" ' . checked( '1', get_option( 'owd_stats_optin' ), FALSE ) . ' /> ' . __( 'Help Find New Spiders', 'owd-site-statistics' ) . '</p>';
	$output .= '<p>' . sprintf( __( 'If you choose to opt in, OwDstats will automatically submit unknown user agents to the %s project in order to help more accurately identify Robots and Spiders crawling your web site.', 'owd-site-statistics' ), '<a href="http://user-agent-string.info/">user-agent-string.info</a>' ) . '</p>';
	$output .= '<p class="owd_stats_submit center"><input type="submit" name="owd_stats_update_optin" class="button-secondary" value="' . __( "Trap Spiders", 'owd-site-statistics' ) . '" /></p></div>';
	$output .= '</form>';



	owd_stats_build_postbox( 'update_def', __( 'Update Definitions', 'owd-site-statistics' ), $output );

}


/**
 * Updates the OwDstats definition INI files - currently only runs against the
 * user agents ini file from user-agent-string.info. In the future this should
 * also run an update against our own server to retrieve updated query.ini files
 *
 * @since 0.5.0
 * @ver 0.5.0
 *
 * @todo Should attempt to use cURL first, file_get_contents second
 */

function owd_stats_utility_ini_update() {

	$uas_ini_url = 'http://user-agent-string.info/rpc/get_data.php?key=free&format=ini';
	$uas_ver_url = $uas_ini_url . '&ver=y';

	if ( ini_get( 'allow_url_fopen' ) ) {

		$ctx = stream_context_create( array( 'http' => array( 'timeout' => 5 ) ) );

		$ver = @file_get_contents( $uas_ver_url, 0, $ctx );

		$cache_ini  = "[agents]\n";
		$cache_ini .= 'localversion = ' . $ver . "\n";
		$cache_ini .= 'lastupdate = ' . time() . "\n";

		@file_put_contents( OWD_stats_INI_CACHE, $cache_ini );

		if ( $ini = @file_get_contents( $uas_ini_url, 0, $ctx ) )
			return ( file_put_contents( OWD_stats_INI_UA, $ini ) ? TRUE : FALSE );

	}

	return FALSE;

}