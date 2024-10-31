<?php

/**
 * Register our widget.
 * @since 0.5.0
 */

function owd_stats_load_widgets() {
	register_widget( 'WP_OwDstats_Widget' );
}

class WP_OwDstats_Widget extends WP_Widget {

	function WP_OwDstats_Widget() {
		$widget_ops = array( 'classname' => 'widget_owd_stat', 'description' => __( 'Display your statistics on the Wordpress sidebar.' ) );
		$control_ops = array( 'width' => 400, 'height' => 350 );
		$this->WP_Widget( 'owd_stats', __( 'OwDstats' ), $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {

		extract($args);

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? 'Blog Statistics' : $instance['title'] );
		$body  = apply_filters( 'owd_stats_body', $instance['body'] );

		echo $before_widget;
		echo $before_title . $title . $after_title;
		?>
			<div class="owd_stats_widget">
			<?php echo $this->parse_owd_stats_code( $body, $instance['filter'] ); ?>
            </div>
		<?php
		echo $after_widget;
	}

	function parse_owd_stats_code( $body, $filter = FALSE ) {
		global $wpdb, $owd_Stats;

		extract( owd_stats_get_totals() );

		// Pad the body for strpos, we'll trim this after.
		$body = '  ' . $body;

		// Parse all the totals data
		if ( strpos( strtolower( $body ), '%total_visitors%') )
			$body = str_ireplace( '%total_visitors%', $totals['visitors'], $body);

		if ( strpos( strtolower( $body ), '%total_pageviews%') )
			$body = str_ireplace( '%total_pageviews%', $totals['pageviews'], $body);

		if ( strpos( strtolower( $body ), '%total_spiders%') )
			$body = str_ireplace( '%total_spiders%', $totals['spiders'], $body);

		if ( strpos( strtolower( $body ), '%total_feeds%') )
			$body = str_ireplace( '%total_feeds%', $totals['feeds'], $body);


		if ( strpos( strtolower( $body ), '%todays_visitors%') )
			$body = str_ireplace( '%todays_visitors%', $daily[$owd_Stats->datetime->today]['visitors'], $body);

		if ( strpos( strtolower( $body ), '%todays_pageviews%') )
			$body = str_ireplace( '%todays_pageviews%', $daily[$owd_Stats->datetime->today]['pageviews'], $body);

		if ( strpos( strtolower( $body ), '%todays_spiders%') )
			$body = str_ireplace( '%todays_spiders%', $daily[$owd_Stats->datetime->today]['spiders'], $body);

		if ( strpos( strtolower( $body ), '%todays_feeds%') )
			$body = str_ireplace( '%todays_feeds%', $daily[$owd_Stats->datetime->today]['feeds'], $body);


		if ( strpos( strtolower( $body ), '%yesterdays_visitors%') )
			$body = str_ireplace( '%yesterdays_visitors%', $daily[$owd_Stats->datetime->yesterday]['visitors'], $body);

		if ( strpos( strtolower( $body ), '%yesterdays_pageviews%') )
			$body = str_ireplace( '%yesterdays_pageviews%', $daily[$owd_Stats->datetime->yesterday]['pageviews'], $body);

		if ( strpos( strtolower( $body ), '%yesterdays_spiders%') )
			$body = str_ireplace( '%yesterdays_spiders%', $daily[$owd_Stats->datetime->yesterday]['spiders'], $body);

		if ( strpos( strtolower( $body ), '%yesterdays_feeds%') )
			$body = str_ireplace( '%yesterdays_feeds%', $daily[$owd_Stats->datetime->yesterday]['feeds'], $body);


		if ( strpos( strtolower( $body ), '%thismonths_visitors%') )
			$body = str_ireplace( '%thismonths_visitors%', $monthly[$owd_Stats->datetime->this_month]['visitors'], $body);

		if ( strpos( strtolower( $body ), '%thismonths_pageviews%') )
			$body = str_ireplace( '%thismonths_pageviews%', $monthly[$owd_Stats->datetime->this_month]['pageviews'], $body);

		if ( strpos( strtolower( $body ), '%thismonths_spiders%') )
			$body = str_ireplace( '%thismonths_spiders%', $monthly[$owd_Stats->datetime->this_month]['spiders'], $body);

		if ( strpos( strtolower( $body ), '%thismonths_feeds%') )
			$body = str_ireplace( '%thismonths_feeds%', $monthly[$owd_Stats->datetime->this_month]['feeds'], $body);



		if ( $ip_macro = strpos( strtolower( $body ), '%ip%') || $host_macro = strpos( strtolower( $body ), '%hostbyaddr%') ) {

			$ip = owd_stats_trap_ip();

			if ( $ip_macro !== FALSE )
				$body = str_ireplace( '%ip%', $ip, $body);

			if ( $host_macro !== FALSE )
				$body = str_ireplace( '%hostbyaddr%', gethostbyaddr( $ip ), $body);
			
		}


		if ( $os_macro = strpos( strtolower( $body ), '%os%' ) || $browser_macro = strpos( strtolower( $body ), '%browser%' ) ) {

			$ua = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? htmlentities( $_SERVER['HTTP_USER_AGENT'], ENT_QUOTES ) : '';
			list( $os, $browser ) = owd_stats_trap_agent( $ua );

			if ( $os_macro !== FALSE )
				$body = str_ireplace( '%os%', $os, $body);

			if ( $os_browser !== FALSE )
				$body = str_ireplace( '%browser%', $browser, $body);

		}



		// Attempt to determine how many folks are online right now
		if ( strpos( strtolower( $body ), '%visitors_online%') ) {
			$time = date( 'Y-m-d G:i:s', strtotime( '-10 minutes', $owd_Stats->datetime->timestamp ) );
			$query = sprintf( "SELECT COUNT( DISTINCT ip ) FROM `%s` WHERE spider = '' AND timestamp >= '%s'", OWD_stats_TABLE_RAW, $time );
			$online = $wpdb->get_var( $query );
			$body = str_ireplace( '%visitors_online%', $online, $body);
		}

		return ( $filter == TRUE ? wpautop( trim( $body ) ) : trim( $body ) );
	}


	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );

