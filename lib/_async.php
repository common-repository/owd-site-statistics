<?php

set_time_limit( 0 );

// Since the request should never be coming from anywhere but ourselves
if ( $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR'] )
	die;

// Make sure we have some data to work with
if ( ! isset( $_POST ) || empty( $_POST ) || ! isset( $_POST['key'] ) )
	die;


	define( 'ABSPATH', urldecode( $_POST['abspath'] ) );

	// Load wordpress
	require( ABSPATH . 'wp-load.php' );
	global $wpdb;


	// Check if we're running MU, if we are, switch to the appropriate blog
	if ( function_exists( 'switch_to_blog' ) && isset( $_POST['blog_id'] ) )
		switch_to_blog( $_POST['blog_id'] );


	// Last line of defense.
	if ( sprintf( '%s', $_POST['key'] ) !== sprintf( '%s', get_option( 'owd_stats_async' ) ) ) 
		die;


	// This should be loaded by WP, but just in case
	if ( ! function_exists( 'owd_stats_cleanup' ) )
		require( dirname( __FILE__ ) . '../owd-stats-reloaded.php' );


	// Run in the background!
	owd_stats_cleanup();


