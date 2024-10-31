<?php
/**
 * Update the locally cached INI files used by the Trap methods
 *
 * @package OwDstatsReloaded
 * @subpackage Views
 */

/**
 * Activate the dashboard pages, and append our styles and necessary scripts
 * only to these pages. Runs checks against the configuration settings to
 * allow access by other users to the stats or options. Blog owner (id 1)
 * automagically has access, but of course.
 *
 * @since 0.1.0
 * @category Views
 */

function owd_stats_add_pages() {
	global $owd_Stats, $current_user;

	get_currentuserinfo();

	// Just to cover our butts
	$min_to_view = ( isset( $owd_Stats->config['access']['minimum_to_view'] ) ? $owd_Stats->access_levels[$owd_Stats->config['access']['minimum_to_view']] : 'create_users' );
	$min_to_edit = ( isset( $owd_Stats->config['access']['minimum_to_edit'] ) ? $owd_Stats->access_levels[$owd_Stats->config['access']['minimum_to_edit']] : 'create_users' );


	if ( $current_user->ID == 1 || current_user_can( $min_to_view ) ) {
		add_object_page( 'OWD Statistics', 'Site Statistics', $min_to_view, 'owd_stats/statistics', 'owd_stats_display', OWD_stats_PLUGIN_URL . 'media/owd_stats.png' );

		$hook_overview = add_submenu_page( 'owd_stats/statistics', __('Blog Statistics - Overview', 'owd-site-statistics' ), __('Overview', 'owd-site-statistics' ), $min_to_view, 'owd_stats/statistics', 'owd_stats_display' );
			add_action( 'admin_print_styles-' . $hook_overview, 'owd_stats_print_styles' ); 
			add_action( 'admin_print_scripts-' . $hook_overview, 'owd_stats_print_scripts' ); 

		$hook_charts   = add_submenu_page( 'owd_stats/statistics', __('Blog Statistics - Charts', 'owd-site-statistics' ), __('Charts', 'owd-site-statistics' ), $min_to_view, 'owd_stats/charts', 'owd_stats_display' );
			add_action( 'admin_print_styles-' . $hook_charts, 'owd_stats_print_styles' ); 
			add_action( 'admin_print_scripts-' . $hook_charts, 'owd_stats_print_scripts' ); 

		if ( $current_user->ID == 1 || current_user_can( $min_to_edit ) ) {
			$hook_options  = add_submenu_page( 'owd_stats/statistics', __('Blog Statistics - Options', 'owd-site-statistics' ), __('Options', 'owd-site-statistics' ), $min_to_edit, 'owd_stats/options', 'owd_stats_display' );
				add_action( 'admin_print_styles-' . $hook_options, 'owd_stats_print_styles' ); 
				add_action( 'admin_print_scripts-' . $hook_options, 'owd_stats_print_scripts' ); 
		}

	}
	
}


/**
 * Apply our stylesheet and the Wordpress dashboard style sheets (for use
 * in displaying our postboxes)
 *
 * @since 0.1.0
 * @category Views
 */

function owd_stats_print_styles() {
	wp_enqueue_style( 'dashboard' );
	wp_enqueue_style( 'owd_stats', OWD_stats_PLUGIN_URL . 'media/global.css', '', OWD_stats_VERSION, 'all' );
}


/**
 * Append the Wordpress dashboard scripts to enable our postbox functionality.
 *
 * @since 0.6.0
 * @category Views
 */

function owd_stats_print_scripts() {
	wp_enqueue_script('dashboard');
}


/**
 * The primary view controller, this method is the only one used by owd_stats_add_pages
 * as it will determine which page we're looking for. If the plugin is in need of an
 * upgrade or has been disabled due to an uninstall, we'll block access from here
 * leaving the menu options intact. The majority of our debug information is also
 * displayed here.
 *
 * @since 0.4.0
 * @category Views
 */

