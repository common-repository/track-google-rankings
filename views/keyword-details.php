<?php

?>

<div class="wrap keyword-details">

<?php screen_icon(); ?>
<h2><?php echo $pageTitle; ?> <small>- <?php	echo esc_html($keyword->keyword); ?></small></h2>

<?php if ( !empty($keyword->last_checked_on) ): ?>

<h3>Google rank: <?php
	if ( $keyword->rank === null ){
		echo '>'.$config->maxGoogleResults;
	} else {
		echo $keyword->rank;
	}

	printf(
		' ( <span class="rank-change %s">%s</span>)',
		$keyword->rank_change > 0 ? 'rank-change-up' : ($keyword->rank_change < 0 ? 'rank-change-down' : 'rank-change-none'),
		sprintf('%+d', $keyword->rank_change)
	);
?></h3>

<div class="rank-chart-wrap">
	<div class="rank-chart-placeholder" id="big-rank-chart">
		Loading chart...
	</div>
</div>

<h3>Search results <?php
	printf(
		'<a id="google-search-%d"
			class="run-google-search"
			title="Search Google for &quot;%s&quot;"
			href="%s"
			target="_blank"
		> </a>',
		intval($keyword->keyword_id),
		esc_attr($keyword->keyword),
		esc_attr($keyword->googleSearchUrl)
	);
?></h3>
<?php if (is_array($keyword->google_results) && count($keyword->google_results) > 0): ?>

<table class="fixed widefat serp-list">
	<thead>
		<tr>
			<th class="serp-rank">#</th>
			<th class="serp-url">URL</th>
		</tr>
	</thead>
	<tbody>
	<?php

	$resultClass = '';
	$displayed = 0;
	foreach($keyword->google_results as $resultRank => $googleResult):
		$resultClass = array();
		if ( $resultRank % 2 ) {
			$resultClass[] = 'alternate';
		}
		if ( $resultRank == $keyword->rank ){
			$resultClass[] = 'our-result';
		}
		if ( !empty($resultClass) ){
			$resultClass = ' class="'.implode(' ', $resultClass).'"';
		} else {
			$resultClass = '';
		}
	?>
		<tr<?php echo $resultClass; ?>>
			<td class="serp-rank"><?php echo $resultRank; ?>.</td>
			<td class="serp-url">
				<div><?php
				printf(
					'<a href="%s" title="Visit URL">%s</a>',
					esc_attr($googleResult['url']),
					esc_html($googleResult['url'])
				);
				?></div>
				<div class="serp-title"><?php echo ($googleResult['title']); ?></div>
			</td>
		</tr>
	<?php
		$displayed++;
		if($displayed >= $config->maxGoogleResults){
			break;
		}
	endforeach;
	?>
	</tbody>
</table>

<?php else: ?>

	<p>No results for "<?php echo esc_html($keyword->keyword); ?>".</p>

<?php endif; ?>

<?php
//If we don't have enough data to cover the entire time period, prepend a couple of
//no-value points to the start of the time series to make the chart look better.
$firstPoint = reset($rankHistory);
if ( $firstPoint === false ){
	$timestamp = $chartPeriodEnd;
} else {
	$timestamp = $firstPoint[0];
}
while($timestamp > $chartPeriodStart){
	$timestamp = $timestamp - (24*3600);
	array_unshift($rankHistory, array($timestamp, null, 'N/A'));
}

//Convert the historic data to a format suitable for plotting with Flot.
$jsRankHistory = array();
foreach($rankHistory as $point){
	list($timestamp, $rank) = $point;
	
	if ( isset($point[2]) ){
		$rankLabel = $point[2];
	} else {
		$rankLabel = ($rank == null) ? sprintf('>%d', $config->maxGoogleResults) : $rank;
	}
	$timestampLabel = date('M d', $timestamp);

	$timestamp = $timestamp * 1000; //JavaScript timestamps are in milliseconds.
	$rank = ($rank === null) ? $config->maxGoogleResults + 1.2 : $rank;
	
	$jsRankHistory[] = array($timestamp, $rank, $rankLabel, $timestampLabel);
}
?>
<script type="text/javascript">
	var grmRankHistory = <?php echo json_encode($jsRankHistory); ?>;
	var grmRankChartMin = <?php echo ($chartPeriodStart - 4*3600) * 1000; ?>;
	var grmRankChartMax = <?php echo (time() + 4*3600) * 1000; ?>;
	var grmMaxGoogleResults = <?php echo $config->maxGoogleResults; ?>;
</script>

<?php else: ?>
	<?php if ($keyword->is_user_added): ?>
		<p>We haven't finished checking this keyword's ranking yet. Please come back in a minute or two.</p>
	<?php else: ?>
		<p>We haven't checked this keyword's ranking yet. Please come back later.</p>
		<p>The next scheduled ranking update will occur in about <?php echo human_time_diff(time(), $nextRankingsUpdate); ?>.</p>
	<?php endif; ?>
<?php endif; ?>

</div>

