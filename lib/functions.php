<?php
/**
 * Library of functions used throughout OwDstats. Includes all Trap related functions
 * and various helper methods.
 *
 * @package OwDstatsReloaded
 * @subpackage Lib
 * @category Functions
 */

/**
 * Attempts to determine our clients true IP address. Looks for HTTP_CLIENT_IP
 * first, for a proxy second and finally falls back on REMOTE_ADDR if none of
 * the other values bear fruit.
 * 
 * @since 0.4.7
 * @subcategory Trap
 * @return string Returns the best IP address found
 */

function owd_stats_trap_ip() {

	// This one *should* be trustworthy if present
	if ( getenv( 'HTTP_CLIENT_IP' ) )
		return $_SERVER['HTTP_CLIENT_IP'];

	// Otherwise we'll check if there's a proxy present
	if ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {

		$ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$ips = count( $ip );

		for ( $i = 0; $i < $ips; $i++ ) {
			if ( owd_stats_validate_ip( $ip[$i] ) )
				return trim( $ip[$i] );
		}

	}

	// Nothing else found
	return $_SERVER['REMOTE_ADDR'];

}


/**
 * IP Validation. Does some simple validation first, to see if we have what
 * looks like a valid IP string. If passed, we run it against a regex
 * that guarantees we're not only formatted correctly but are in a valid
 * range (0.0.0.0 to 255.255.255.255)
 *
 * @since 0.4.2
 * @subcategory Validate
 * @param string $ip   The IP address to validate
 * @param bool   $full Whether or not to expect a full address or fragment
 * @return bool  True on validation, false otherwise
 */
 
function owd_stats_validate_ip( $ip, $full = TRUE ) {

	// While the regex would catch extra groups, let's just accommodate
	// the possibility of typo's...
	$parts = explode( '.', $ip );

	if ( count( $parts ) > 4 )
		$parts = array_splice( $parts, 0, 4 );
	else if ( count( $parts ) < 4 && $full === TRUE )
		return FALSE;

	// If we're expecting a fragment, pad it for running through the regex
	if ( $full === FALSE ) {

		for ( $i = 0; $i < 4; $i++ )
			$parts[$i] = ( isset( $parts[$i] ) && ! empty( $parts[$i] ) ? $parts[$i] : '0' );

		$ip = implode( '.', $parts );

	}

	// Validation
	if ( preg_match( '~^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$~', $ip ) )
		return TRUE;

	return FALSE;

}


/**
 * Check the provided IP address against the list of ignored IPs
 *
 * @since 0.1.0
 * @subcategory Validate
 * @param int $ip The IP address to be checked
 * @return bool True on match, false otherwise
 */

function owd_stats_ignore_ip( $ip  = '') {
	global $owd_Stats;

	if ( empty( $ip ) || empty( $owd_Stats->config['ignore']['ip_list'] ) )
		return FALSE;

	foreach ( $owd_Stats->config['ignore']['ip_list'] as $ignore )
		if ( ! empty( $ignore ) && strpos( $ip, $ignore ) !== FALSE )
			return TRUE;

	return FALSE;

}


/**
 * Grab the requested URL and format it for use by the collector.
 *
 * @since 0.1.0
 * @subcategory Trap
 * @return string The formatted request URI
 */

function owd_stats_trap_url () {
	global $owd_Stats;

	$url = $_SERVER['REQUEST_URI'];

	if ( substr ( $url, 0, 2) == '/?' )
		$url = substr ( $url, 1 );
	else if ( $url == '/' )
		$url = $owd_Stats->blog_url . '/';

	return htmlentities( $url, ENT_QUOTES );
}


/**
 * Attempts to determine if our visitor is a search engine spider or not, using
 * the INI file provided by user-agent-string.info
 *
 * @since 0.5.0
 * @subcategory Trap
 * @param string $user_agent The user agent string to run the query against
 * @return FALSE|string Returns the name of the spider if matched, false otherwise
 */

function owd_stats_trap_spider( $user_agent = '' ) {

	if ( empty( $user_agent ) || ! file_exists( OWD_stats_INI_UA ) )
		return FALSE;

	$uas = parse_ini_file( OWD_stats_INI_UA, TRUE );

	foreach ( $uas['robots'] as $test ) {

		if ( $test[0] == $user_agent )
			return $test[1];

	}

	return FALSE;
}


/**
 * Attempts to determine whether or not the visitor is accessing a feed, and which type
 *
 * @since 0.1.0
 * @subcategory Trap
 * @param string $url The url string to run the query against
 * @return FALSE|string Returns the type of feed if matched, false otherwise
 *
 * @todo Not very happy with the use of get_bloginfo for this, need to find a better method
 */

