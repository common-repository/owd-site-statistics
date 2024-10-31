<?php
/**
 * OWD Statistics date and time management class. This does most of
 * necessary work on being instantiated, and provides a list of properties
 * used elsewhere in the plugin to determine the current timestamp,
 * week, month, and more.
 *
 * @package OwDstatsReloaded
 * @subpackage Lib
 * @category Datetime
 */

class owd_stats_datetime {

	/**
	 * Holds the start time for the process timer.
	 * @var int
	 */

	private $stopwatch;

	/**
	 * The timezone offset, as set by Wordpress
	 * @var int
	 */

	public $offset;

	/**
	 * First day of the week, as set by Wordpress
	 * @var int
	 */

	public $start_of_week;

	/**
	 * The current timestamp, pulled from the internal clock and adjusted by the offset
	 * @var int
	 */

	public $timestamp;

	/**
	 * An array of dates, including today (at offset 0) and the previous 7
	 * @var array
	 */

	public $last_week;

	/**
	 * Today's date (Y-m-d)
	 * @var string
	 */

	public $today;

	/**
	 * Yesterday's date (Y-m-d)
	 * @var string
	 */

	public $yesterday;

	/**
	 * This month's date (Y-m)
	 * @var string
	 */

	public $this_month;



	/**
	 * Datetime construction sets all internal properties, either
	 * from local methods or pulled from the wp_options table as
	 * necessary.
	 *
	 * @since 0.2.5
	 */

	public function __construct() {

		$this->offset         = get_option( 'gmt_offset' );
		$this->start_of_week  = get_option( 'start_of_week' );

		$this->timestamp      = $this->_gmtimestamp();
		$this->last_week      = $this->get_time_last_x_days( 7 );
		$this->today          = $this->last_week[0];
		$this->yesterday      = $this->last_week[1];
		$this->this_month     = substr( $this->today, 0, 7 ); 

	}


	/**
	 * Gets the unix formatted timestamp based on the internal clock
	 * and calculated from the Wordpress offset configuration.
	 *
	 * @since 0.2.5
	 */

	private function _gmtimestamp() {
		list( $month, $day, $year, $hour, $minute, $second ) = explode( ' ', gmdate( 'n j Y G i s' ) );
		return mktime( $hour + $this->offset, $minute, $second, $month, $day, $year );
	}


	/**
	 * Builds an array of the last X days, including the current
	 * day at index 0
	 *
	 * @ver 1.0.0
	 * @param int $x The number of days to return in addition to today
	 * @return array The array of the last X days
	 */

	public function get_time_last_x_days( $x = 21 ) {

		if ( ! is_numeric( $x ) )
			return;

		$last_x_days = array();

		$last_x_days[] = date( 'Y-m-d', $this->timestamp );

		for ( $i = 1; $i <= $x; $i++ )
			$last_x_days[] = date( 'Y-m-d', strtotime( "-{$i} days", $this->timestamp ) );

		return $last_x_days;

	}


	/**
	 * Builds an array of the last X months, including the current
	 * month at index 0
	 *
	 * @since 0.2.5
	 * @param int $x The number of months to return in addition to this month
	 * @return array The array of the last X months
	 */

	public function get_time_last_x_months( $x = 6 ) {

		if ( ! is_numeric( $x ) )
			return;

		$last_x_months = array();

		$last_x_months[] = date( 'Y-m', $this->timestamp );

		for ( $i = 1; $i <= $x; $i++ )
			$last_x_months[] = date( 'Y-m', strtotime( "-{$i} months", $this->timestamp ) );

		return $last_x_months;

	}


	/**
	 * Our process timer/benchmark, sets the class property $this->stopwatch
	 * for use by $this->end_timer()
	 *
	 * @since 0.2.5
	 */

	public function start_timer() {
		$mtime = explode( ' ', microtime() );
		$this->stopwatch = $mtime[1] + $mtime[0];
	}


	/**
	 * Looks for data in $this->stopwatch and uses it to calculate the execution
	 * time. Returns or echoes a formatted string displaying the result.
	 *
	 * @since 0.2.5
	 * @param bool   $ech If true, echoes the result, if false returns it
	 * @param string $msg The formatted string to return or display
	 */

	public function end_timer( $ech = TRUE, $msg = 'This page was created in %s seconds' ) {

		if ( ! isset( $this->stopwatch ) || empty( $this->stopwatch ) )
			return;

		$mtime = explode( ' ', microtime() );
		$total = ( ( $mtime[1] + $mtime[0] ) - $this->stopwatch );

		return ( $ech ) ? printf( $msg, $total ) : sprintf( $msg, $total );

	}

}