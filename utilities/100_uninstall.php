<?php

/**
 * OwDstats uninstall utility. Uses the Setup function owd_stats_uninstall.
 *
 * @package OwDstatsReloaded
 * @subpackage Utilities
 */

/**
 * @ignore
 */

if ( ! defined( 'OWD_stats_VERSION' ) ) die( 'Aaaaaaaaaarrrggghhh!' );

	/** @todo Utilities should probably run themselves based on their filenames? */
	owd_stats_postbox_uninstall();


/**
 * Displays the uninstall utility on the Options page.
 * Uninstall is handled in the main plugin file, so that after completing
 * this process the plugin will be disabled.
 *
 * @since 0.6.0
 * @category Options
 */

function owd_stats_postbox_uninstall() {
	global $wpdb;

	$output  = '<form action="" method="post">';
	$output .= '<input type="hidden" name="owd_stats_admin" value="true" />'; // What is this for again?

	if ( isset( $_POST['owd_stats_uninstall'] ) && ! isset( $_POST['owd_stats_uninstall_confirm'] ) ) {
		$output .= '<p class="error">' . __( 'You must check the confirm box before continuing.', 'owd-site-statistics' ) . '</p>';
	}

	$output .= '<p>' . __( 'OwDstats requires some additional options and database tables in order to function. These features are not removed on deactivated to ensure that no data is lost unintentionally.', 'owd-site-statistics' ) . '</p>';
	$output .= '<p>' . __( 'If you are planning on removing OwDstats by hand instead of using the automatic delete feature, please be sure to run this uninstall utility first to remove all additional data.', 'owd-site-statistics' ) . '</p>';
	$output .= '<p class="aside"><input type="checkbox" name="owd_stats_uninstall_confirm" value="1" /> ' . __( 'Please confirm before proceeding.', 'owd-site-statistics' ) . '</p>';
	$output .= '<p class="owd_stats_submit center"><input type="submit" name="owd_stats_uninstall" class="button-secondary" value="' . __( 'Uninstall', 'owd-site-statistics' ) . '" /></p>';

	$output .= '</form>';

	owd_stats_build_postbox( 'statpress', __( 'Uninstall OwDstats', 'owd-site-statistics' ), $output );

}
