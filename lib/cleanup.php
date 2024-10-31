<?php
/**
 * OwDstats aggregate and cleanup methods. All the functions found here
 * are responsible for the routines which parse our raw data and turn
 * it into usable meta data in the totals table.
 *
 * @package OwDstatsReloaded
 * @subpackage Lib
 * @category Cleanup
 */


function owd_stats_cleanup_handler() {
	global $blog_id;

	// If and when this function is tripped, we can't afford for it to abort halfway through.
	ignore_user_abort( 1 );
	set_time_limit( 0 );

	$result = FALSE;

	if ( function_exists( 'fsockopen' ) ) {

		$async = array();
		$async['abspath'] = ABSPATH;

		if ( isset( $blog_id ) ) {
			$async['blog_id'] = $blog_id;
		}

		if ( ! $key = get_option( 'owd_stats_async' ) ) {
			$key = sha1( owd_stats_generate_key( 32, TRUE ) );
			add_option( 'owd_stats_async', $key );
		}

		$async['key'] = $key;

		$result = owd_stats_trigger_async( OWD_stats_PLUGIN_URL . 'lib/_async.php', $async );

	}


	if ( $result === FALSE ) {
		$rows_affected = owd_stats_cleanup();
	}


}


/**
 * Fires off the asynchronous http request to run the cleanup routine
 * in a background process. Two birds with one stone, if I'm lucky; no
 * additional load time on the users end, and all the time in the world
 * to complete.
 *
 * @since 0.7.1
 * @param string $url     Where the request should be sent
 * @param array  $params  An associative array of key => value pairs for the POST data
 * @param int    $timeout Time in seconds to wait for a response
 * @return bool True on success
 */

function owd_stats_trigger_async( $url = '', $params = array(), $timeout = 10, $method = 'POST' ) {

	if ( empty( $url ) )
		return FALSE;

	if ( ! empty( $params ) && is_array( $params ) ) {

		foreach ( $params as $key => &$val ) {

			if ( is_array( $val ) )
				$val = implode( ',', $val );

			$post_parts[] = $key . '=' . urlencode( $val );

		}

		$post = implode( '&', $post_parts );

	}


	$default_port = ( isset( $_SERVER['PORT'] ) ? $_SERVER['PORT'] : 80 );

	$method = ( $method == 'POST' ? 'POST' : 'GET' );

    $url = parse_url( $url );

    $fp = fsockopen( $url['host'], ( isset( $url['port'] ) ? $url['port'] : $default_port ), $errno, $errstr, $timeout );

	if ( $fp ) {

		$output  = $method . ' ' . $url['path'] . " HTTP/1.1\r\n";
		$output .= 'Host: ' . $url['host'] . "\r\n";
		$output .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$output .= 'Content-Length: ' . strlen( $post ) . "\r\n";
		$output .= "Connection: Close\r\n\r\n";

		if ( isset( $post ) )
			$output .= $post;

	    fwrite( $fp, $output );
	    fclose( $fp );
		
		return TRUE;

	}

	return FALSE;

}


/**
 * Responsible for completing our nightly aggregate of information. Runs our
 * helper methods first to gather their cumulative information on the untouched
 * raw stats, then grabs the totals in the same way our real time methods do and
 * replaces it into the totals table.
 *
 * @since 0.1.0
 * @todo This may run pretty fast, but it might be an idea to look into future versions running it through an asynchronous http request.
 * @todo It could easily load WP, set the user blog (on MU) and run our required tasks. Would definitely require intensive testing first.
 */

