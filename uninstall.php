<?php

if( defined( 'ABSPATH') && defined('WP_UNINSTALL_PLUGIN') ) {
	delete_option('ws_grm_settings');
	$mywpdb = $GLOBALS['wpdb'];
	if( isset($mywpdb) ) {
		$tableKeywords = $mywpdb->prefix.'grm_keywords';
		$tableHistory = $mywpdb->prefix.'grm_rank_history';
		$mywpdb->query( "DROP TABLE IF EXISTS `$tableKeywords`, `$tableHistory`" );
	}
}

