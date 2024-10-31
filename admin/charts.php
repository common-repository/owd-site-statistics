<?php

function owd_stats_display_charts() {

	$views = array( 'searches', 'referrers', 'agents', 'spiders' );

	$view  = ( isset( $_GET['view'] ) && in_array( $_GET['view'], $views ) ? $_GET['view'] : 'all' );
	$limit = ( $view == 'all' ? 'LIMIT 20' : '' );

?>
	<div class="postbox-container" style="width: 100%;">
	<div class="metabox-holder">	
	<div class="meta-box-sortables">

		<?php
		if ( $view == 'searches' || $view == 'all' )
	        owd_stats_postbox_charts_search_terms( $view, $limit );
		if ( $view == 'referrers' || $view == 'all' )
			owd_stats_postbox_charts_referrers( $view, $limit );
		if ( $view == 'agents' || $view == 'all' )
			owd_stats_postbox_charts_user_agents( $view, $limit );
		if ( $view == 'spiders' || $view == 'all' )
			owd_stats_postbox_charts_spiders( $view, $limit );
		?>

	</div></div>
	</div> <!-- .postbox-container -->
<?php
}


function owd_stats_postbox_charts_search_terms( $view = 'all', $limit = '' ) {
	global $wpdb, $owd_Stats;

	$query[] = sprintf( "SELECT SUM( v.count ) AS count, v.search_terms, v.search_engine, v.url, v.referrer
						 FROM ( SELECT count( search_terms ) AS count, search_terms, search_engine, url, referrer
								FROM `%s`
								WHERE search_terms <> '' AND preserved IS NULL
								GROUP BY search_terms
								UNION ALL
								SELECT count, search_terms, search_engine, url, referrer
								FROM `%s`
								WHERE chart = %d )
						 AS v
						 GROUP BY v.search_terms
						 ORDER BY count DESC
						 %s", OWD_stats_TABLE_RAW, OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_SEARCHES, $limit );
				   
	$results = $wpdb->get_results ( end( $query ) );

	if ( empty( $limit ) )
		$total_results = count( $results );


	$output = '<ul class="options">';

	if ( $view == 'all' ) {
		$output .= '<li><a href="?page=' . $_GET['page'] . '&view=searches">' . __( 'View All Search Terms', 'owd-site-statistics' ) . '</a></li>';
	}
	else {
		$output .= '<li><a href="?page=' . $_GET['page'] . '">' . __( 'Back to Charts', 'owd-site-statistics' ) . '</a></li>';
		$output .= '<li>' . sprintf( __( 'Displaying %d search terms', 'owd-site-statistics' ), $total_results ) . '</li>';
	}

	$output .= '</ul>';

	$output .= '<table class="owdstats">';
	$output .= '<thead><tr>';
	$output .= '<th scope="col">' . __( 'Terms', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col">' . __( 'Engine', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="text-align: center;">' . __( 'Views', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="width: 16px;"></th>';
	$output .= '</tr></thead>';

	$output .= '<tbody>';

	if ( is_null( $results ) || empty( $results ) ) {
		$output .= '<tr><td colspan="4" style="padding: 4em; text-align: center;"><strong>' . __( 'No search terms to report yet.', 'owd-site-statistics' ) . '</strong></td></tr>';
	}
	else {

		foreach ( $results as $the ) {
			$output .= '<tr>';
			$output .= '<td><a href="' . owd_stats_entities( $the->referrer ). '">' . $the->search_terms . '</a></td>';
			$output .= '<td>' . $the->search_engine . '</td>';
			$output .= '<td style="text-align: center;">' . $the->count . '</td>';
			$output .= '<td><a href="' . $owd_Stats->blog_url . ( strpos ( $the->url, 'index.php' ) === FALSE ? owd_stats_entities( $the->url ) : '' ) . '"><img src="' . OWD_stats_PLUGIN_MEDIA . 'page_go.png" width="16" height="16" title="' . __( 'Page viewed', 'owd-site-statistics' ) . '" /></a></td>';
			$output .= '</tr>';
		}

	}

	$output .= '</tbody></table>';

	owd_stats_build_postbox( 'chart_search_terms', __( 'Top Search Terms', 'owd-site-statistics' ), $output );

}


function owd_stats_postbox_charts_referrers( $view = 'all', $limit = '' ) {
	global $wpdb, $owd_Stats;

	$query[] = sprintf( "SELECT SUM( v.count ) AS count, v.referrer, v.url
						 FROM ( SELECT count( referrer ) AS count, referrer, url 
								FROM `%s`
								WHERE referrer NOT LIKE '%s%%' AND referrer <> '' AND search_engine = '' AND url <> '' AND preserved IS NULL
								GROUP BY referrer
								UNION ALL
								SELECT count, referrer, url
								FROM `%s`
								WHERE chart = %d )
						 AS v
						 GROUP BY v.referrer
						 ORDER BY count DESC
						 %s", OWD_stats_TABLE_RAW, $owd_Stats->blog_url, OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_REFERRERS, $limit );

	$results = $wpdb->get_results ( end( $query ) );

	if ( empty( $limit ) )
		$total_results = count( $results );


	$output = '<ul class="options">';

	if ( $view == 'all' ) {
		$output .= '<li><a href="?page=' . $_GET['page'] . '&view=referrers">' . __( 'View All Referrers', 'owd-site-statistics' ) . '</a></li>';
	}
	else {
		$output .= '<li><a href="?page=' . $_GET['page'] . '">' . __( 'Back to Charts', 'owd-site-statistics' ) . '</a></li>';
		$output .= '<li>' . sprintf( __( 'Displaying %d referrers', 'owd-site-statistics' ), $total_results ) . '</li>';
	}

	$output .= '</ul>';


	$output .= '<table class="owdstats">';
	$output .= '<thead><tr>';
	$output .= '<th scope="col">' . __( 'URL', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="text-align: center;">' . __( 'Views', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="width: 16px;"></th>';
	$output .= '</tr></thead>';

	$output .= '<tbody>';

	if ( is_null( $results ) || empty( $results ) ) {
		$output .= '<tr><td colspan="4" style="padding: 4em; text-align: center;"><strong>' . __( 'No referrers to report yet.', 'owd-site-statistics' ) . '</strong></td></tr>';
	}
	else {

		foreach ( $results as $the ) {
			$output .= '<tr>';
			$output .= '<td><a href="' . owd_stats_entities( $the->referrer ). '">' . owd_stats_truncate ( $the->referrer, 98 ) . '</a></td>';
			$output .= '<td style="text-align: center;">' . $the->count . '</td>';
			$output .= '<td><a href="' . $owd_Stats->blog_url . ( strpos ( $the->url, 'index.php' ) === FALSE ? owd_stats_entities( $the->url ) : '' ) . '"><img src="' . OWD_stats_PLUGIN_MEDIA . 'page_go.png" width="16" height="16" title="' . __( 'Page viewed', 'owd-site-statistics' ) . '" /></a></td>';
			$output .= '</tr>';
		}

	}

	$output .= '</tbody></table>';

	owd_stats_build_postbox( 'chart_referrers', __( 'Top Referrers', 'owd-site-statistics' ), $output );

}


function owd_stats_postbox_charts_user_agents( $view = 'all', $limit = '' ) {
	global $wpdb;

	$query[] = sprintf( "SELECT SUM( v.count ) AS count, v.user_agent, v.os, v.browser
						 FROM ( SELECT count( user_agent ) AS count, user_agent, os, browser
								FROM `%s`
								WHERE user_agent <> '' AND CONCAT( os, browser ) <> '' AND preserved IS NULL
								GROUP BY user_agent
								UNION ALL
								SELECT count, user_agent, os, browser
								FROM `%s`
								WHERE chart = %d )
						 AS v
						 GROUP BY v.user_agent
						 ORDER BY count DESC
						 %s", OWD_stats_TABLE_RAW, OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_AGENTS, $limit );

	$results = $wpdb->get_results ( end( $query ) );

	if ( empty( $limit ) )
		$total_results = count( $results );


	$output = '<ul class="options">';

	if ( $view == 'all' ) {
		$output .= '<li><a href="?page=' . $_GET['page'] . '&view=agents">' . __( 'View All User Agents', 'owd-site-statistics' ) . '</a></li>';
	}
	else {
		$output .= '<li><a href="?page=' . $_GET['page'] . '">' . __( 'Back to Charts', 'owd-site-statistics' ) . '</a></li>';
		$output .= '<li>' . sprintf( __( 'Displaying %d user agents', 'owd-site-statistics' ), $total_results ) . '</li>';
	}

	$output .= '</ul>';


	$output .= '<table class="owdstats">';
	$output .= '<thead><tr>';
	$output .= '<th scope="col">' . __( 'Operating System', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col">' . __( 'Browser', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="text-align: center;">' . __( 'Visits', 'owd-site-statistics' ) . '</th>';
	$output .= '</tr></thead>';

	$output .= '<tbody>';

	if ( is_null( $results ) || empty( $results ) ) {
		$output .= '<tr><td colspan="4" style="padding: 4em; text-align: center;"><strong>' . __( 'No user agents to report yet.', 'owd-site-statistics' ) . '</strong></td></tr>';
	}
	else {

		foreach ( $results as $the ) {
			$output .= '<tr>';
			$output .= '<td><abbr title="' . owd_stats_entities( $the->user_agent ) . '">' . $the->os . '</abbr></td>';
			$output .= '<td><abbr title="' . owd_stats_entities( $the->user_agent ) . '">' .  $the->browser . '</abbr></td>';
			$output .= '<td style="text-align: center;">' . $the->count . '</td>';
			$output .= '</tr>';
		}

	}

	$output .= '</tbody></table>';

	owd_stats_build_postbox( 'chart_agents', __( 'Top User Agents', 'owd-site-statistics' ), $output );

}


function owd_stats_postbox_charts_spiders( $view = 'all', $limit = '' ) {
	global $wpdb;

	$query[] = sprintf( "SELECT SUM( v.count ) AS count, v.user_agent, v.spider, v.url
						 FROM ( SELECT COUNT( user_agent ) AS count, user_agent, spider, url
								FROM `%s`
								WHERE spider <> '' AND url <> '' AND url <> '/robots.txt' AND preserved IS NULL
								GROUP BY user_agent
								UNION ALL
								SELECT count, user_agent, spider, url
								FROM `%s`
								WHERE chart = %d )
						 AS v
						 GROUP BY v.user_agent
						 ORDER BY count DESC
						 %s", OWD_stats_TABLE_RAW, OWD_stats_TABLE_CHARTS, OWD_stats_CHARTS_SPIDERS, $limit );

	$results = $wpdb->get_results ( end( $query ) );

	if ( empty( $limit ) )
		$total_results = count( $results );


	$output = '<ul class="options">';

	if ( $view == 'all' ) {
		$output .= '<li><a href="?page=' . $_GET['page'] . '&view=spiders">' . __( 'View All Spiders', 'owd-site-statistics' ) . '</a></li>';
	}
	else {
		$output .= '<li><a href="?page=' . $_GET['page'] . '">' . __( 'Back to Charts', 'owd-site-statistics' ) . '</a></li>';
		$output .= '<li>' . sprintf( __( 'Displaying %d spiders', 'owd-site-statistics' ), $total_results ) . '</li>';
	}

	$output .= '</ul>';


	$output .= '<table class="owdstats">';
	$output .= '<thead><tr>';
	$output .= '<th scope="col">' . __( 'Spider', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col">' . __( 'Last Page Visited', 'owd-site-statistics' ) . '</th>';
	$output .= '<th scope="col" style="text-align: center;">' . __( 'Visits', 'owd-site-statistics' ) . '</th>';
	$output .= '</tr></thead>';

	$output .= '<tbody>';

	if ( is_null( $results ) || empty( $results ) ) {
		$output .= '<tr><td colspan="4" style="padding: 4em; text-align: center;"><strong>' . __( 'No spider visits to report yet.', 'owd-site-statistics' ) . '</strong></td></tr>';
	}
	else {

		foreach ( $results as $the ) {
			$output .= '<tr>';
			$output .= '<td><abbr title="' . owd_stats_entities( $the->user_agent ) . '">' . $the->spider . '</abbr></td>';
			$output .= '<td style="color: #757575;"><a href="' . owd_stats_entities( $the->url ). '">' . owd_stats_truncate ( $the->url, 50 ) . '</a></td>';
			$output .= '<td style="text-align: center;">' . $the->count . '</td>';
			$output .= '</tr>';
		}

	}

	$output .= '</tbody></table>';

	owd_stats_build_postbox( 'chart_spiders', __( 'Top Spiders', 'owd-site-statistics' ), $output );

}
