<?php

class wsUtil {
	
	public static function isInternalUrl($pageUrl, $siteUrl){
		$siteUrl = self::stripPrefix(self::stripScheme($siteUrl), 'www.'); 
		$pageUrl = self::stripPrefix(self::stripScheme($pageUrl), 'www.');
		return self::startsWith($pageUrl, $siteUrl);
	}
	
	/**
	 * Remove the "scheme://" part from the URL. 
	 * 
	 * @param string $url
	 * @return string
	 */
	public static function stripScheme($url){
		$position = strpos($url, '://');
		if ($position !== false){
			return substr($url, $position + 3);
		} else {
			return $url;
		}	
	}
	
	/**
	 * Remove a prefix from a string, if present. 
	 * 
	 * @param string $string
	 * @param string $prefix
	 * @return string
	 */
	public static function stripPrefix($string, $prefix){
		if ( self::startsWith($string, $prefix) ){
			$string = substr($string, strlen($prefix));
		}
		return $string;
	}
	
	/**
	 * Check if a string starts with the specified prefix.
	 * 
	 * @param string $string
	 * @param string $prefix
	 * @return bool
	 */
	public static function startsWith($string, $prefix){
		return (strpos($string, $prefix) === 0);
	}

	/**
	 * Split a string by all of the following separators: comma, semi-colon and newline.
	 * Empty pieces will be skipped.
	 *
	 * @param string $list
	 * @return array
	 */
	public static function splitList($list){
		$items = preg_split('@[\r\n,;]+@', $list, null, PREG_SPLIT_NO_EMPTY);
		return $items;
	}
	
	/**
	 * Extract the Google Search query from a HTTP referrer.
	 * 
	 * @param string $referer Optional. Defaults to the current HTTP referrer.
	 * @return string The search query, or an empty string on failure.
	 */
	public static function getGoogleSearchQuery($referer = null){
		if ( $referer === null ){
			if ( !array_key_exists('HTTP_REFERER', $_SERVER) || empty($_SERVER['HTTP_REFERER']) ) {
				return '';
			}
			$referer = $_SERVER['HTTP_REFERER'];
		}
		
		$parts = @parse_url($referer);
		if ( empty($parts) || (strpos($parts['host'], 'google.') === false) || !isset($parts['query']) ){
			return '';
		}
		
		parse_str($parts['query'], $query);
		if ( !isset($query['q']) || empty($query['q']) ){
			return '';
		}
		
		$keyword = trim($query['q']);
		return $keyword;
	}

	/**
	 * Display the bulk actions dropdown.
	 * Stolen from WP_List_Table.
	 *
	 * @param array $actions
	 * @param string $two
	 * @return void
	 */
	public static function bulk_actions($actions, $two = '') {
		if ( empty( $actions ) )
			return;

		echo "<select name='action$two'>\n";
		echo "<option value='-1' selected='selected'>" . __( 'Bulk Actions' ) . "</option>\n";

		foreach ( $actions as $name => $title ) {
			printf("\t<option value='%s'>%s</option>\n", esc_attr($name), $title);
		}

		echo "</select>\n";

		submit_button( __( 'Apply' ), 'button-secondary action', false, false, array( 'id' => "doaction$two" ) );
		echo "\n";
	}

	/**
	 * Get the current action selected from the bulk actions dropdown.
	 * Stolen from WP_List_Table.
	 *
	 * @return string|bool The action name or False if no action was selected
	 */
	public static function current_action() {
		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
			return $_REQUEST['action'];

		if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
			return $_REQUEST['action2'];

		return false;
	}

	/**
	 * Get the current HTTP referrer without status parameters like 'added', 'deleted' and so on.
	 *
	 * @return bool|string
	 */
	public static function getCleanReferrer(){
		$url = wp_get_referer();
		if ( !$url ){
			return false;
		} else {
			return remove_query_arg( array('added', 'deleted', 'trashed', 'untrashed', 'ids'), $url );
		}
	}
}