function owd_stats_trap_feed( $url = '' ) {

	// Bail immediately if we can
	if ( empty( $url ) || strpos( $url, 'feed' ) === FALSE )
		return FALSE;

	if ( strpos( get_bloginfo( 'comments_atom_url' ), $url ) !== FALSE )
		return 'COMMENT ATOM';
	else if ( strpos( get_bloginfo( 'comments_rss2_url' ), $url ) !== FALSE )
		return 'COMMENT RSS';
	else if ( strpos( get_bloginfo( 'rdf_url' ), $url ) !== FALSE )
		return 'RDF';
	else if ( strpos( get_bloginfo( 'atom_url' ), $url ) !== FALSE )
		return 'ATOM';
	else if ( strpos( get_bloginfo( 'rss_url' ), $url ) !== FALSE )
		return 'RSS';
	else if ( strpos( get_bloginfo( 'rss2_url' ), $url ) !== FALSE )
		return 'RSS2';
	elseif ( strpos( 'wp-feed.php', $url ) !== FALSE )
		return 'RSS2';
	else if ( strpos( '/feed', $url ) !== FALSE )
		return 'RSS2';

	return FALSE;
}


/**
 * Attempts to determine the visitors Operating System based on their User Agent
 *
 * @since 0.1.0
 * @subcategory Trap
 * @param string $user_agent The user agent string to run the query against
 * @return FALSE|string Returns the name of their OS if matched, false otherwise
 */

function owd_stats_trap_agent( $user_agent = '' ) {

	if ( empty( $user_agent ) || ! file_exists( OWD_stats_INI_UA ) )
		return FALSE;

	$uas = parse_ini_file( OWD_stats_INI_UA, TRUE );

	$browser = array();

	foreach ( $uas['browser_reg'] as $test ) {
		if ( preg_match( $test[0], $user_agent, $info ) ) { // $info[1] contains version if found
			$browser['id'] = $test[1];
			break;
		}
	}

	// Grab the Browser detail
	if ( isset( $browser['id'] ) ) {
		if ( $uas['browser_type'][$uas['browser'][$browser['id']][0]][0] ) {
			$browser['type'] = $uas['browser_type'][$uas['browser'][$browser['id']][0]][0];
			$browser['name'] = $uas['browser'][$browser['id']][1] . ' ' . $info[1]; // Attach the browsers version to it's name
		}
	}


	// Grab our OS
	foreach ( $uas['os_reg'] as $test ) {
		if ( preg_match( $test[0], $user_agent ) ) {
			$os = $test[1];
			break;
		}
	}

	if ( isset( $os ) )
		$browser['os'] = $uas['os'][$os][1];

	if ( ! empty( $browser ) )
		return array( $browser['os'], $browser['name'] );

	return FALSE;

}


/**
 * Attempts to determine any search terms used to find the site, based on the referrer
 *
 * @since 0.1.0
 * @subcategory Trap
 * @param string $referrer The referrer string to run the query against
 * @return FALSE|string Returns the search terms if trapped, false otherwise
 */

function owd_stats_trap_search_terms( $referrer ) {

	if ( empty( $referrer ) || ! file_exists( OWD_stats_INI_QUERY ) )
		return FALSE;

	function trap_query( $referrer ) {
	
		$query_str = parse_url( $referrer, PHP_URL_QUERY );
	
		if ( ! empty( $query_str ) )
			return explode( '&', html_entity_decode( urldecode( $query_str ) ) );
	
		return FALSE;
	
	}

	$data = parse_ini_file( OWD_stats_INI_QUERY, TRUE );

	foreach ( $data as $url => $info ) {

		if ( strpos( $referrer, $url ) !== FALSE ) {

			$query_parts = trap_query( $referrer );
			$i = count( $query_parts );

			while ( $i-- ) {
				$parts = explode( '=', $query_parts[$i] );

				if ( is_array( $info['query'] ) ) {

					$queries = count( $info['query'] );

					while ( $queries-- ) {
						if ( $parts[0] == $info['query'][$queries] )
							return array( $info['engine'], htmlentities( $parts[1], ENT_QUOTES ) );
					}

				}
				else {
					if ( $parts[0] == $info['query'] )
						return array( $info['engine'], htmlentities( $parts[1], ENT_QUOTES ) );
				}

			}

		}

	}
	
	return FALSE;
}


