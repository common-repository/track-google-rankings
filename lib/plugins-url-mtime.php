<?php

/**
 * A callback for the 'plugins_url' filter. It appends file modification time
 * to all URLs retrieved via plugins_url().
 *
 * @see plugins_url()
 *
 * @param string $url
 * @param string $path
 * @param string $plugin
 * @return string
 */ 
 
function ws_append_mtime($url, $path, $plugin){
	//Reconstruct the filesystem path of the file that the URL points to.
	//The logic below was adapted from the plugins_url() function.
	if ( !empty($plugin) && 0 === strpos($plugin, WPMU_PLUGIN_DIR) ){
		$filename = WPMU_PLUGIN_DIR;
	} else {
		$filename = WP_PLUGIN_DIR;
	}

	if ( !empty($plugin) && is_string($plugin) ) {
		$folder = dirname(plugin_basename($plugin));
		if ( '.' != $folder ){
			$filename .= '/' . ltrim($folder, '/');
		}
	}
	
	if ( !empty($path) && is_string($path) && strpos($path, '..') === false ){
		$filename .= '/' . ltrim($path, '/');
	}
	
	//Append the modification time to the URL.
	$mtime = filemtime($filename);
	if ( $mtime !== false ){
		$url = add_query_arg('mt', $mtime, $url);
	}
	
	return $url;
}
add_filter('plugins_url', 'ws_append_mtime', 10, 3);