<?php /*
Plugin Name: Visitor Statistics
Plugin URI: http://webdesign-suggestions.com/
Description: Site Statistics panel. Monitor your Visitors, Page views, search engine crawling and more
Version: 0.5.0
Author: Mark Waterous
Author URI: http://webdesign-suggestions.com/
*/

/**
 * Site Statistics panel. Monitor your Visitors, Page views, search engine crawling and more

 */

global $wpdb;

define( 'OWD_stats_VERSION', '1.0' );

register_activation_hook( __FILE__, 'owd_stats_activate' );
register_deactivation_hook( __FILE__, 'owd_stats_deactivate' );
register_uninstall_hook( __FILE__, 'owd_stats_uninstall' );


if ( isset( $_POST['owd_stats_uninstall'], $_POST['owd_stats_uninstall_confirm'] ) ) {
	owd_stats_uninstall();
	update_option( 'owd_stats_uninstalled', 1 );
}

require_once( 'owd-stats-config.php' );



 
function owd_stats_collector() {
	global $wpdb, $owd_Stats, $current_user;

	// Try to record stats regardless of complete page loads
	ignore_user_abort( 1 );

	get_currentuserinfo();

	// Don't record statistics for logged in administrators.
	/**	@todo This should be made configurable on the options page. Some blogs may have a lot of administrators and the owner could want to track them to? */
	if ( $current_user->ID == 1 || current_user_can( 'create_users' ) )
		return;

	
	$arr = array();

	$arr['ip'] = owd_stats_trap_ip();

	// Check the IP against our ignore list, exit if matched
	if ( owd_stats_ignore_ip( $arr['ip'] ) )
		return;

	$arr['url'] = owd_stats_trap_url(); // Request URI

	if ( strpos( $arr['url'], 'robots.txt' ) !== FALSE && $owd_Stats->config['misc']['uas_opt_in'] && isset( $_SERVER['HTTP_USER_AGENT'] ) )
		owd_stats_trigger_async( 'http://user-agent-string.info:80/rpc/botdetect.php', array( 'ua' => $_SERVER['HTTP_USER_AGENT'], 'adr' => $_SERVER['REMOTE_ADDR'] ), 1, 'GET' );

	// Don't collect stats for these areas of the site
	if ( preg_match( '~(robots\.txt|wp-content/(mu-)?(plugins|themes)|wp-admin)~', $arr['url'] ) )
		return;

	// File extensions that we want to ignore
	$arr['ext'] = pathinfo( $arr['url'], PATHINFO_EXTENSION );
	if ( ! empty( $arr['ext'] ) && preg_match( '~ico|css|js|jpe?g|png|gif~', $arr['ext'] ) )
		return;


	// Set variables
	$arr['referrer']   = ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], $owd_Stats->blog_url ) === FALSE ) ? htmlentities( $_SERVER['HTTP_REFERER'], ENT_QUOTES ) : '';
	$arr['user_agent'] = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? htmlentities( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) : '';


	// Default
	$arr['spider'] = $arr['os'] = $arr['browser'] = $arr['search_engine'] = $arr['search_terms'] = $arr['feed'] = FALSE;

	$arr['spider'] = owd_stats_trap_spider( $arr['user_agent'] );
         

	// Not a spider? Who is it? TELL ME.
	if ( $arr['spider'] === FALSE ) {
		$arr['feed']    = owd_stats_trap_feed( $arr['url'] );
        list( $arr['os'], $arr['browser'] ) = owd_stats_trap_agent( $arr['user_agent'] );
		list( $arr['search_engine'], $arr['search_terms'] ) = owd_stats_trap_search_terms( $arr['referrer'] );
	}


	$query  = sprintf( "INSERT /*! HIGH_PRIORITY */ INTO `%s` ( timestamp, ip, url, user_agent, referrer, spider, search_engine, search_terms, os, browser, feed, user ) ", OWD_stats_TABLE_RAW );
	$query .= sprintf( "VALUES ( '%s', INET_ATON( '%s' ), '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
								date( 'Y-m-d H:i:s', $owd_Stats->datetime->timestamp ),
								$arr['ip'],
								esc_sql( $arr['url'] ), // should be escaped when trapped
								esc_sql( $arr['user_agent'] ),
								esc_sql( $arr['referrer'] ),
								$arr['spider'],
								$arr['search_engine'],
								esc_sql( $arr['search_terms'] ),
								$arr['os'],
								$arr['browser'],
								$arr['feed'],
								$current_user->user_login ); // Data that isn't escaped is trusted as it's generated internally

	$wpdb->query( $query ); // RUN IT!

}


