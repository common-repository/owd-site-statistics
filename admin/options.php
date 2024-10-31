<?php

function owd_stats_options_update_cron( $new_schedule = 'daily' ) {
	global $owd_Stats;

	if ( in_array( $new_schedule, array( 'daily', 'twicedaily', 'hourly' ) ) ) {
		$old_schedule = wp_get_schedule( 'owd_stats_aggregate_hook' );

		if ( $new_schedule != $old_schedule ) {
			wp_unschedule_event( wp_next_scheduled( 'owd_stats_aggregate_hook' ), 'owd_stats_aggregate_hook' );

			switch ( $new_schedule ) {
				case 'daily':
					$cron = strtotime( date( 'Y/m/d', strtotime( '+1 day', $owd_Stats->datetime->timestamp ) ) . ' 12:01am' );
					break;

				case 'twicedaily':
					$cron = strtotime( date( 'Y/m/d', strtotime( '+12 hours', $owd_Stats->datetime->timestamp ) ) . ' 12:01am' );
					break;

				case 'hourly':
					$cron = strtotime( date( 'Y/m/d g:ia', strtotime( '+1 hour', $owd_Stats->datetime->timestamp ) ) );
					break;

			}

			wp_schedule_event( $cron, $new_schedule, 'owd_stats_aggregate_hook' );

		}

	}

}



function owd_stats_display_options() {
	global $owd_Stats;

	$owd_Stats->refresh_options();

	if ( isset( $_POST['owd_stats_scheduler'] ) )
		owd_stats_options_update_cron( $_POST['owd_stats_scheduler'] );

?>
	<div class="postbox-container" >
	<div class="metabox-holder">	 	
	<div class="meta-box-sortables">
    	<form action="" id="owdadmin" method="post">
        <?php
			owd_stats_postbox_main_display_scheduler();
		?>
        </form>

		<form action="options.php" id="owdadmin" method="post">
		<?php
        	settings_fields('owd_stats_admin_options');

			owd_stats_postbox_main_display_options();
			owd_stats_postbox_main_database_options();
			owd_stats_postbox_main_access_control();
			owd_stats_postbox_main_ignore_lists()
		?>
		</form>
	</div></div>
	</div> <!-- .postbox-container -->

	<?php /*?><div class="postbox-container" style="width: 24%;">
	<div class="metabox-holder">	
	<div class="meta-box-sortables">
	<?php
		owd_stats_postbox_support();
		_owd_stats_load_library( OWD_stats_UTILITIES_BASE );
	?>
	</div></div>
	</div> <!-- .postbox-container --><?php */?>

<?php

}