/**
 * Nothing special, simply returns the string with an ellipsis on the end
 * if it exceeds the requested length.
 *
 * @since 0.1.0
 * @param string $string The string to truncate
 * @param int    $length The maximum length allowable in characters
 * @return string The original string, formatted if it exceeded $length, the same otherwise
 */

function owd_stats_truncate( $string, $length ) {
	global $owd_Stats;

	if ( $string == '/' || $string == $owd_Stats->blog_url . '/' )
		return __( 'Page: Home', 'owd-site-statistics' );

	$string = owd_stats_entities( $string );

	if ( $length > strlen( $string ) )
		return $string;

	return '<abbr title="' . $string . '">' . mb_substr( $string, 0, $length ) . '...</a>';
}


/**
 * All results from our database should already be safe, but just to make
 * sure this function works as an alias to htmlentities
 *
 * @since 0.7.1
 * @param string $string The string we're encoding
 * @return string The encoded string
 */

function owd_stats_entities ( $string ) {

	if ( version_compare( phpversion(), '5.2.3', '<' ) ) {
		return trim( htmlentities( html_entity_decode( $string ), ENT_QUOTES ) );
	}
	else {
		return trim( htmlentities( $string, ENT_QUOTES, 'UTF-8', FALSE ) );
	}
}


/**
 * Grabs the real time totals from the raw
 *
 * @ver 0.5.0
 * @category Database
 * @param int $days_to_total Should be grabbed from $owd_stats_options[settings_overview_chart|settings_dashboard_chart] depending on where the data is being displayed
 * @return array An array of data, including the charts days, totals, and $max for use in building the chart
 */

function owd_stats_get_totals ( $return_id = FALSE ) {
	global $wpdb, $owd_Stats;

	// Set up the structure
	$arr = array( 'totals'  => array(),
				  'monthly' => array(),
				  'daily'   => array(),
				 );

	$arr['totals']['visitors'] = 0;
	$arr['totals']['pageviews'] = 0;
	$arr['totals']['spiders'] = 0;
	$arr['totals']['feeds'] = 0;

	$arr['daily'][$owd_Stats->datetime->today]['visitors'] = 0;
	$arr['daily'][$owd_Stats->datetime->today]['pageviews'] = 0;
	$arr['daily'][$owd_Stats->datetime->today]['spiders'] = 0;
	$arr['daily'][$owd_Stats->datetime->today]['feeds'] = 0;

	// Look for data in our aggregate table first
	$query[] = sprintf( "SELECT type, name, visitors, pageviews, spiders, feeds FROM `%s` ORDER BY name ", OWD_stats_TABLE_TOTALS );
	$results = $wpdb->get_results( end( $query ), ARRAY_A );

	if ( ! is_null( $results ) ) {
		foreach ( $results as $data ) {
			if ( empty( $data['name'] ) )
				$arr['totals'] = array_slice( $data, 2 );
			else
				$arr[$data['type']][$data['name']] = array_slice( $data, 2 );
		}
	}

	// The id of the last row in the database as of right NOW().
	// This is used to ensure that during the cleanup, no data entered after the totals are pulled but
	// before our aggregate is completed is lost.
	$query[] = sprintf( "SELECT id FROM `%s` ORDER BY id DESC LIMIT 1", OWD_stats_TABLE_RAW );
	$last_id = $wpdb->get_var( end( $query ) );

	// Then start pulling it from our raw table, if there is any...
	$query[] = sprintf( "SELECT SUBSTR( timestamp, 1, 10 ) AS timestamp, COUNT( DISTINCT ip ) AS visitors, COUNT( timestamp ) AS pageviews FROM `%s` WHERE spider = '' AND preserved IS NULL AND id <= %d GROUP BY SUBSTR( timestamp, 1, 10 )", OWD_stats_TABLE_RAW, $last_id );
	$results = $wpdb->get_results( end( $query ), ARRAY_A );

	if ( ! is_null( $results ) ) {

		foreach ( $results as $row ) {
			if ( ! isset( $arr['daily'][$row['timestamp']]['visitors'] ) )
				$arr['daily'][$row['timestamp']]['visitors'] = 0;

			if ( ! isset( $arr['daily'][$row['timestamp']]['pageviews'] ) )
				$arr['daily'][$row['timestamp']]['pageviews'] = 0;

			$arr['daily'][$row['timestamp']]['visitors']  += $row['visitors'];
			$arr['daily'][$row['timestamp']]['pageviews'] += $row['pageviews'];


			$str_monthly = substr( $row['timestamp'], 0, 7 );

			if ( ! isset( $arr['monthly'][$str_monthly]['visitors'] ) )
				$arr['monthly'][$str_monthly]['visitors'] = 0;

			if ( ! isset( $arr['monthly'][$str_monthly]['pageviews'] ) )
				$arr['monthly'][$str_monthly]['pageviews'] = 0;

			$arr['monthly'][$str_monthly]['visitors']  += $row['visitors'];
			$arr['monthly'][$str_monthly]['pageviews'] += $row['pageviews'];


			$arr['totals']['visitors']  += $row['visitors'];
			$arr['totals']['pageviews'] += $row['pageviews'];
		}
	}


	$query[] = sprintf( "SELECT SUBSTR( timestamp, 1, 10 ) AS timestamp, COUNT( ip ) AS spiders FROM `%s` WHERE spider <> '' AND preserved IS NULL AND id <= %d GROUP BY SUBSTR( timestamp, 1, 10 )", OWD_stats_TABLE_RAW, $last_id );
	$results = $wpdb->get_results( end( $query ), ARRAY_A );

	if ( ! is_null( $results ) ) {
		foreach ( $results as $row ) {
			if ( ! isset( $arr['daily'][$row['timestamp']]['spiders'] ) )
				$arr['daily'][$row['timestamp']]['spiders'] = 0;

			$arr['daily'][$row['timestamp']]['spiders'] += $row['spiders'];


			$str_monthly = substr( $row['timestamp'], 0, 7 );

			if ( ! isset( $arr['monthly'][$str_monthly]['spiders'] ) )
				$arr['monthly'][$str_monthly]['spiders'] = 0;

			$arr['monthly'][$str_monthly]['spiders'] += $row['spiders'];


			$arr['totals']['spiders'] += $row['spiders'];
		}
	}

	$query[] = sprintf( "SELECT SUBSTR( timestamp, 1, 10 ) AS timestamp, COUNT( ip ) AS feeds FROM `%s` WHERE feed <> '' AND preserved IS NULL AND id <= %d GROUP BY SUBSTR( timestamp, 1, 10 )", OWD_stats_TABLE_RAW, $last_id );
	$results = $wpdb->get_results( end( $query ), ARRAY_A );

	if ( ! is_null( $results ) ) {
		foreach ( $results as $row ) {
			if ( ! isset( $arr['daily'][$row['timestamp']]['feeds'] ) )
				$arr['daily'][$row['timestamp']]['feeds'] = 0;

			$arr['daily'][$row['timestamp']]['feeds'] += $row['feeds'];


			$str_monthly = substr( $row['timestamp'], 0, 7 );

			if ( ! isset( $arr['monthly'][$str_monthly]['feeds'] ) )
				$arr['monthly'][$str_monthly]['feeds'] = 0;

			$arr['monthly'][$str_monthly]['feeds'] += $row['feeds'];


			$arr['totals']['feeds'] += $row['feeds'];
		}
	}

	ksort( $arr['monthly'] );
	ksort( $arr['daily'] );

	if ( $return_id !== FALSE )
		$arr['last_id'] = $last_id;

	return $arr; // Should be extracted on the other side, but can be used literally too.

}


