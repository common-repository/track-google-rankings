<?php

if ( !isset($delimiter) ){
	$delimiter = ',';
}
if ( !isset($enclosure) ){
	$enclosure = '"';
}
$columnHeaders = array('Keyword', 'Rank', 'Rank change', 'Last check', 'Added');

$fp = fopen('php://output', 'w');
if ($fp) {
    header('Content-Type: text/csv');
    header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));
    header('Pragma: no-cache');
    header('Expires: 0');
    
   	fputcsv($fp, $columnHeaders, $delimiter, $enclosure);
    foreach($keywords as $keyword){
    	$row = array(
    		$keyword->keyword,
    		($keyword->rank == null) ? '>'.$config->maxGoogleResults : $keyword->rank,
    		sprintf('%+d', $keyword->rank_change),
    		date('Y-m-d H:i:s T', $keyword->last_checked_on),
		    date('Y-m-d H:i:s T', $keyword->created_on),
		);
    	fputcsv($fp, $row, $delimiter, $enclosure);
    }
    fclose($fp);
    die;
}		