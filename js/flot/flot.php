<?php

/**
 * Callback for 'init'. Registers Flot and its plugins.
 *
 * @return void
 */
function ws_register_flot(){
	//Register the Flot jQuery plugin.
	wp_register_script(
		'jquery-flot',
		plugins_url('flot/jquery.flot.min.js', __FILE__),
		array('jquery'),
		'0.7'
	);

	//Excanvas is required to support Internet Explorer < 9.
	wp_register_script(
		'excanvas',
		plugins_url('flot/excanvas.min.js', __FILE__)
	);

	//Register Flot plugins
	$flot_plugins = array(
		'jquery-flot-crosshair',
		'jquery-flot-fillbetween',
		'jquery-flot-image',
		'jquery-flot-navigate',
		'jquery-flot-pie',
		'jquery-flot-resize',
		'jquery-flot-stack',
		'jquery-flot-symbol',
		'jquery-flot-threshold',
	);
	foreach($flot_plugins as $handle){
		wp_register_script(
			$handle,
			plugins_url('flot/'.str_replace('-', '.', $handle).'.min.js', __FILE__),
			array('jquery-flot')
		);
	}
}

if ( did_action('init') ){
	ws_register_flot();
} else {
	add_action('init', 'ws_register_flot', -10);
}