function owd_stats_cleanup() {
	global $wpdb, $owd_Stats;

	ignore_user_abort( 1 );
	set_time_limit( 0 );


	// Run cleanup on all our charts first
	owd_stats_cleanup_search_terms();
	owd_stats_cleanup_referrers();
	owd_stats_cleanup_agents();
	owd_stats_cleanup_spiders();


	// Find some data to work with...
	$arr = owd_stats_get_totals( 1 );


	// This is our primary aggregate data stored in the totals table
	$query_parts[] = sprintf( "( 'totals', '', '%d', '%d', '%d', '%d' )", $arr['totals']['visitors'], $arr['totals']['pageviews'], $arr['totals']['spiders'], $arr['totals']['feeds'] );

	foreach ( $arr['monthly'] as $month => $data )
		$query_parts[] = sprintf( "( 'monthly', '%s', '%d', '%d', '%d', '%d' )", $month, $data['visitors'], $data['pageviews'], $data['spiders'], $data['feeds'] );

	foreach ( $arr['daily'] as $day => $data )
		$query_parts[] = sprintf( "( 'daily', '%s', '%d', '%d', '%d', '%d' )", $day, $data['visitors'], $data['pageviews'], $data['spiders'], $data['feeds'] );

	$rows_affected = owd_stats_cleanup_large_query( OWD_stats_TABLE_TOTALS, $query_parts );  // This of course stays after debugging.


	// Check our configuration, keep only as many days as the user needs
	if ( isset( $owd_Stats->config['database']['days_to_keep'] ) && $owd_Stats->config['database']['days_to_keep'] != '0' ) {
		$last_day = $owd_Stats->datetime->get_time_last_x_days( $owd_Stats->config['database']['days_to_keep'] );

		$query[] = sprintf( "DELETE FROM `%s` WHERE type = 'daily' AND name < '%s'", OWD_stats_TABLE_TOTALS, $last_day[$owd_Stats->config['database']['days_to_keep']] );
		$wpdb->query( end( $query ) );
	}

	// Same thing for monthly data
	if ( isset( $owd_Stats->config['database']['months_to_keep'] ) && $owd_Stats->config['database']['months_to_keep'] != '0' ) {
		$last_month = $owd_Stats->datetime->get_time_last_x_months( $owd_Stats->config['database']['months_to_keep'] );

		$query[] = sprintf( "DELETE FROM `%s` WHERE type = 'monthly' AND name < '%s'", OWD_stats_TABLE_TOTALS, $last_month[$owd_Stats->config['database']['months_to_keep']] );
		$wpdb->query( end( $query ) );
	}


	// Delete old data from the raw table if desired... 
	if ( isset( $owd_Stats->config['database']['rows_to_keep'] ) && $owd_Stats->config['database']['rows_to_keep'] != '0' ) {

		$query[] = sprintf( "SELECT * FROM `%s` WHERE id <= %d ORDER BY id DESC LIMIT %d, 1", OWD_stats_TABLE_RAW, $arr['last_id'], $owd_Stats->config['database']['rows_to_keep'] );
		$last_row = $wpdb->get_var( end( $query ) );

		if ( ! is_null( $last_row ) ) {
			sprintf( "DELETE FROM `%s` WHERE id <= %d", OWD_stats_TABLE_RAW, $last_row );
			$wpdb->query( end( $query ) );
		}

	}

	// Set preserved to keep from counting duplicate data
	$query[] = sprintf( "UPDATE `%s` SET preserved = 1 WHERE id <= %d", OWD_stats_TABLE_RAW, $arr['last_id'] );
	$wpdb->query( end( $query ) );

	return $rows_affected;
}


/**
 * This will break up large queries into smaller queries to ensure that we
 * don't attempt to overload the database.
 *
 * @since 0.1.0
 * @param string $table  The name of the table we're running the query on
 * @param array  $values An indexed array of preformatted values to use
 * @param int    $per    How many sets of data to run in one query
 * @param string $type   One of REPLACE or INSERT
 */

function owd_stats_cleanup_large_query( $table, $values = array(), $fields = array(), $per = 300, $type = 'REPLACE' ) {
	global $wpdb;

	if ( empty( $table ) || empty( $values ) )
		return FALSE;
	else if ( $type != 'REPLACE' )
		$type = 'INSERT';

	$query_parts   = array();
	$total_inserts = count( $values );

	// More inserts than we're prepared to do in one query? 
	if ( $total_inserts > $per ) {
		$iterations = ceil( $total_inserts / $per );

		// Loop through and build an array with slices from the original array
		for ( $i = 1; $i <= $iterations; $i++ ) {
			$start = ( $i - 1 ) * $per;
			$query_parts[] = array_slice( $values, $start, $per );
		}

	}
	else {
		$query_parts[] = $values;
	}

	$rows_affected = array();

	$fields = ( empty( $fields ) ? '' : '( ' . implode( ', ', $fields ) . ' )' );

	foreach ( $query_parts as $query ) {

		if ( ! empty( $query ) && is_array( $query ) ) {

			$count = count( $query );

			$query = sprintf( "%s INTO `%s` %s VALUES %s", $type, $table, $fields, implode( ', ', $query ) );
			$wpdb->query( $query );

			$rows_affected[] = mysql_affected_rows();

		}

	}

	return array_sum( $rows_affected );

}


/**
 * Responsible for updating the OwDstats Top Searches aggregate information.
 *
 * @since 0.1.0
 */

