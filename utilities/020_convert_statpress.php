<?php

/**
 * The StatPress Reloaded conversion utility. Tested with SPR v1.5.21
 *
 * @package OwDstatsReloaded
 * @subpackage Utilities
 */

/**
 * @ignore
 */

if ( ! defined( 'OWD_stats_VERSION' ) ) die( 'Aaaaaaaaaarrrggghhh!' );

	// Display the postbox
	owd_stats_postbox_convert_statpress();


/**
 * Handles the creation of the options page postbox for displaying the conversion
 * utility. The postbox is created outside of the standard options subpackage to
 * allow removal if not needed by the user.
 *
 * @since 0.6.0
 * @category Options
 */

function owd_stats_postbox_convert_statpress() {
	global $wpdb;

	$output  = '<form action="" method="post">';
	$output .= '<input type="hidden" name="owd_stats_admin" value="true" />';

	if ( isset( $_POST['statpress_convert'], $_POST['statpress_table'] ) ) {

		$sptable = esc_sql( $_POST['statpress_table'] );

		if ( empty( $sptable ) || ! $wpdb->query( "SHOW TABLE STATUS LIKE '{$sptable}'" ) ) {
			$output .= '<div class="error">' . __( 'The table you were trying to convert could not be found. Please make sure that you entered the name properly.', 'owd-site-statistics' ) . '</div>';
		}
		else if ( is_null( $wpdb->get_row( "SELECT id, date, time, ip, urlrequested, agent, referrer, search, nation, os, browser, searchengine, spider, feed, user, timestamp FROM `{$sptable}` LIMIT 1" ) ) ) {
			$output .= '<div class="error">' . __( 'The table you were trying to convert does not look like a StatPress Reloaded table. Please make sure that you entered the name properly, and that it is a version of Statpress compatible with 1.5.21.', 'owd-site-statistics' ) . '</div>';
		}
		else {
			$trap = ( isset( $_POST['statpress_trap'] ) ? TRUE : FALSE );
			$output .= owd_stats_convert_from_statpress( $sptable, $trap );
			$converted = TRUE;
		}

	}

	if ( ! isset( $converted ) ) {

//		$output .= '<p class="aside"><strong>This has been tested with StatPress Reloaded version 1.5.21;</strong> using it with another version may have unexpected results.</p>';
		$output .= '<p>' . __( 'If you\'ve been running StatPress Reloaded and would like to convert your existing data to work with OwDstats, then this utility is for you!', 'owd-site-statistics' ) . '</p>';
		$output .= '<p>' . __( 'You won\'t lose any of your existing data either. Simply enter the name of your StatPress database table below, and OwDstats will handle the rest.', 'owd-site-statistics' ) . '</p>';

		$output .= '<p class="owd_stats_submit center"><input type="text" name="statpress_table" value="' . $wpdb->prefix . 'statpress" /></p>';
		$output .= '<p class="owd_stats_submit center"><input type="submit" name="statpress_convert" class="button-secondary" value="' . __( 'Convert Statistics', 'owd-site-statistics' ) . '" /></p>';

		$output .= '<p class="aside"><input type="checkbox" name="statpress_trap" value="1" /> Trap User Agents<br />OwDstats can more accurately determine what OS and Browser your visitors were using. Depending on the size of your statpress table, this may add an exponential amount of time to the conversion.</p>';
		$output .= '<p class="aside">' . sprintf( __( 'Once you are done with the conversion you can delete the file %s and this utility will no longer be displayed.', 'owd-site-statistics' ), '<code><abbr title="' . __FILE__ . '">' . basename( __FILE__ ) . '</abbr></code>' ) . '</p>';

	}

	$output .= '</form>';

	owd_stats_build_postbox( 'statpress', __( 'Convert From StatPress Reloaded', 'owd-site-statistics' ), $output );

}




/**
 * Runs the actual conversion process. Tested under StatPress v1.5.21 only!!
 *
 * @ver 0.3.0
 * @category Convert
 * @ignore
 */

