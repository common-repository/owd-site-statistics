<?php


/**
 * Activate the dashboard widget
 *
 * @since 0.4.2
 * @category Views
 * @subcategory Dashboard
 */
 
function owd_stats_add_dashboard_widget() {
	global $owd_Stats, $current_user;

	get_currentuserinfo();

	$min_to_view = ( isset( $owd_Stats->config['access']['minimum_to_view'] ) ? $owd_Stats->access_levels[$owd_Stats->config['access']['minimum_to_view']] : 'create_users' );

	if ( $current_user->ID == 1 || current_user_can( $min_to_view ) ) {
		if ( $owd_Stats->config['dashboard']['display_widget'] == 1 )
			wp_add_dashboard_widget( 'owd_stats', __( 'Blog Statistics', 'owd-site-statistics' ), 'owd_stats_display_dashboard_widget' );	
	}

} 



/**
 * Responsible for building the dashboard widget, which displays a smaller summary
 * chart and bar graph.
 *
 * @since 0.1.0
 * @category Views
 * @subcategory Dashboard
 */

function owd_stats_display_dashboard_widget() {
	global $wpdb, $owd_Stats; 

	extract( owd_stats_get_totals() );

	// Until we find out if can attached a stylesheet only to the dashboard, lets just append it inline.
?>
    <style type="text/css">
    .owdstats { margin: 1em 0; width: 100%; color: #353535; padding: 6px; background: #fff url('<?php echo OWD_stats_PLUGIN_MEDIA; ?>brighter.png') repeat-x 0 bottom; font-family: Helvetica, Arial, sans-serif; border: 1px solid #d9d9d9; border-top-color: #d2d2d2; border-left-color: #d2d2d2; -moz-border-radius: 6px; -khtml-border-radius: 6px; -webkit-border-radius: 6px; border-radius: 6px; }
    .owdstats thead th { vertical-align: top; font-size: 12px; text-align: left; color: #aaa; font-weight: 500; border-bottom: 1px solid #eee; }
    .owdstats thead th span { display: block; margin: 0 0 4px; font-size: 10px; }
    .owdstats tbody td { font-size: 11px; border-bottom: 1px dotted #e2e2e2; border-left: 1px dotted #e0e0e0; padding: 2px 0 2px 3px; }
    .owdstats tbody td:first-child { border-left: none; padding: 2px 0; }
    
    .sc_visitors { background-color: #114477; }
    .sc_pageviews { background-color: #3377B6; }
    .sc_spiders { background-color: #83b4d8; }
    .sc_feeds { background-color: #f38f36; }

.bg_visitors { background: #114477 url('<?php echo OWD_stats_PLUGIN_MEDIA; ?>shade.png') repeat-y 100%; }
.bg_pageviews { background: #3377B6 url('<?php echo OWD_stats_PLUGIN_MEDIA; ?>shade.png') repeat-y 100%; }
.bg_spiders { background: #83b4d8 url('<?php echo OWD_stats_PLUGIN_MEDIA; ?>shade.png') repeat-y 100%; }
.bg_feeds { background: #f38f36 url('<?php echo OWD_stats_PLUGIN_MEDIA; ?>shade.png') repeat-y 100%; }

    .boxes { width: 8px; height: 8px; float: left; margin: 2px 3px 0 0; }
    </style>
<?php

	owd_stats_display_dashboard_summary( $totals, $monthly, $daily );

	list( $graph_data, $max ) = owd_stats_get_graph_data( $owd_Stats->config['dashboard']['days_to_show'], $daily );
	owd_stats_display_bargraph( $graph_data, $max );

}

function owd_stats_display_dashboard_summary( $totals, $monthly, $daily ) {
	global $owd_Stats;

	if ( ! is_array( $totals ) || ! is_array( $monthly ) || ! is_array( $daily ) )
		return;

?>
	<table class="owdstats">
    <thead>
    <tr><th scope="col"></th>
        <th scope="col">Total</th>
        <th scope="col">This month<span><?php echo date ( 'M, Y', strtotime( $owd_Stats->datetime->this_month . '-01 00:00:00' ) ); ?></span></th>
        <th scope="col">Yesterday<span><?php echo date ( 'd M, Y', strtotime( $owd_Stats->datetime->yesterday ) ); ?></span></th>
        <th scope="col">Today<span><?php echo date ( 'd M, Y', strtotime( $owd_Stats->datetime->today ) ); ?></span></th>
	</tr>
    </thead>

	<tbody>
	<tr><td><div class="boxes sc_visitors"></div> Visitors</td>
        <td><?php echo isset( $totals['visitors'] ) ? number_format( $totals['visitors'] ) : 0; ?></td>
        <td><?php echo isset( $monthly[$owd_Stats->datetime->this_month]['visitors'] ) ? number_format( $monthly[$owd_Stats->datetime->this_month]['visitors'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->yesterday]['visitors'] ) ? number_format( $daily[$owd_Stats->datetime->yesterday]['visitors'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->today]['visitors'] ) ? number_format( $daily[$owd_Stats->datetime->today]['visitors'] ) : 0; ?></td>
	</tr>

	<tr><td><div class="boxes sc_pageviews"></div> Page Views</td>
        <td><?php echo isset( $totals['pageviews'] ) ? number_format( $totals['pageviews'] ) : 0; ?></td>
        <td><?php echo isset( $monthly[$owd_Stats->datetime->this_month]['pageviews'] ) ? number_format( $monthly[$owd_Stats->datetime->this_month]['pageviews'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->yesterday]['pageviews'] ) ? number_format( $daily[$owd_Stats->datetime->yesterday]['pageviews'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->today]['pageviews'] ) ? number_format( $daily[$owd_Stats->datetime->today]['pageviews'] ) : 0; ?></td>
	</tr>

	<tr><td><div class="boxes sc_spiders"></div> Spiders</td>
        <td><?php echo isset($totals['spiders'] ) ? number_format( $totals['spiders'] ) : 0; ?></td>
        <td><?php echo isset( $monthly[$owd_Stats->datetime->this_month]['spiders'] ) ? number_format( $monthly[$owd_Stats->datetime->this_month]['spiders'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->yesterday]['spiders'] ) ? number_format( $daily[$owd_Stats->datetime->yesterday]['spiders'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->today]['spiders'] ) ? number_format( $daily[$owd_Stats->datetime->today]['spiders'] ) : 0; ?></td>
	</tr>

	<tr><td><div class="boxes sc_feeds"></div> Feeds</td>
        <td><?php echo isset( $totals['feeds'] ) ? number_format( $totals['feeds'] ) : 0; ?></td>
        <td><?php echo isset( $monthly[$owd_Stats->datetime->this_month]['feeds'] ) ? number_format( $monthly[$owd_Stats->datetime->this_month]['feeds'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->yesterday]['feeds'] ) ? number_format( $daily[$owd_Stats->datetime->yesterday]['feeds'] ) : 0; ?></td>
        <td><?php echo isset( $daily[$owd_Stats->datetime->today]['feeds'] ) ? number_format( $daily[$owd_Stats->datetime->today]['feeds'] ) : 0; ?></td>
	</tr>

    </tbody>
    </table>
 
<?php
}