function owd_stats_cleanup_search_terms() {
	global $wpdb;

	$tempkey = owd_stats_generate_key();

	$query[] = sprintf( 'CREATE TEMPORARY TABLE `%1$s%2$s`
					     SELECT %3$d AS chart, SUM( v.count ) AS count, v.search_terms, v.search_engine, v.url, v.referrer
					     FROM ( SELECT count( search_terms ) AS count, search_terms, search_engine, url, referrer
							    FROM `%4$s`
							    WHERE search_terms <> \'\' AND preserved IS NULL
							    GROUP BY search_terms
							    UNION ALL
							    SELECT count, search_terms, search_engine, url, referrer
							    FROM `%1$s`
								WHERE chart = %3$d )
					     AS v
					     GROUP BY v.search_terms
					     ORDER BY count DESC', OWD_stats_TABLE_CHARTS, $tempkey, OWD_stats_CHARTS_SEARCHES, OWD_stats_TABLE_RAW );

	$query[] = sprintf( "DELETE FROM `%s` WHERE chart = %d", OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_SEARCHES );

	$query[] = sprintf( 'INSERT INTO `%1$s` ( chart, count, search_terms, search_engine, url, referrer ) SELECT chart, count, search_terms, search_engine, url, referrer FROM `%1$s%2$s`', OWD_stats_TABLE_CHARTS, $tempkey );


	foreach ( $query as $q )
		$wpdb->query( $q );

}


/**
 * Responsible for updating the OwDstats Top Referrers aggregate information.
 *
 * @since 0.1.0
 */

function owd_stats_cleanup_referrers() {
	global $wpdb, $owd_Stats;

	$tempkey = owd_stats_generate_key();

	$query[] = sprintf( 'CREATE TEMPORARY TABLE `%1$s%2$s`
					     SELECT %3$d AS chart, SUM( v.count ) AS count, v.referrer, v.url
						 FROM ( SELECT count( referrer ) AS count, referrer, url 
								FROM `%4$s`
								WHERE referrer NOT LIKE \'%5$s%%\' AND referrer <> \'\' AND search_engine = \'\' AND url <> \'\' AND preserved IS NULL
								GROUP BY referrer
								UNION ALL
								SELECT count, referrer, url
								FROM `%1$s`
								WHERE chart = %3$d )
						 AS v
						 GROUP BY v.referrer
						 ORDER BY count DESC', OWD_stats_TABLE_CHARTS, $tempkey, OWD_stats_CHARTS_REFERRERS, OWD_stats_TABLE_RAW, $owd_Stats->blog_url );

	$query[] = sprintf( "DELETE FROM `%s` WHERE chart = %d", OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_REFERRERS );

	$query[] = sprintf( 'INSERT INTO `%1$s` ( chart, count, referrer, url ) SELECT chart, count, referrer, url FROM `%1$s%2$s`', OWD_stats_TABLE_CHARTS, $tempkey );


	foreach ( $query as $q )
		$wpdb->query( $q );

}


/**
 * Responsible for updating the OwDstats Top User Agents aggregate information.
 *
 * @since 0.1.0
 */

function owd_stats_cleanup_agents() {
	global $wpdb;

	$tempkey = owd_stats_generate_key();

	$query[] = sprintf( 'CREATE TEMPORARY TABLE `%1$s%2$s`
					     SELECT %3$d AS chart, SUM( v.count ) AS count, v.user_agent, v.os, v.browser
						 FROM ( SELECT count( user_agent ) AS count, user_agent, os, browser
								FROM `%4$s`
								WHERE user_agent <> \'\' AND CONCAT( os, browser ) <> \'\' AND preserved IS NULL
								GROUP BY user_agent
								UNION ALL
								SELECT count, user_agent, os, browser
								FROM `%1$s`
								WHERE chart = %3$d )
						 AS v
						 GROUP BY v.user_agent
						 ORDER BY count DESC', OWD_stats_TABLE_CHARTS, $tempkey, OWD_stats_CHARTS_AGENTS, OWD_stats_TABLE_RAW );

	$query[] = sprintf( "DELETE FROM `%s` WHERE chart = %d", OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_AGENTS );

	$query[] = sprintf( 'INSERT INTO `%1$s` ( chart, count, user_agent, os, browser ) SELECT chart, count, user_agent, os, browser FROM `%1$s%2$s`', OWD_stats_TABLE_CHARTS, $tempkey );


	foreach ( $query as $q )
		$wpdb->query( $q );

}


/**
 * Responsible for updating the OwDstats Top Crawlers aggregate information.
 *
 * @since 0.1.0
 */

function owd_stats_cleanup_spiders() {
	global $wpdb;

	$tempkey = owd_stats_generate_key();

	$query[] = sprintf( 'CREATE TEMPORARY TABLE `%1$s%2$s`
					     SELECT %3$d AS chart, SUM( v.count ) AS count, v.user_agent, v.spider, v.url
						 FROM ( SELECT COUNT( user_agent ) AS count, user_agent, spider, url
								FROM `%4$s`
								WHERE spider <> \'\' AND url <> \'\' AND url <> \'/robots.txt\' AND preserved IS NULL
								GROUP BY user_agent
								UNION ALL
								SELECT count, user_agent, spider, url
								FROM `%1$s`
								WHERE chart = %3$d )
						 AS v
						 GROUP BY v.user_agent
						 ORDER BY count DESC', OWD_stats_TABLE_CHARTS, $tempkey, OWD_stats_CHARTS_SPIDERS, OWD_stats_TABLE_RAW );

	$query[] = sprintf( "DELETE FROM `%s` WHERE chart = %d", OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_SPIDERS );

	$query[] = sprintf( 'INSERT INTO `%1$s` ( chart, count, user_agent, spider, url ) SELECT chart, count, user_agent, spider, url FROM `%1$s%2$s`', OWD_stats_TABLE_CHARTS, $tempkey );


	foreach ( $query as $q )
		$wpdb->query( $q );

}