function owd_stats_postbox_main_display_scheduler() {
	global $owd_Stats;


	$schedule = wp_get_schedule( 'owd_stats_aggregate_hook' );

	$options = array();

	$options[] = array( 'id'    => 'owd_stats_scheduler',
					    'name'  => __( 'Aggregate Schedule:', 'owd-site-statistics' ),
						'desc'  => __( 'Determines the interval between runs. For higher traffic sites this should be run more often.', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_scheduler" type="radio" value="daily" ' . checked( 'daily', $schedule, FALSE ) . ' /><span>' . __( 'Daily', 'owd-site-statistics' ) . '</span>' .
								   '<input name="owd_stats_scheduler" type="radio" value="twicedaily" ' . checked( 'twicedaily', $schedule, FALSE ) . ' /><span>' . __( 'Twice Daily', 'owd-site-statistics' ) . '</span>' .
								   '<input name="owd_stats_scheduler" type="radio" value="hourly" ' . checked( 'hourly', $schedule, FALSE ) . ' /><span>' . __( 'Hourly', 'owd-site-statistics' ) . '</span>'
					   );


	$output  = '<div class="intro">';
	$output .= '<p>' . __( 'OWD Site Statistics is activated by default to run an aggregate once daily just after midnight. If your site receives a large amount of traffic, you may want to change this setting to run more often for performance reasons.', 'owd-site-statistics' ) . '</p>';
	$output .= '<p>' . __( 'Next scheduled run is at:', 'owd-site-statistics' ) . ' <strong>' .  date( 'g:ia \o\n D, F jS', wp_next_scheduled( 'owd_stats_aggregate_hook' ) ) . '</strong></p>';
	$output .= '</div>';

	$output .= owd_stats_options_build_form( $options, 'primary' );

	owd_stats_build_postbox( 'display_options', __( 'Aggregate Schedule', 'owd-site-statistics' ), $output );

}



function owd_stats_postbox_main_display_options() {
	global $owd_Stats;

	$output  = "<script type=\"text/javascript\">\n";

	if ( $owd_Stats->config['dashboard']['display_widget'] == 0 ) {

		$output .= "\tjQuery(document).ready(function($) {\n";
		$output .= "\t\t$('.hide').hide()\n";
		$output .= "\t})\n";
	}

	$output .= "\tfunction toggleDW() {\n";
	$output .= "\t\tjQuery('.hide').slideToggle();\n";
	$output .= "\t}\n";
	$output .= "\tfunction hideDW() {\n";
	$output .= "\t\tjQuery('.hide').hide();\n";
	$output .= "\t}\n";
	$output .= "</script>\n";

	$output .= '<div class="intro">';
	$output .= '<p>' . __( 'The following options control how OWD Site Statistics will function and display your statistics information.', 'owd-site-statistics' ) . '</p>';
	$output .= '</div>';

	$options = array();

	$options[] = array( 'id'    => 'dashboard_display_widget',
					    'name'  => __( 'Dashboard Widget:', 'owd-site-statistics' ),
						'desc'  => __( 'Display a widget on your dashboard with an overview chart and bar graph.', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[dashboard_display_widget]" type="radio" onClick="toggleDW()" value="1" ' . checked( '1', $owd_Stats->config['dashboard']['display_widget'], FALSE ) . ' /><span>' . __( 'Yes', 'owd-site-statistics' ) . '</span>' .
								   '<input name="owd_stats_options[dashboard_display_widget]" type="radio" onClick="toggleDW()" value="0" ' . checked( '0', $owd_Stats->config['dashboard']['display_widget'], FALSE ) . ' /><span>' . __( 'No', 'owd-site-statistics' ) . '</span>'
					   );

	$options[] = array( 'id'    => 'dashboard_days_to_show',
					    'class' => 'hide',
					    'name'  => __( 'Dashboard Days to Display:', 'owd-site-statistics' ),
						'desc'  => __( 'How many days of data to display on the dashboard widget graph.', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[dashboard_days_to_show]" type="text" value="' . $owd_Stats->config['dashboard']['days_to_show'] . '" />'
					   );

	$options[] = array( 'id'    => 'overview_days_to_show',
					    'name'  => __( 'Overview Days to Display:', 'owd-site-statistics' ),
						'desc'  => __( 'How many days of data to display on the primary bar graph.', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[overview_days_to_show]" type="text" value="' . $owd_Stats->config['overview']['days_to_show'] . '" />'
					   );

	$options[] = array( 'id'    => 'overview_recent_hits_to_show',
					    'name'  => __( 'Recent Hits to Display:', 'owd-site-statistics' ),
						'desc'  => __( 'How many recent hits you would displayed on the statistics overview page.', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[overview_recent_hits_to_show]" type="text" value="' . $owd_Stats->config['overview']['recent_hits_to_show'] . '" />'
					   );

	$output .= owd_stats_options_build_form( $options );

	owd_stats_build_postbox( 'display_options', __( 'Display Options', 'owd-site-statistics' ), $output );

}



function owd_stats_postbox_main_database_options() {
	global $owd_Stats;

	$options = array();

	$options[] = array( 'id'    => 'database_day_to_keep',
					    'name'  => __( 'Days to Keep:', 'owd-site-statistics' ),
						'desc'  => __( 'How many days of aggregate data to retain (0=unlimited).', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[database_days_to_keep]" type="text" value="' . $owd_Stats->config['database']['days_to_keep'] . '" />'
					   );

	$options[] = array( 'id'    => 'database_months_to_keep',
					    'name'  => __( 'Months to Keep:', 'owd-site-statistics' ),
						'desc'  => __( 'How many months of aggregate data to retain (0=unlimited).', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[database_months_to_keep]" type="text" value="' . $owd_Stats->config['database']['months_to_keep'] . '" />'
					   );

	$options[] = array( 'id'    => 'database_rows_to_keep',
					    'name'  => __( 'Raw Data to Keep:', 'owd-site-statistics' ),
						'desc'  => __( 'How many rows of raw data you would like to remain after the aggregate runs (0=unlimited).', 'owd-site-statistics' ),
						'input' => '<input name="owd_stats_options[database_rows_to_keep]" type="text" value="' . $owd_Stats->config['database']['rows_to_keep'] . '" />'
					   );

	$output = '<div class="intro">';
	$output .= '<p>' . __( 'Use the following configuration settings to tell OwDstats how much information you would like to store in the aggregate tables and retain at any given time in the raw table.', 'owd-site-statistics' ) . '</p>';
	$output .= '</div>';

	$output .= owd_stats_options_build_form( $options );

	owd_stats_build_postbox( 'access_control', __( 'Database Options', 'owd-site-statistics' ), $output );
}




function owd_stats_postbox_main_access_control() {
	global $owd_Stats, $current_user;

	$options = array();

	$options[] = array( 'id'    => 'access_minimum_to_view',
					    'name'  => __( 'View Statistics:', 'owd-site-statistics' ),
						'desc'  => __( 'The minimum access level required to view statistics for your blog.', 'owd-site-statistics' ),
						'input' => '<select name="owd_stats_options[access_minimum_to_view]">' . owd_stats_options_dropdown_roles( $owd_Stats->config['access']['minimum_to_view'] ) . '</select>'
					   );

	$options[] = array( 'id'    => 'access_minimum_to_edit',
					    'name'  => __( 'Edit Options:', 'owd-site-statistics' ),
						'desc'  => __( 'The minimum access level required to modify OwDstats options.', 'owd-site-statistics' ),
						'input' => '<select name="owd_stats_options[access_minimum_to_edit]">' . owd_stats_options_dropdown_roles( $owd_Stats->config['access']['minimum_to_edit'] ) . '</select>'
					   );

	$output = '<div class="intro">';
	$output .= '<p>' . __( 'The following options can be used to control how OwDstats will permit access to various areas of operation.', 'owd-site-statistics' ) . '</p>';

	if ( current_user_can( 'create_users' ) )
		$output .= '<p style="font-style: italic">' . __( 'As the blog owner, you are automatically granted full access; from here you can set the permissions level necessary for other users of the blog to view your statistics or edit the configuration.', 'owd-site-statistics' ) . '</p>';

	$output .= '</div>';

	$output .= owd_stats_options_build_form( $options );

	owd_stats_build_postbox( 'access_control', __( 'Access Control', 'owd-site-statistics' ), $output );
}


function owd_stats_postbox_main_ignore_lists() {
	global $owd_Stats;

	$options = array();

	$options[] = array( 'id'    => 'ignore_ip_list',
					    'name'  => __( 'Ignore List:', 'owd-site-statistics' ),
						'desc'  => __( 'You can use an incomplete address to block entire ranges. Each IP should be on its own line.', 'owd-site-statistics' ),
						'input' => '<textarea name="owd_stats_options[ignore_ip_list]" type="text" rows="4" cols="20">' . ( is_array( $owd_Stats->config['ignore']['ip_list'] ) ? implode( "\r\n", $owd_Stats->config['ignore']['ip_list'] ) : '' ) . '</textarea>'
					   );

	$output = '<div class="intro">';
	$output .= '<p>' . __( 'Set up a list of IP addresses (including fragments) that you would like OwDstats to exclude from recording.', 'owd-site-statistics' ) . '</p>';
	$output .= '</div>';

	$output .= owd_stats_options_build_form( $options );

	owd_stats_build_postbox( 'ignore_lists', __( 'Ignore Lists', 'owd-site-statistics' ), $output );
}


function owd_stats_options_build_form( $options, $button = 'secondary' ) {

	$output = '<fieldset>';

	foreach ( $options as $option ) {

		$output .= '<dl' . ( isset( $option['class'] ) ? ' class="' . $option['class'] . '"' : '' ) . '>';
		$output .= '<dt><label for="owd_stats_options[' . $option['id'] . '">' . $option['name'] . '</label>';

		if ( isset( $option['desc'] ) )
			$output .= '<p>' . $option['desc'] . '</p>';

		$output .= '</dt>';
		$output .= '<dd>' . $option['input'] . '</dd>';
		$output .= '</dl>';

	}

	$output .= '<div style="clear: both;"></div>';
	$output .= '<p class="owd_stats_submit"><input type="submit" class="button-' . $button . '" value="' . __( 'Save Changes', 'owd-site-statistics' ) . '" /></p>';
	$output .= '</fieldset>';

	return $output;

}


function owd_stats_options_dropdown_roles( $selected = FALSE ) {
	global $wp_roles;

//	die( $selected );
	$p = '';
	$r = '';

	$editable_roles = get_editable_roles();

	foreach( $editable_roles as $role => $details ) {
		$name = translate_user_role($details['name'] );
		if ( strtolower( $selected ) == $role ) // Make default first in list
			$p = "\n\t<option selected='selected' value='" . esc_attr($role) . "'>$name</option>";
		else
			$r .= "\n\t<option value='" . esc_attr($role) . "'>$name</option>";
	}
	return $p . $r;
}
