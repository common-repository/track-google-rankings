<?php

class WPSelectQuery {
	private $query;
	
	public function __construct($query){
		global $wpdb;
		
		$args = func_get_args();
		array_shift( $args );
		if ( !empty($args) ){
			$query = $wpdb->prepare($query, $args);
		}
		
		$this->query = $query;
	}	
}