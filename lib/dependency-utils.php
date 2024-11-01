<?php

if ( !class_exists('DependencyUtils') ):

class DependencyUtils {
	private static $register_dependencies = array();
	private static $enqueue_on_page = array();

	/**
	 * Enqueue a script on a specific admin page.
	 * 
	 * You can enqueue more than one script on more than one page at once 
	 * by passing an array of script handles as the first argument and 
	 * and array of page hooks as the second.
	 * 
	 * @param string|array $page Page hook(s)
	 * @param string|array $handle Script handle(s)
	 * @return void
	 */
	public static function enqueue_script_on_page($page, $handle){
		self::enqueue_dependency_on_page('script', $handle, $page);
	}
	
	/**
	 * Enqueue a style on a specific admin page.
	 * 
	 * @see DependencyUtils::enqueue_script_on_page()
	 *
	 * @param string|array $page Page hook(s) 
	 * @param string|array $handle Style handle(s)
	 * @return void
	 */
	public static function enqueue_style_on_page($page, $handle){
		self::enqueue_dependency_on_page('style', $handle, $page);
	}
	
	/**
	 * Enqueue a script or a stylesheet on a specific admin page.
	 * 
	 * @param string $type 'script' or 'style'
	 * @param string|array $handle Script or style handle(s).
	 * @param string|array $page Page hook(s).
	 * @return void
	 */
	private static function enqueue_dependency_on_page($type, $handle, $page){
		$handle = (array)$handle;
		$page = (array)$page;
		
		foreach($page as $the_page){
			if ( !array_key_exists($the_page, self::$enqueue_on_page) ){
				self::$enqueue_on_page[$the_page] = array();
			}
			if ( !array_key_exists($type, self::$enqueue_on_page[$the_page]) ){
				self::$enqueue_on_page[$the_page][$type] = array();
			}
			self::$enqueue_on_page[$the_page][$type] = array_merge(self::$enqueue_on_page[$the_page][$type], $handle);
		}
	}
	
	/**
	 * Callback for the 'admin_enqueue_scripts' action. 
	 * Enqueues the scripts and styles that were intended for the current page. 
	 * 
	 * @param string $hook
	 * @return void
	 */
	public static function on_admin_enqueue_scripts($hook = null){
		if ( !array_key_exists($hook, self::$enqueue_on_page) || empty(self::$enqueue_on_page[$hook]) ){
			return;
		}
		
		$queue = self::$enqueue_on_page[$hook];
		$scripts = array_key_exists('script', $queue) ? array_unique($queue['script']) : array();
		$styles =  array_key_exists('style',  $queue) ? array_unique($queue['style'])  : array();
		
		foreach($scripts as $script){
			wp_enqueue_script($script);
		}
		foreach($styles as $style){
			wp_enqueue_style($style);
		}
	}
	
	/**
	 * Defer the registration of a JavaScript file until the 'init' action.
	 * 
	 * @see wp_register_script()
	 * @link http://core.trac.wordpress.org/ticket/11526
	 * @link http://codex.wordpress.org/Function_Reference/wp_register_script
	 */
	public static function register_script_on_init($handle, $src, $deps = array(), $ver = false, $in_footer = false){
		self::register_dependency('script', func_get_args());
	}
	
	/**
	 * Defer the registration of a CSS file until the 'init' action.
	 *
	 * @see wp_register_style()
	 */
	public static function register_style_on_init($handle, $src, $deps = array(), $ver = false, $media = 'all'){
		self::register_dependency('style', func_get_args());
	}
	
	/**
	 * Queue a dependency to be registered inside the 'init' action.
	 * If 'init' has already fired, it will just register the dependency immediately.
	 * 
	 * @param string $type Dependency type. Valid values are 'script' and 'style'.
	 * @param array $args Arguments to pass to the registration function.
	 * @return void
	 */
	private static function register_dependency($type, $args){
		if ( did_action('init') ){
			if ( $type === 'script' ){
				call_user_func_array('wp_register_script', $args);
			} else if ( $type === 'style' ){
				call_user_func_array('wp_register_style', $args);
			} else {
				throw new Exception("Unknown dependency type '{$type}'");
			}
		} else {
			self::$register_dependencies[] = array($type, $args);
		}		
	}
	
	/**
	 * Callback for the 'init' action.
	 * 
	 * @return void
	 */
	public static function on_init(){
		//Register any queued styles and scripts.
		foreach(self::$register_dependencies as $dependency){
			list($type, $args) = $dependency;
			if ( $type === 'script' ){
				call_user_func_array('wp_register_script', $args);
			} else if ( $type === 'style' ){
				call_user_func_array('wp_register_style', $args);
			} 
		}
		self::$register_dependencies = array();
	}
}

add_action('init', 'DependencyUtils::on_init');

/**
 * Notice the very high $priority value below which makes our 'admin_enqueue_scripts' callback
 * run last. This way, enqueue_script_on_page() will still mostly work as expected even if someone
 * calls it inside their own 'admin_enqueue_scripts' callback. 
 */ 
add_action('admin_enqueue_scripts', 'DependencyUtils::on_admin_enqueue_scripts', 70000, 1);


endif;