/**
 * Using the array provided by owd_stats_get_totals, this will return
 * a slice from that array for use by the bar graph, along with the
 * $max value necessary to build it.
 *
 * @since 0.6.0
 * @subcategory Display
 * @param int   $days  The number of days of data we want for the graph
 * @param array $daily The array of data generated by owd_stats_get_totals
 * @return array Associative array containing the array slice and max value
 */

function owd_stats_get_graph_data( $days = 21, $daily ) {
	global $owd_Stats;

	if ( empty( $daily ) || ! is_array( $daily ) )
		return FALSE;

	// This is the index we're going to look for
	$search = date( 'Y-m-d', strtotime( "-{$days} days", $owd_Stats->datetime->timestamp ) );

	$slice = array_slice( $daily, array_search( $search, array_keys( $daily ) ) ); // ARRAY-FU!

	// Total each set from the slice to build our scale
	$max = array();

	foreach ( $slice as $data )
		$max[] = array_sum( $data );

	return array( $slice, max( $max ) );

}


/**
 * Returns an alphabetical string, for use in creating random
 * names on our temporary tables.
 *
 * @since 0.6.0
 * @param int $length Length of string to return
 * @return string The random string
 */

function owd_stats_generate_key( $length = 6, $special = FALSE) {

	$charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	if ( $special !== FALSE )
		$charset .= '0123456789~@#$%^*()_+-={}|]["';

	$key = '_'; 

	for ( $i = 0; $i < $length; $i++ )
		$key .= $charset[( mt_rand( 0, ( strlen( $charset ) - 1 ) ) )];

	return $key;

}