/**
 * The activation process, schedules the nightly cron event and creates the database
 * tables we'll be using to store our data. All tables are MyIsam for speed with the
 * single exception of InnoDB on our raw data table, as this should be the only table
 * we need to make use of row level locking on.
 *
 * @ver 0.5.0
 * @category Main
 * @subcategory Setup
 * @return void
 */

function owd_stats_activate() {
	global $wpdb, $owd_Stats;

    if ( version_compare( phpversion(), '5.0', '<' ) )
        wp_die( '<strong>We apologize, but OWD Stats Reloaded requires PHP 5 or above for normal operation.</strong><br />PHP 4 is outdated <a href="http://www.php.net/archive/2007.php#2007-07-13-1">by over 2 years now</a>, and you should contact your host and request that they upgrade your server immediately.' );

	if ( get_option( 'owd_stats_uninstalled' ) )
		delete_option( 'owd_stats_uninstalled' );

	if ( ! get_option( 'owd_stats_version' ) )
		update_option( 'owd_stats_version', OWD_stats_VERSION );

	$cron = strtotime( date( 'Y/m/d', strtotime( '+1 day', $owd_Stats->datetime->timestamp ) ) . ' 12:01am' );

	wp_schedule_event( $cron, 'daily', 'owd_stats_aggregate_hook' );

	$new_options = array( 'database_months_to_keep'      => 24,
						  'database_days_to_keep'        => 365,
						  'database_rows_to_keep'        => 2000,
						  'access_minimum_to_view'       => 'subscriber',
						  'access_minimum_to_edit'       => 'subscriber',
						  'dashboard_display_widget'     => 1,
						  'dashboard_days_to_show'       => 12,
						  'overview_days_to_show'        => 21,
						  'overview_recent_hits_to_show' => 20,
						  'ignore_ip_list'           => array( '127.0.0.1', '64.41.145.' )
						);

	update_option( 'owd_stats_options', $new_options );

	$query   = sprintf( "SHOW TABLES LIKE '%sowd_stats%%'", $wpdb->prefix );
	$results = $wpdb->get_results( $query, ARRAY_A );

	// We run this here if the tables don't exist, OR if the ugprade isn't going to run it itself.
	if ( count( $results ) != 3 || $owd_Stats->upgrade === FALSE )
		owd_stats_create_tables();

}


/**
 * Handles the creation of our database tables, used by both the activation
 * process and upgrade process.
 *
 * @since 0.6.0
 * @category Main
 * @subcategory Setup
 */