function owd_stats_display () {
	global $wpdb, $owd_Stats;

	$page = ( $owd_Stats->upgrade !== FALSE ) ? '_upgrade' : $_GET['page'];

	if ( defined( 'OWD_stats_DISABLED' ) )
		$page = '_disabled';


	if ( substr( $page, 0, 1 ) != '_' ) {
		$tabs  = '<style type="text/css">';
		$tabs .= '#owd_stats-menu { display: inline; position: relative; }';
		$tabs .= '#owd_stats-menu a, #owd_stats-menu a.link, #owd_stats-menu a:visited { text-decoration: none; z-index: 1; margin: 0 auto; padding: 0 6px 0 6px; height: 22px; line-height: 22px; font-size: 10px; background-repeat: no-repeat; background-position: right bottom; color: #555; text-shadow: #fff -1px 1px 0; }';
		$tabs .= '#owd_stats-menu a:hover { color: #222; }';
		$tabs .= '.owd_stats-menu-link { float: right; background: transparent url("/wp-admin/images/screen-options-left.gif") no-repeat 0 0; font-family: "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif; height: 22px; padding: 0; margin: 0 6px 0 0; text-decoration: none; text-align:center; -moz-border-radius-bottomright: 2px; -khtml-border-radius-bottomright: 2px; -webkit-border-bottom-right-radius: 2px; border-bottom-right-radius: 2px; -moz-border-radius-bottomleft: 2px; -khtml-border-radius-bottomleft: 2px; -webkit-border-bottom-left-radius: 2px; border-bottom-left-radius: 2px; }';
		$tabs .= '</style>';

		$tabs .= '<ul id="owd_stats-menu">';
		$tabs .= ( $page != 'owd_stats/options' )    ? '<li class="owd_stats-menu-link"><a href="?page=owd_stats/options">' . __( 'Options', 'owd-site-statistics' ) . '</a></li>' : '';
		$tabs .= ( $page != 'owd_stats/charts' )     ? '<li class="owd_stats-menu-link"><a href="?page=owd_stats/charts">' . __( 'Charts', 'owd-site-statistics' ) . '</a></li>' : '';
		$tabs .= ( $page != 'owd_stats/statistics' ) ? '<li class="owd_stats-menu-link"><a href="?page=owd_stats/statistics">' . __( 'Overview', 'owd-site-statistics' ) . '</a></li>' : '';
		$tabs .= '</ul><div style="clear: right;"></div>';

		echo $tabs;
	}


	// I thought this was supposed to be handled automatically, but may only on add_options_pages?
	if ( isset( $_GET['updated'] ) && $_GET['updated'] == TRUE )
		echo '<div class="updated" style="padding: 2px 4px;">' . __( 'Your options have been saved.', 'owd-site-statistics' ) . '</div>';


	// @todo The notices functionality of our owd_stats class is rudimentary at best right now. But it works...
	if ( isset( $owd_Stats->notices ) && $page != '_upgrade' ) {
		foreach ( $owd_Stats->notices as $notice ) 
			echo '<p class="error">' . $notice . '</p>';
	}

	$bgpx = ( $page == '_upgrade' ? '22px' : '0' );

	echo '<div class="wrap" >';
	echo ( $page != '_disabled' ? '<div id="owd_stats-icon" style="background: transparent url(\''. OWD_stats_PLUGIN_MEDIA . 'k_icon.png\') no-repeat;" class="icon32"><br /></div>' : '' );


	if ( defined( 'OWD_stats_DEBUG' ) && OWD_stats_DEBUG == TRUE ) {

		echo '<div class="updated">';
		echo '<form action="" method="post">';
		echo '<label for="owd_stats_version" style="position: relative; top: -2px;">Current Version:</label> <input type="text" name="owd_stats_version" value="' . get_option( 'owd_stats_version' ) . '" />';
		echo '<input type="submit" class="button-secondary" name="owd_stats_debug" value="Change" />';
		echo '</form></div>';

		echo '<div class="updated" style="padding: 0.6em 1em; font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 15px;">';
		echo 'Defined Version: <strong>' . OWD_stats_VERSION . '</strong><br />';
		echo 'Current Timestamp: <strong>' . $owd_Stats->datetime->timestamp . '</strong><br />';
		echo 'Current Time: <strong>' . date( 'Y-m-d g:ia', $owd_Stats->datetime->timestamp ) . '</strong><br /><br />';
		echo '<strong><pre>' . print_r( get_option( 'owd_stats_options' ), TRUE ) . '</pre></strong>';
		echo '</div>';

	}


	switch ( $page ) {

		case '_disabled':
//			$output  = '<h2 style="margin-bottom: 1em;">' . __( 'OwDstats has been disabled', 'owd-site-statistics' ) . '</h2>';
			$output  = '<p style="margin-top: 2em; font-size: 110%"><strong>' . __( 'OwDstats has been disabled.', 'owd-site-statistics' ) . '</strong><br />';
			$output .= __( 'Your database tables or options may be corrupt, or you have performed an uninstall via the Options page.', 'owd-site-statistics' ) . '</p>';
			$output .= sprintf( __( 'In order for OwDstats to resume normal operation, you will have to deactivate and reactivate the plugin via the %sPlugins Page%s .', 'owd-site-statistics' ), '<a href="/wp-admin/plugins.php">', '</a>' ) . '</p>';
			echo $output;
			break;

		case '_upgrade':
		    echo '<h2 style="margin-bottom: 1em;">' . __( 'Upgrading OwDstats', 'owd-site-statistics' ) . '</h2>';
			require_once( OWD_stats_UTILITIES_BASE . '_upgrade.php' );
			break;

		case 'owd_stats/statistics':
		    echo '<h2 style="margin-bottom: 1em;">' . sprintf( __( 'Statistics Overview for %s', 'owd-site-statistics' ), get_bloginfo( 'title' ) ) . '</h2>';
			extract( owd_stats_get_totals() );
   			owd_stats_display_overview( $totals, $monthly, $daily );

			list( $graph_data, $max ) = owd_stats_get_graph_data( $owd_Stats->config['overview']['days_to_show'], $daily );
			owd_stats_display_bargraph( $graph_data, $max );
			owd_stats_display_recent_pageviews();
			break;

		case 'owd_stats/charts':
		    echo '<h2 style="margin-bottom: 1em;">' . __( 'Charts and Data', 'owd-site-statistics' ) . '</h2>';
			owd_stats_display_charts();
			break;

		case 'owd_stats/options':
		    echo '<h2 style="margin-bottom: 1em;">' . __( 'Options and Configuration', 'owd-site-statistics' ) . '</h2>';
			owd_stats_display_options();
			break;

	}


	if ( defined( 'OWD_stats_DEBUG' ) && OWD_stats_DEBUG == TRUE ) {

		echo '<div style="clear: both;"></div>';
		echo '<div class="error" style="padding: 0.6em 1em; font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 15px;">';
		echo '<strong><pre>' . print_r( $owd_Stats, TRUE ) . '</pre></strong>';
		echo '</div>';

	}

	echo '</div>'; // Close our .wrap

}


