<?php

class grmSettingsUI {
    private $config;

    private $settingsPage;
	private $optionGroup;

    public function __construct($config, $settingsPage = 'ws_rank_monitor_settings', $optionGroup = 'grm_settings'){
		$this->config = $config;
	    $this->settingsPage = $settingsPage;
	    $this->optionGroup = $optionGroup;
	    $this->init();
    }

    private function init(){
		$settingsSection = 'default';

		register_setting($this->optionGroup, $this->config->getOptionName(), array($this, 'validateSettings'));
		add_settings_section(
			$settingsSection,
			'',
			array($this, 'generalSectionText'),
			$this->settingsPage
		);

		add_settings_field(
			'googleTld',
			'Google country',
			array($this, 'printGoogleTld'),
			$this->settingsPage,
			$settingsSection
		);

	    add_settings_field(
			'maxGoogleResults',
			'Max. Google results',
			array($this, 'printMaxGoogleResults'),
			$this->settingsPage,
			$settingsSection
		);

		add_settings_field(
			'catchSearchKeywords',
			'Keyword discovery',
			array($this, 'printCatchSearchKeywords'),
			$this->settingsPage,
			$settingsSection
		);

		add_settings_field(
			'catchSearchcredits',
			'Credits',
			array($this, 'printCatchSearchcredits'),
			$this->settingsPage,
			$settingsSection
		);
	}

	public function render(){
		echo '<form action="options.php" method="post">';

		settings_fields($this->optionGroup);
		do_settings_sections($this->settingsPage);
		submit_button();

		echo '</form>';
	}

	public function validateSettings($inputs){
		//Start with current settings and selectively
		//overwrite them with valid inputs.

		$newSettings = $this->config->getAll(false);

		if ( isset($inputs['googleTld']) ){
			$validTlds = $this->getSupportedGoogleTlds();
			if ( array_key_exists($inputs['googleTld'], $validTlds) ){
				$newSettings['googleTld'] = $inputs['googleTld'];
			} else {
				add_settings_error(
					'googleTld',
					'grm-invalid-tld',
					'The Google domain you selected is unknown or invalid. Please select a different one.'
				);
			}
		}

		if ( isset($inputs['maxGoogleResults']) && is_numeric($inputs['maxGoogleResults']) ){
			$num = intval($inputs['maxGoogleResults']);
			if ($num < 10) {
				$num = 10;
			} else if ( $num > 100 ) {
				$num = 100;
			} else {
				//Round down to the nearest page (there are ten results per page).
				$num = $num - ($num % 10);
			}
			$newSettings['maxGoogleResults'] = $num;
		}

		if ( isset($inputs['catchSearchKeywords']) ){
			$newSettings['catchSearchKeywords'] = !empty($inputs['catchSearchKeywords']);
		} else {
			$newSettings['catchSearchKeywords'] = false;
		}

		if ( isset($inputs['catchSearchcredits']) ){
			$newSettings['catchSearchcredits'] = !empty($inputs['catchSearchcredits']);
		} else {
			$newSettings['catchSearchcredits'] = false;
		}

		return $newSettings;
	}

	public function generalSectionText(){
		//No-op
	}

	public function printGoogleTld(){
		printf('<select name="%s[googleTld]" id="googleTld">', esc_attr($this->config->getOptionName()));
		foreach($this->getSupportedGoogleTlds() as $tld => $description){
			printf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr($tld),
				($tld == $this->config->googleTld) ? ' selected="selected"' : '',
				esc_html($description),
				esc_html($tld)
			);
		}
		echo '</select>';
	}

	private function getSupportedGoogleTlds(){
		$googleDomains = file(GRM_PLUGIN_DIR.'/extensions.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$result = array();
		foreach($googleDomains as $domainInfo){
			list($tld, $description) = explode(',', $domainInfo, 2);
			$tld = trim($tld);
			$description = trim($description);
			$result[$tld] = $description;
		}
		return $result;
	}

	public function printMaxGoogleResults(){
		printf('<select name="%s[maxGoogleResults]" id="maxGoogleResults">', esc_attr($this->config->getOptionName()));
		for ($num = 10; $num <= 100; $num += 10){
			printf(
				'<option value="%d"%s>%d</option>',
				$num,
				($num == $this->config->maxGoogleResults) ? ' selected="selected"' : '',
				$num
			);
		}
		echo '</select>';
		echo ' results';
	}

	public function printCatchSearchKeywords(){
		printf(
			'<label><input type="checkbox" id="catchSearchKeywords" name="%s[catchSearchKeywords]"%s> %s</label>',
			esc_attr($this->config->getOptionName()),
			$this->config->catchSearchKeywords ? ' checked="checked"' : '',
			'Automatically add incoming Google search terms to the tracker'
		);
	}
	public function printCatchSearchcredits(){
		printf(
			'<label><input type="checkbox" id="catchSearchcredits" name="%s[catchSearchcredits]"%s> %s</label>',
			esc_attr($this->config->getOptionName()),
			$this->config->catchSearchcredits ? ' checked="checked"' : '',
			'Display Aurthor Link'
		);
	}
}
