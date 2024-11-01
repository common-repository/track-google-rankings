<?php
require 'lib/plugin-settings.php';
class grmPluginSettings extends wsPluginSettings {
	public function __construct(){
		parent::__construct(
			'ws_grm_settings',
			array(
				'gracePeriod' => 5,
				'maxGoogleResults' => 50,
				'googleTld' => '.com',
				'rankChartDays' => 50,
				'catchSearchKeywords' => true,
				'catchSearchcredits' => true,
			)
		);
	}
}

return new grmPluginSettings();