function owd_stats_convert_from_statpress( $sptable = '', $trap = FALSE ) {
	global $wpdb, $owd_Stats;

	$output = '';

	// This could take some time, so lets not be hasty
	set_time_limit( 0 );

	$owd_Stats->datetime->start_timer();

	// Determine our MySQL version requirements
	$mysql5 = ( version_compare( mysql_get_server_info(), '5.0.3', '>' ) ? TRUE : FALSE );

	$query = array();

	$charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	$key = '_';
	for ( $i = 0; $i < 6; $i++ )
		$key .= $charset[( mt_rand( 0, ( strlen( $charset ) - 1 ) ) )];

	// Dump all our data into a temporary table that we can mangle without hurting the original
	$query[] = sprintf( 'CREATE TEMPORARY TABLE `%1$s%2$s` SELECT * FROM `%1$s`', $sptable, $key ); // SHOULD BE TEMPORARY AFTER TESTING

	$sptable .= $key;

	// Change only the fields we have to to get the data from one table to the next
	$query[] = sprintf( "UPDATE `%s` SET ip = INET_ATON( ip )", $sptable );
	$query[] = sprintf( "UPDATE `%s` SET urlrequested = '%s/' WHERE urlrequested = ''", $sptable, $owd_Stats->blog_url );

	$query[] = sprintf( "ALTER TABLE `%s`
					     CHANGE `timestamp` `timestamp_` TINYTEXT  NULL DEFAULT NULL ,
					     ADD `timestamp` DATETIME NOT NULL FIRST ,
					     CHANGE `urlrequested` `url` TEXT NULL DEFAULT NULL ,
						 CHANGE `searchengine` `search_engine` VARCHAR( 255 ) DEFAULT NULL ,
						 CHANGE `agent` `user_agent` TEXT NULL DEFAULT NULL ,
					     CHANGE `search` `search_terms` VARCHAR( 255 ) NULL DEFAULT NULL AFTER `search_engine`,
						 DROP `id`,
						 ADD COLUMN id SERIAL FIRST", $sptable, $collation ); // Dropping and re-adding this resets our numbering for a fresh database.

	// SQL-FU... what was the point of three date columns mr statpress?
	$query[] = sprintf( "UPDATE `%s` SET timestamp = CONCAT_WS( ' ', CONCAT_WS( '-', SUBSTR( date, 1, 4 ), SUBSTR( date, 5, 2 ), SUBSTR( date, 7, 2 ) ), time )", $sptable );

	$query[] = sprintf( "ALTER TABLE `%s` DROP `timestamp_`", $sptable );

	$query[] = sprintf( "TRUNCATE TABLE `%s`", OWD_stats_TABLE_RAW );
	$query[] = sprintf( "TRUNCATE TABLE `%s`", OWD_stats_TABLE_CHARTS );
	$query[] = sprintf( "TRUNCATE TABLE `%s`", OWD_stats_TABLE_TOTALS );

	foreach ( $query as $q )
		$wpdb->query( $q );


	// This takes a crapload of time to run on even small tables. 
	if ( $trap === TRUE ) {

		$query[] = sprintf( "SELECT /*! SQL_NO_CACHE */ user_agent FROM `%s` WHERE user_agent <> '' GROUP BY user_agent", $sptable );
		$results = $wpdb->get_results( end( $query ), ARRAY_A );

		foreach ( $results as $row ) {

			$spider = owd_stats_trap_spider( $row['user_agent'] );
	
			if ( $spider === FALSE ) {
				list( $os, $browser ) = explode( '|', owd_stats_trap_agent( $row['user_agent'] ) );
				$query[] = sprintf( "UPDATE `%s` SET os = '%s', browser = '%s' WHERE user_agent = '%s'", $sptable, $os, $browser, $row['user_agent'] );
				$wpdb->query( end( $query ) );
			}
			else {
				$query[] = sprintf( "UPDATE `%s` SET spider = '%s' WHERE user_agent = '%s'", $sptable, $spider, $row['user_agent'] );
				$wpdb->query( end( $query ) );
			}

		}

	}


	$query[] = sprintf( "INSERT INTO `%s` (id, timestamp, ip, url, referrer, user_agent, os, browser, search_engine, search_terms, spider, feed, user, preserved) SELECT id, timestamp, ip, url, referrer, user_agent, os, browser, search_engine, search_terms, spider, feed, user, NULL AS preserved FROM `%s` ORDER BY id", OWD_stats_TABLE_RAW, $sptable );
	$wpdb->query( end( $query ) );

	// The wheel has already been created...
	owd_stats_cleanup(); 

	// Sanitize all data from the StatPress table
	if ( ! function_exists( 'owd_stats_upgrade_encode' ) )
		require( '_upgrade.php' );

	owd_stats_upgrade_encode();

	$output .= '<p>' . $owd_Stats->datetime->end_timer( FALSE, __( 'Conversion completed in %s seconds.', 'owd-site-statistics' ) ) . '</p>';

	$output .= '<p class="aside">' . sprintf( __( 'You will continue to see this option on your administrative page until you delete the file %s from your plugins directory. If you would prefer to leave the option open to convert stats from StatPress again in the future, simply leave the file as is!', 'owd-site-statistics' ), '<code><abbr title="' . __FILE__ . '">' . basename( __FILE__ ) . '</abbr></code>' ) . '</p>';

	return $output;

}