function owd_stats_create_tables() {
	global $wpdb;

	$charset = owd_stats_get_mysql_charset();

	if ( ! function_exists( 'dbDelta' ) )
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	// Determine our MySQL version, so we know if we can use varchar or if we have to use text
	// columns on pre 5.0.3 - varchar's should be as small as humanly possible, while still being able
	// to accept the possibility of extra long strings...

	$mysql5 = ( version_compare( mysql_get_server_info(), '5.0.3', '>' ) ? TRUE : FALSE );

	$tables['raw'] = "CREATE TABLE IF NOT EXISTS `" . OWD_stats_TABLE_RAW . "` (
						  `id` SERIAL,
						  `timestamp` datetime NOT NULL,
						  `ip` int(10) unsigned NOT NULL,
						  `url` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " NOT NULL,
						  `referrer` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " NOT NULL,
						  `user_agent` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " DEFAULT NULL,
						  `os` varchar(255) DEFAULT NULL,
						  `browser` varchar(255) DEFAULT NULL,
						  `search_engine` varchar(255) DEFAULT NULL,
						  `search_terms` varchar(255) DEFAULT NULL,
						  `spider` varchar(255) DEFAULT NULL,
						  `feed` enum('','ATOM','COMMENT ATOM','COMMENT RSS','RDF','RSS','RSS2')DEFAULT NULL,
						  `user` varchar(255) DEFAULT NULL,
						  `preserved` enum('1') DEFAULT NULL,
						  KEY `timestamp` (`timestamp`),
						  KEY `ip` (`ip`),
						  KEY `url` (`url`(255))
						) ENGINE=InnoDB " . $charset;

	/** @todo Should we be using meta tables to store os, browser, etc? This would reduce our table size due to the amount of empty fields... */

	$tables['charts'] = "CREATE TABLE IF NOT EXISTS `" . OWD_stats_TABLE_CHARTS . "` (
						  `chart` int(10) unsigned DEFAULT NULL,
						  `timestamp` datetime DEFAULT NULL,
						  `ip` int(10) unsigned DEFAULT NULL,
						  `url` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " DEFAULT NULL,
						  `referrer` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " DEFAULT NULL,
						  `user_agent` " . ( $mysql5 === TRUE ? 'varchar(1024)' : 'text' ) . " DEFAULT NULL,
						  `os` varchar(255) DEFAULT NULL,
						  `browser` varchar(255) DEFAULT NULL,
						  `search_engine` varchar(255) DEFAULT NULL,
						  `search_terms` varchar(255) DEFAULT NULL,
						  `spider` varchar(255) DEFAULT NULL,
						  `feed` enum('','ATOM','COMMENT ATOM','COMMENT RSS','RDF','RSS','RSS2') DEFAULT NULL,
						  `user` varchar(255) DEFAULT NULL,
						  `count` int(10) unsigned NOT NULL,
						  KEY `chart` (`chart`),
						  KEY `timestamp` (`timestamp`),
						  KEY `ip` (`ip`),
						  KEY `url` (`url`(255))
						) ENGINE=MyISAM " . $charset;


	$tables['totals'] = "CREATE TABLE IF NOT EXISTS `" . OWD_stats_TABLE_TOTALS . "` (
						   `type` enum('totals','monthly','daily') NOT NULL,
						   `name` varchar(12) NOT NULL,
						   `visitors` int(10) unsigned NOT NULL,
						   `pageviews` int(10) unsigned NOT NULL,
						   `spiders` int(10) unsigned NOT NULL,
						   `feeds` int(10) unsigned NOT NULL,
						   PRIMARY KEY `name` (`name`),
						   KEY `type` (`type`)
						 ) ENGINE=MyISAM " . $charset;

	foreach ( $tables as $table )
		dbDelta( $table );

}


/**
 * Determines the character set and collation to use in our database
 * based on the settings provided by Wordpress. Defaults to utf8 if
 * Wordpress data isn't defined.
 *
 * @since 0.6.0
 * @category Main
 * @subcategory Setup
 * @return string Formatted string with character collation for MySQL
 */

function owd_stats_get_mysql_charset() {
	global $wpdb;

	if ( defined('DB_CHARSET') && ! empty( $wpdb->charset ) ) {

		$charset = 'DEFAULT CHARACTER SET ' . $wpdb->charset;

		if ( ! empty( $wpdb->collate ) )
			$charset .= ' COLLATE ' . $wpdb->collate;

	}
	else {
		$charset = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_bin';
	}

	return $charset;
}


/**
 * Deactivation clears the scheduled cron hook. You must uninstall the plugin
 * via the Plugins page or via the plugin Options page to remove all data.
 *
 * @since 0.6.0
 * @category Main
 * @subcategory Setup
 */

function owd_stats_deactivate() {
	wp_clear_scheduled_hook( 'owd_stats_aggregate_hook' );
	delete_option( 'owd_stats_async' );
}




function owd_stats_uninstall() {
	global $wpdb;

	wp_clear_scheduled_hook( 'owd_stats_aggregate_hook' );

	delete_option( 'owd_stats_version' );
	delete_option( 'owd_stats_options' );
	delete_option( 'owd_stats_async' );
	if ( get_option( 'widget_owd_stat' ) )
		delete_option( 'widget_owd_stat' );

	$query   = sprintf( "SHOW TABLES LIKE '%sowd_stats%%'", $wpdb->prefix );
	$results = $wpdb->get_results( $query, ARRAY_A );

	$tables = array();

	foreach ( $results as $arr ) {
		$tmp = array_values( $arr );
		array_push( $tables, $tmp[0] );
	}

	foreach ( $tables as $table )
		$wpdb->query( "DROP TABLE IF EXISTS `" . $table . "`" );

}
