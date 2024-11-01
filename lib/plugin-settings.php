<?php

if ( !class_exists('wsPluginSettings') ){

/**
 * Simple container class for storing plugin (and theme) settings.
 * Requires PHP 5 with SPL.
 * 
 * Example:
 * <code>
 * <?php
 *  require 'plugin-settings.php';
 *  
 *  //Init
 *  $config = new wsPluginSettings(
 * 		'my_config',          //Load settings from the 'my_config' option, if it exists. 
 * 		array('foo' => 'bar') //Default settings in key => value format. 
 * 	);
 * 
 *  //Use $object->setting syntax to access individual settings:
 *  echo $config->foo; //'bar'
 *  $config->foo = 'baz';
 *  echo $config->foo; //'baz'
 * 
 *  //Save changes back to 'my_config':
 *  $config->save();
 * 
 *  //Reload settings (overwrites unsaved changes):
 *  $config->foo = 'this will be overwritten';
 *  $config->load(); 
 *  echo $config->foo; //'baz'
 * 
 *  echo $config->unknown;    //throws UndefinedSettingException
 *  $config->unknown = 'abc'; //throws UndefinedSettingException
 * ?>
 * </code>
 * 
 * @author Janis Elsts
 * @copyright 2011
 * @version 1.0
 */
class wsPluginSettings {
	protected $optionName;
	protected $defaults;
	protected $settings = array();
	
	/**
	 * Class constructor.
	 * 
	 * @param string $optionName Load/store settings in this WP option. 
	 * @param array $defaults Default settings. All settings that you'll use must have default values.
	 */
	public function __construct($optionName, $defaults){
		if ( empty($optionName) || !is_string($optionName) ){
			throw new InvalidArgumentException("Option name must be a non-empty string.");
		}
		if ( !is_array($defaults) ){
			throw new InvalidArgumentException("Invalid default settings. Use an associative array to specify defaults.");
		}
		$this->optionName = $optionName;
		$this->defaults = $defaults;
		$this->load();
	}

	/**
	 * Load settings from the database.
	 *
	 * @return bool True if existing settings were successfully loaded, False if the option doesn't exist.
	 */
	public function load(){
		$settings = get_option($this->getOptionName());
		if ( $settings === false ){
			$settings = array();
			$loaded = false;
		} else if ( is_array($settings) ){
			$loaded = true;
		} else {
			throw new UnexpectedValueException("Failed to load settings: the option value is not an array.");
		}
		
		$this->settings = $settings;
		return $loaded;
	}
	
	/**
	 * Save settings to the database.
	 * @see update_option()
	 * 
	 * @return bool True if settings were saved, False otherwise.
	 */
	public function save(){
		return update_option($this->getOptionName(), $this->settings);
	}
	
	/**
	 * Delete the option used for setting storage.
	 * @see delete_option()
	 * 
	 * @return bool True if the option was successfully deleted, False otherwise.
	 */
	public function deleteOption(){
		return delete_option($this->getOptionName());
	}
	
	/**
	 * Get the name of the option used for setting storage.
	 * 
	 * @return string
	 */
	public function getOptionName(){
		return $this->optionName;
	}
	
	/**
	 * Magic method. Lets one retrieve settings using the `$object->setting` notation.
	 * 
	 * @param string $name Setting name.
	 * @return mixed Setting value. If no value has been specified, the default value will be returned instead.
	 */
	public function __get($name){
		if ( array_key_exists($name, $this->settings) ){
			return $this->settings[$name];
		} else if ( array_key_exists($name, $this->defaults) ){
			return $this->defaults[$name];
		} else {
			throw new wsUndefinedSettingException("Undefined setting '{$name}'.");
		}
	}
	
	/**
	 * Magic method. Lets one modify settings using the `$object->setting = $value` notation.
	 * 
	 * @param string $name Setting name.
	 * @param mixed $value New setting value.
	 * @return void
	 */
	public function __set($name, $value){
		if ( array_key_exists($name, $this->defaults) ){
			$this->settings[$name] = $value;
		} else {
			throw new wsUndefinedSettingException("Undefined setting '{$name}'.");
		}
	}
	
	/**
	 * Retrieve a list of all settings and their current values as an associative array.
	 * 
	 * @param bool $includeDefaults Include options currently set to the default value.   
	 * @return array
	 */
	public function getAll($includeDefaults = true){
		if ( $includeDefaults ){
			return array_merge($this->defaults, $this->settings);
		} else {
			return $this->settings;
		}
	}
	
	/**
	 * Set a given setting back to its default value.
	 * 
	 * @param string $name Setting name.
	 * @return void
	 */
	public function setToDefault($name){
		if ( array_key_exists($name, $this->defaults) ){
			throw new wsUndefinedSettingException("Undefined setting '{$name}'.");
		}
		if ( array_key_exists($name, $this->settings) ){
			unset($this->settings[$name]);
		}
	}
}

/**
 * Custom exception thrown when an unknown setting is read or written.
 */
class wsUndefinedSettingException extends RuntimeException {}

}

?>