		$instance['body'] = ( current_user_can( 'unfiltered_html' ) ? $new_instance['body'] : stripslashes( wp_filter_post_kses( $new_instance['body'] ) ) );

		$instance['filter'] = isset($new_instance['filter']);

		return $instance;
	}


	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => 'Blog Statistics', 'body' => 'Total Visitors: %total_visitors%' . "\r\n" . 'Currently Online: %visitors_online%', 'filter' => TRUE ) );
		$title = strip_tags( $instance['title'] );
		$body = format_to_edit( $instance['body'] );
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>

		<textarea class="widefat" rows="16" cols="20" id="<?php echo $this->get_field_id( 'body' ); ?>" name="<?php echo $this->get_field_name( 'body' ); ?>"><?php echo $body; ?></textarea>

		<p><input id="<?php echo $this->get_field_id( 'filter' ); ?>" name="<?php echo $this->get_field_name( 'filter' ); ?>" type="checkbox" <?php checked( $instance['filter'] ); ?> />&nbsp;<label for="<?php echo $this->get_field_id( 'filter' ); ?>"><?php _e( 'Automatically add paragraphs.' ); ?></label></p>

		<p><strong>Count Macros</strong></p>
        <p>Available prefixes:<br />
           <code>%total_</code><br />
           <code>%todays_</code><br />
           <code>%yesterdays_</code><br />
           <code>%thismonths_</code></p>
        <p>Available postfixes:<br />
           <code>visitors%</code><br />
           <code>pageviews%</code><br />
           <code>spiders%</code><br />
           <code>feeds%</code></p>
        <p>Example: <code>%total_visitors%</code> or <code>%yesterdays_visitors%</code></p>
		<p><strong>Visitor Macros</strong></p>
        <p><code>%ip%</code> - Visitors IP<br />
           <code>%hostbyaddr%</code> - Visitors Host<br />
           <code>%os%</code> - Visitors OS<br />
           <code>%browser%</code> - Visitors Browser<br />
           <code>%visitors_online%</code> - Visitors Online</p>
<?php

	}

}