/**
 * Responsible for interpreting the data used by the charts and
 * generating the view for them. Determines the number of days to display
 * based on the array it's passed.
 *
 * @since 0.1.0
 * @category Views
 * @param array $daily An array of data used to build the graph
 * @param int   $max   Used to determine the scale used in our graph
 * @param bool  $ech   Whether to echo the output or return it
 */

function owd_stats_display_bargraph( $daily, $max, $ech = TRUE ) {
	global $owd_Stats;

	if ( ! is_array( $daily ) || empty( $daily ) )
		return;

	$max = ( $max == 0 ) ? 1 : $max;

	// Determine which chart we're displaying and what the config for it is
	$days = ( $_GET['page'] == 'owd_stats/statistics' ? $owd_Stats->config['overview']['days_to_show'] : $owd_Stats->config['dashboard']['days_to_show'] );


    $column_width  = ( 95 / $days ) . '%';

	$first_day = $days - 1;

	$output = '';

	if ( defined( 'OWD_stats_DEBUG' ) && OWD_stats_DEBUG == TRUE ) {
		$output .= '<div class="updated">';
		$output .= '<p>Max: <strong>' . $max . '</strong></p>';
		$output .= '<p>Days: <strong>' . $days . '</strong></p>';
		$output .= '<strong><pre>' . print_r( $daily, TRUE ) . '</pre></strong>';
		$output .= '</div>';
	}

	$output .= '<table class="owdstats" style="margin-bottom: 3em;"><tr><td style="border: none;">';
	$output .= '<table width="99%" style="margin: 6px auto 0; border-collapse: collapse;"><tr>';

    while ( $days-- ) {

		$current = date( 'Y-m-d', $owd_Stats->datetime->timestamp - 86400 * $days );

		$px_max = ( isset( $_GET['page'] ) && substr( $_GET['page'], 0, 6 ) == 'owd_stats' ) ? 200 : 140;
			
		if ( isset( $daily[$current] ) ) {
			$px_visitors  = round ( $daily[$current]['visitors'] * $px_max / $max );
			$px_pageviews = round ( $daily[$current]['pageviews'] * $px_max / $max );
			$px_spiders   = round ( $daily[$current]['spiders'] * $px_max / $max );
			$px_feeds     = round ( $daily[$current]['feeds'] * $px_max / $max );
	
			$px_white     = $px_max - $px_visitors - $px_pageviews - $px_spiders - $px_feeds;
		}
		else {
			$px_visitors = $px_pageviews = $px_spiders = $px_feeds = 0;
			$px_white = $px_max;
		}

		$prev_day = $owd_Stats->datetime->start_of_week - 1;

		$output .= '<td width="' . $column_width . '" style="border: 1px solid #dadada; border-bottom: none;';
		if ( $owd_Stats->datetime->start_of_week == date( 'w', $owd_Stats->datetime->timestamp - 86400 * $days ) && $days != $first_day )
			$output .= ' border-left: 2px dashed #dadada; padding-left: 1px; ';
		else if ( $prev_day == date( 'w', $owd_Stats->datetime->timestamp - 86400 * $days ) )
			$output .= ' padding-right: 1px; ';
		else
			$output .= '';
		$output .= '">';

		$output .= '<div style="float: left; height: 100%; width: 100%; line-height: 14px; font-size: 9px; font-family: Helvetica, Arial, sans-serif; text-align: center;">';
		$output .= '<div style="background: #fff url(' . OWD_stats_PLUGIN_MEDIA . 'graph.png) repeat; width: 100%; height: ' . $px_white . 'px;"></div>';
		$output .= '<div class="bg_visitors"  style="width: 100%; border-top-right-radius: 3px; border-top-left-radius: 3px; -moz-border-radius-topright: 3px; -moz-border-radius-topleft: 3px; -webkit-border-top-right-radius: 3px; -webkit-border-top-left-radius: 3px; height: ' . $px_visitors . 'px;"  title="' . $daily[$current]['visitors'] . ' ' . __( 'visitors', 'owd-site-statistics' ) . '"></div>';
		$output .= '<div class="bg_pageviews" style="width: 100%; height: ' . $px_pageviews . 'px;" title="' . $daily[$current]['pageviews'] . ' ' . __( 'pageviews', 'owd-site-statistics' ) . '"></div>';
		$output .= '<div class="bg_spiders"   style="width: 100%; height: ' . $px_spiders . 'px;"   title="' . $daily[$current]['spiders'] . ' ' . __( 'spiders', 'owd-site-statistics' ) . '"></div>';
		$output .= '<div class="bg_feeds"     style="width: 100%; height: ' . $px_feeds . 'px;"     title="' . $daily[$current]['feeds'] . ' ' . __( 'feeds', 'owd-site-statistics' ) . '"></div>';
		$output .= '<div style="margin-bottom: 5px; background: #dadada; width: 100%; height: 3px;"></div>';
		$output .= date('M j', $owd_Stats->datetime->timestamp - 86400 * $days) . '<br /><span style="color: #aaa;">' . date('D', $owd_Stats->datetime->timestamp - 86400 * $days) . '</div></td>';

/*		$output .= '<div class="sc_visitors"  style="width: 100%; height: ' . $px_visitors . 'px;"  title="' . $daily[$current]['visitors'] . ' ' . __( 'visitors', 'owd-site-statistics' ) . '"><span style="color: #fff;">' . $daily[$current]['visitors'] . '</span></div>';
		$output .= '<div class="sc_pageviews" style="width: 100%; height: ' . $px_pageviews . 'px;" title="' . $daily[$current]['pageviews'] . ' ' . __( 'pageviews', 'owd-site-statistics' ) . '"><span style="color: #fff;">' . $daily[$current]['pageviews'] . '</span></div>';
		$output .= '<div class="sc_spiders"   style="width: 100%; height: ' . $px_spiders . 'px;"   title="' . $daily[$current]['spiders'] . ' ' . __( 'spiders', 'owd-site-statistics' ) . '"><span style="color: #fff;">' . $daily[$current]['spiders'] . '</span></div>';
		$output .= '<div class="sc_feeds"     style="width: 100%; height: ' . $px_feeds . 'px;"     title="' . $daily[$current]['feeds'] . ' ' . __( 'feeds', 'owd-site-statistics' ) . '"><span style="color: #fff;">' . $daily[$current]['feeds'] . '</span></div>';
*/
	}

	$output .= '</tr></table>';
	$output .= '</td></tr></table>';

	if ( $ech === TRUE )
		echo $output;
	else
		return $output;

}


/**
 * Used to build our Wordpress postboxes
 *
 * @since 0.6.0
 * @category Views
 */

function owd_stats_build_postbox( $id, $title, $content, $ech = TRUE ) {

	$output  = '<div id="owd_stats_' . $id . '" class="postbox">';
	$output .= '<div class="handlediv" title="Click to toggle"><br /></div>';
	$output .= '<h3 class="hndle"><span>' . $title . '</span></h3>';
	$output .= '<div class="inside">';
	$output .= $content;
	$output .= '</div></div>';

	if ( $ech === TRUE )
		echo $output;
	else
		return $output;

}
