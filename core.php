<?php
//Dependencies
require 'lib/ws-util.php';
require 'lib/wp-mutex.php';
require 'lib/google-search.php';
require 'lib/dependency-utils.php';
require 'lib/ws-pagination.php';
require 'js/flot/flot.php';

class wsGoogleRankMonitor {
	private $config = null;
	private $pluginFile;
	private $pluginDir;
	private $tableKeywords; //The name of the keyword table.
	private $tableHistory;  //The name of the rank history table.
	
	private $cronUpdatesHook = 'grm_cron_update_rankings'; //Arbitrary. Just needs to be a unique string.
	private $cronSingleUpdateHook = 'grm_cron_update_rankings_once'; //For out-of-order updates.
	private $updaterLock = 'grm_updater_lock';

	private $wpdb;

	private $settingsUi;
	private $settingsPageSlug = 'trackgooglerankings_settings';
	private $settingsPageTitle = 'Track Google Rankings Settings';
	private $settingsMenuTitle = 'Track Google Rankings';
	private $reportPageSlug = 'trackgooglerankings';
	private $reportPageTitle = 'Track Google Rankings';
	private $reportMenuTitle = 'Track Google Rankings';

	public function __construct($pluginFile = ''){
		$this->pluginFile = $pluginFile;
		$this->pluginDir = dirname($this->pluginFile);
		
		$this->config = include('configuration.php');

		global $wpdb;
		$this->wpdb = $wpdb;
		$this->tableKeywords = $wpdb->prefix.'grm_keywords';
		$this->tableHistory  = $wpdb->prefix.'grm_rank_history';

		register_deactivation_hook($this->pluginFile, array($this, 'onDeactivation'));
		register_activation_hook($this->pluginFile, array($this, 'onActivation'));

		if ( $this->config->catchSearchKeywords ){
			add_action('wp_loaded', array($this, 'catchSearchKeywords'));
		}

		if ( $this->config->catchSearchcredits ){
add_action('wp_head', 'credits');
function credits(){
			  	echo '<div align="right"><small>Track Google Rankings plugin provided by <a href="http://www.packages-seo.com/">search engine optimization</a></div></small>';
}
		}
		
		//Set up periodic rank updates.
		if (!wp_next_scheduled($this->cronUpdatesHook)) {
			wp_schedule_event(time(), 'hourly', $this->cronUpdatesHook);
		}
		add_action($this->cronUpdatesHook, array($this, 'updateKeywordRankings'));
		add_action($this->cronSingleUpdateHook, array($this, 'updateKeywordRankings'));

		//Set up the admin.
		add_action('admin_menu', array($this, 'createMenuEntries'));
		add_action('admin_init', array($this, 'initSettingsApi'));
	}

	public function updateKeywordRankings(){
		ignore_user_abort(true);
		@set_time_limit(0); //Fails in safe mode. There's no workaround.
		
		if ( !WPMutex::acquire($this->updaterLock, 5) ){
			return;
		};

		$siteUrl = get_site_url();

		$minSleep = max(round($this->config->gracePeriod * 0.5), 1);
		$maxSleep = round($this->config->gracePeriod * 1.5);

		$searchesDone = 0;
		while($keywords = $this->fetchKeywordsToUpdate()){
			foreach($keywords as $keyword){
				try {
					list($newRank, $results) = $this->getGoogleRank($siteUrl, $keyword->keyword, $this->config->maxGoogleResults);
				} catch (GoogleNoResponseException $e) {
					//Network error? Retry later.
					error_log(sprintf('Network error occurred while checking keyword "%s".', $keyword));
					WPMutex::release($this->updaterLock);
					return;
				} catch (GoogleBanException $e){
					//Google has temporarily blocked our IP. Defer remaining rank updates
					//until the next cron event.
					error_log(sprintf(
						'Failed to check keyword "%s": Google has temporarily blocked our IP address after %d searches in a row.',
						$keyword,
						$searchesDone
					));
					WPMutex::release($this->updaterLock);
					return;
				}
				
				if ( ($keyword->last_checked_on != 0) && ($newRank != $keyword->rank) ) {
					// If the current or old position are outside of our "window of visibility" (normally the first
					// 100 results), we assume the keyword rose/fell by the minimum number of positions necessary 
					// to enter/exit the window. Otherwise, just calculate the change directly.
					if ($newRank == null) {
						$rankChange = $keyword->rank - $this->config->maxGoogleResults - 1;					
					} else if ($keyword->rank == null) {
						$rankChange = $this->config->maxGoogleResults - $newRank + 1;
					} else {
						$rankChange = $keyword->rank - $newRank;
					}
				} else {
					$rankChange = 0;
				}

				//Update the keyword.
				//Alas, we can't use $wpdb->prepare here because it doesn't handle NULL values properly.
				$now = time();
				$query = sprintf("
					UPDATE `{$this->tableKeywords}`
					SET rank = %s, rank_change = %d, last_checked_on = %d, google_results = %s
					WHERE keyword_id = %d",
					($newRank === null) ? 'NULL' : $newRank,
					$rankChange,
					$now,
					$this->wpdb->prepare("%s", json_encode($results)),
					$keyword->keyword_id
				);
				$this->wpdb->query($query);

				//Insert a history record.
				$query = sprintf("
					INSERT INTO `{$this->tableHistory}`(keyword_id, checked_on, rank)
					VALUES (%d, %d, %s)",
					$keyword->keyword_id,
					$now,
					($newRank === null) ? 'NULL' : $newRank
				);
				$this->wpdb->query($query);

				$searchesDone++;
				sleep(rand($minSleep, $maxSleep));
			}
		}
		
		WPMutex::release($this->updaterLock);
	}

	/**
	 * Fetch a list of keywords that require a rankings update.
	 * By default, each keyword is updated in 24 hour intervals.
	 *
	 * @param integer $maxKeywords
	 * @return array An array of row objects.
	 */
	public function fetchKeywordsToUpdate($maxKeywords = 30){
		$threshold = time() - 24*3600;
		$query = $this->wpdb->prepare("
			SELECT keyword_id, keyword, rank, last_checked_on
			FROM `{$this->tableKeywords}`
			WHERE last_checked_on < %d
			ORDER BY last_checked_on ASC
			LIMIT %d",
			$threshold, $maxKeywords
		);
		return $this->wpdb->get_results($query, OBJECT);
	}

	/**
	 * Determine how high a given site ranks in Google for a specific keyword or phrase.
	 * Returns an array with exactly two results: the current rank, and an array of
	 * Google results.
	 *
	 * @throws GoogleBanException|GoogleNoResponseException
	 *
	 * @param string $siteUrl Site URL.
	 * @param string $keyword Keyword.
	 * @param int $numResults The maximum number of Google results to examine.
	 * @param string $googleTld A country-specific Google domain.
	 * @return array ($rank, $googleResults)
	 */
	public function getGoogleRank($siteUrl, $keyword, $numResults = 100, $googleTld = '.com'){
		$baseUrl = 'http://www.google'.$googleTld;
		$requestUrl = $this->getGoogleSearchUrl($keyword, $googleTld);

		$cookies = array();
		$httpArgs = array (
			'timeout' => 20,
			'user-agent' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)', //Spoof.
			'cookies' => $cookies,
		);
		
		//Note to self: Free Rank Monitor for Google uses the following UA:
		//Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SV1)

		$results = array();
		$siteRank = null;
		do {
			$httpArgs['cookies'] = $cookies;

			$response = wp_remote_get($requestUrl, $httpArgs);
			$body = wp_remote_retrieve_body($response);

			if ( empty($body) ){
				throw new GoogleNoResponseException("Google Search did not respond.");
			} else if (preg_match("#answer=86640#i", $body)) {
				$e = 'Blocked by Google. Please read: http://www.google.com/support/websearch/' .
					 'bin/answer.py?&answer=86640&hl=en';
				throw new GoogleBanException($e);
			} else {
				$serp = parseGoogleSerp($body);
				foreach($serp->results as $result){
					$rank = count($results) + 1;
					if ( ($siteRank === null) && wsUtil::isInternalUrl($result['url'], $siteUrl)){
						$siteRank = $rank;
					}
					$result['page'] = $serp->page;
					$results[$rank] = $result;
				}

				$requestUrl = $serp->nextPageUrl;
				$newCookies = isset($response['cookies']) ? $response['cookies'] : array();
				if ( !empty($newCookies) ){
					$cookies = $this->mergeCookies($cookies, $newCookies);
				}
			}

			if ($requestUrl != null){
				//The next page URL is usually relative. We need a fully qualified one.
				if ( !wsUtil::startsWith($requestUrl, 'http://') ){
					$requestUrl = $baseUrl.$requestUrl;
				}
				//Pause for a few seconds to avoid Google's throttling algorithm.
				sleep(rand(3, 5));
			}

		} while ( (count($results) < $numResults) && ($requestUrl != null) );

		return array($siteRank, $results);
	}

	/**
	 * Generate a Google search page URL for a specific keyword and Google TLD.
	 *
	 * @param string $keyword
	 * @param string $googleTld Defaults to the current configured TLD.
	 * @return string Search page URL.
	 */
	public function getGoogleSearchUrl($keyword, $googleTld = null){
		if ( empty($googleTld) ){
			$googleTld = $this->config->googleTld;
		}

		$baseUrl = 'http://www.google'.$googleTld;
		$query = array('q' => $keyword, 'pws' => 0);
		$query = array_map('urlencode', $query);
		return $baseUrl.'/search?'.build_query($query);
	}

	/**
	 * Convert a keyword to lowercase.
	 *
	 * Assumes keywords use are encoded in UTF-8,
	 * but multibyte support hasn't really been tested properly.
	 *
	 * @param string $keyword
	 * @return string
	 */
	private function keywordToLowercase($keyword){
		if ( $keyword == '' ){
			return $keyword;
		}
		if ( function_exists('mb_strtolower') ){
			if ( mb_check_encoding($keyword, 'UTF-8') ){
				$keyword = mb_strtolower($keyword, 'UTF-8');
			}
		} else {
			$keyword = strtolower($keyword);
		}
		return $keyword;
	}

	/**
	 * Merge two arrays of WP_Http_Cookie's into one, favouring the newer cookies.
	 *
	 * @param array $cookies
	 * @param array $newCookies
	 * @return array
	 */
	private function mergeCookies($cookies, $newCookies){
		$merged = array();
		foreach((array)$cookies as $cookie){
			if ( !empty($cookie->name) ){
				$merged[$cookie->name] = $cookie;
			}
		}
		foreach((array)$newCookies as $cookie){
			if ( !empty($cookie->name) ){
				$merged[$cookie->name] = $cookie;
			}
		}
		return $merged;
	}

	/**
	 * Set up the admin menu entries for this plugin.
	 *
	 * @return void
	 */
	public function createMenuEntries(){
		$pageHook = add_management_page(
			$this->reportPageTitle,
			$this->reportMenuTitle,
			'manage_options',
			$this->reportPageSlug,
			array($this, 'pageRankMonitor')
		);

		if ( $pageHook !== false ) {
			//Add our style sheets an scripts to the "Rank Monitor" page.
			wp_register_style('grm-rank-monitor-css', plugins_url('css/rank-monitor-page.css', $this->pluginFile));
			DependencyUtils::enqueue_style_on_page($pageHook, 'grm-rank-monitor-css');

			wp_register_script('grm-rank-monitor-js', plugins_url('js/rank-monitor-page.js', $this->pluginFile));
			DependencyUtils::enqueue_script_on_page(
				$pageHook,
				array(
					'jquery',
					'jquery-flot',
					'jquery-flot-resize',
					'jquery-flot-threshold',
					'grm-rank-monitor-js'
				)
			);

			//The Flot charting lib needs excanvas for IE < 9 support.
			global $is_IE;
			if ( $is_IE ){
				DependencyUtils::enqueue_script_on_page($pageHook, 'excanvas');
			}
		}

		add_options_page(
			$this->settingsPageTitle,
			$this->settingsMenuTitle,
			'manage_options',
			$this->settingsPageSlug,
			array($this, 'pageSettings')
		);
		add_filter(
			'plugin_action_links_'.plugin_basename($this->pluginFile),
			array($this, 'addSettingsLink')
		);
	}

	public function pageRankMonitor(){
		switch ( wsUtil::current_action() ){
			case 'export-csv':
				$this->actionExportCsv(); break;
			case 'add-keywords':
				$this->actionAddKeywords(); break;
			case 'delete-keywords':
				$this->actionDeleteKeywords(); break;
			case 'details':
				$this->actionKeywordDetails(); break;
			default:
				$this->actionKeywordList();
		}
	}

	/**
	 * Handler for the main keyword list/summary page.
	 *
	 * @return void
	 */
	private function actionKeywordList(){
		//Get a list of keywords and paginate it.
		$pagination = new wsPagination(20);

		$orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
		if ( !in_array($orderby, array('keyword', 'rank', 'rank_change', 'created_on', 'last_checked_on')) ){
			$orderby = 'rank';
		}

		if ( isset( $_GET['order'] ) && 'desc' == $_GET['order'] ){
			$order = 'desc';
		} else {
			$order = 'asc';
		}

		$query = "
			SELECT SQL_CALC_FOUND_ROWS
				keyword_id,
				keyword,
				rank,
				rank_change,
				last_checked_on,
				created_on
			FROM `{$this->tableKeywords}`
		";
		if ( $orderby === 'rank' ){
			$query .= ' ORDER BY COALESCE(rank, 10000) '.$order;
		} else {
			$query .= sprintf(' ORDER BY `%s` %s', $orderby, $order);
		}
		$query .= $this->wpdb->prepare(' LIMIT %d OFFSET %d', $pagination->limit(), $pagination->offset());

		$keywords = $this->wpdb->get_results($query, OBJECT);
		$pagination->setTotalItems($this->wpdb->get_var('SELECT FOUND_ROWS()'));
		$pageTitle = $this->reportPageTitle;
		$pageSlug = $this->reportPageSlug;
		$settingsPageSlug = $this->settingsPageSlug;

		/**
		 * @var StdClass $keyword
		 */
		foreach($keywords as $keyword){
			$keyword->googleSearchUrl = $this->getGoogleSearchUrl($keyword->keyword);
		}

		//Display the "Rank Monitor" page.
		$this->render(
			'rank-monitor-page',
			compact('pagination', 'keywords', 'pageTitle', 'pageSlug', 'settingsPageSlug')
		);
	}

	/**
	 * Output a downloadable CSV export of all keywords and their data.
	 *
	 * @return void
	 */
	public function actionExportCsv(){
		$query = "
			SELECT keyword, rank, rank_change, last_checked_on, created_on
			FROM `{$this->tableKeywords}`
		";
		$keywords = $this->wpdb->get_results($query, OBJECT);
		$filename = sprintf('%s-rankings-%s.csv', parse_url(get_site_url(), PHP_URL_HOST), date('Ymd'));
		$this->render('keywords-as-csv', compact('keywords', 'filename'));
	}

	/**
	 * A handler for the "Add Keywords" sub-page.
	 *
	 * @return void
	 */
	private function actionAddKeywords(){
		if ( isset($_POST['keywords']) ){
			$safeUrl = admin_url('tools.php?page='.urlencode($_GET['page']));

			$keywords = wsUtil::splitList($_POST['keywords']);
			
			//All keywords should be normalized to lowercase as Google ignores case.
			$keywords = array_map(array($this, 'keywordToLowercase'), $keywords);
			$keywords = array_filter(array_map('trim', $keywords));
			$keywords = array_unique($keywords);

			if ( empty($keywords) ){
				wp_redirect($safeUrl);
			} else {
				$added = $this->addKeywords($keywords, true);
				if ( $added > 0 ){
					//Queue a rankings update for the newly added keyword(s).
					wp_schedule_single_event(time()+2, $this->cronSingleUpdateHook);
				}
				wp_redirect(add_query_arg('added', $added, $safeUrl));
			}
		}

		$this->render('add-keywords');
	}

	/**
	 * Handler for the "Delete keywords" bulk action.
	 *
	 * @return void
	 */
	private function actionDeleteKeywords(){
		check_admin_referer('bulk-keywords');
		$redirect = wsUtil::getCleanReferrer();

		if ( !isset($_POST['selected_keywords']) || empty($_POST['selected_keywords']) ){
			wp_redirect($redirect);
		}

		$keywords = (array)$_POST['selected_keywords'];
		$keywords = array_filter(array_unique(array_map('intval', $keywords)));
		if ( empty($keywords) ){
			wp_redirect($redirect);
		}

		$deleted = $this->deleteKeywords($keywords);
		wp_redirect(add_query_arg('deleted', $deleted, $redirect));
	}

	/**
	 * Handler for the per-keyword details pages.
	 *
	 * @throws Exception if the selected keyword doesn't exist.
	 * @return void
	 */
	private function actionKeywordDetails(){
		$keywordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

		$query = "
			SELECT *
			FROM `{$this->tableKeywords}`
			WHERE keyword_id = %d
		";
		$keyword = $this->wpdb->get_row($this->wpdb->prepare($query, $keywordId), OBJECT);

		if ( $keyword === null ){
			throw new Exception(sprintf("Keyword %d not found", $keywordId));
		} else {
			/**
			 * @var StdClass $keyword
			 */
			$encoded = $keyword->google_results;
			$keyword->google_results = json_decode($encoded, true);
			if ($keyword->google_results === null){
				$keyword->google_results = unserialize($encoded);
			}
			$keyword->googleSearchUrl = $this->getGoogleSearchUrl($keyword->keyword);

			$chartPeriodStart = time() - $this->config->rankChartDays * 24 * 3600;
			$chartPeriodEnd = time();
			$rankHistory = $this->getRankHistory(array($keyword), $chartPeriodStart, $chartPeriodEnd);
			$rankHistory = reset($rankHistory);

			$nextRankingsUpdate = wp_next_scheduled($this->cronUpdatesHook);
			$nextUrgentRankingsUpdate = wp_next_scheduled($this->cronSingleUpdateHook);
			if ( $nextUrgentRankingsUpdate && ($nextUrgentRankingsUpdate < $nextRankingsUpdate) ) {
				$nextRankingsUpdate = $nextUrgentRankingsUpdate;
			}

			$pageTitle = $this->reportPageTitle;

			$this->render(
				'keyword-details',
				compact('keyword', 'rankHistory', 'chartPeriodStart', 'chartPeriodEnd', 'nextRankingsUpdate', 'pageTitle')
			);
		}
	}

	/**
	 * Stub handler for the settings page.
	 *
	 * @return void
	 */
	public function pageSettings(){
		$this->render('settings-page', array('settingsUi' => $this->settingsUi, 'pageTitle' => $this->settingsPageTitle));
	}

	/**
	 * Add one or more keywords.
	 *
	 * @param array $keywords
	 * @param bool $isUserAdded Whether to mark the keywords as added by the user (as opposed to auto-detected keywords).
	 * @return int The number of keywords added to the DB, or FALSE on error.
	 */
	private function addKeywords($keywords, $isUserAdded = false){
		$createdOn = time();
		$query = "INSERT IGNORE INTO `{$this->tableKeywords}`(keyword, created_on, is_user_added) VALUES ";
		$values = array();
		foreach($keywords as $keyword){
			$values[] = $this->wpdb->prepare('(%s, %d, %d)', $keyword, $createdOn, $isUserAdded ? 1 : 0);
		}
		$query .= implode(', ', $values);
		return $this->wpdb->query($query);
	}

    /**
     * Get the rank history for one or more keywords.
     *
     * Returns an array indexed by keyword ID. Each array item is an array
     * of time series data in array(time, rank) format, sorted from oldest
     * to newest.
     *
     * @param array $keywords Array of keyword IDs or keyword objects.
	 * @param int $periodStart Timestamp. Only return records from this period.
	 * @param int $periodEnd Timestamp.
	 * @return array
     */
	private function getRankHistory($keywords, $periodStart = 0, $periodEnd = 0){
		if ( !is_array($keywords) ){
			$keywords = array($keywords);
		}
		$keywordIds = array();
		foreach($keywords as $keyword){
			if ( is_numeric($keyword) ){
				$keywordIds[] = intval($keyword);
			} else if ( is_object($keyword) && isset($keyword->keyword_id) ){
				$keywordIds[] = intval($keyword->keyword_id);
			} else {
				throw new Exception('Invalid array item type - expected an integer or an object with a "keyword_id" field.');
			}
		}

		//Warning: Inefficient for large datasets due to IN().
		$query = "
			SELECT keyword_id, checked_on, rank
			FROM `{$this->tableHistory}`
			WHERE keyword_id IN(%s)
		";
		$query = sprintf($query, implode(', ', $keywordIds));
		if ( $periodStart != 0 ){
			$query .= sprintf(' AND checked_on >= %d', $periodStart);
		}
		if ( $periodEnd != 0 ){
			$query .= sprintf(' AND checked_on < %d', $periodEnd);
		}
		$entries = $this->wpdb->get_results($query, ARRAY_A);

		//Format the results as [id => [[time1, rank1], [time2, rank2], ...], ...].
		$rankHistory = array();
		foreach($keywordIds as $id){
			$rankHistory[$id] = array();
		}
		foreach($entries as $entry){
			//$wpdb returns all values as strings, but we actually want INTs and NULLs.
			$rank = is_numeric($entry['rank']) ? intval($entry['rank']) : null;
			$rankHistory[intval($entry['keyword_id'])][] = array(intval($entry['checked_on']), $rank);
		}

		//Sort each time series oldest => newest.
		foreach($rankHistory as $id => $history){
			usort($history, array($this, 'compareHistoricRanks'));
			$rankHistory[$id] = $history;
		}

		return $rankHistory;
	}

	/**
	 * usort() callback for use in {@link wsGoogleRankMonitor::getRankHistory()}.
	 * Used to sort historic rankings from oldest to newest.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	private function compareHistoricRanks($a, $b){
		return $a[0] - $b[0];
	}

	/**
	 * Delete one or more keywords
	 *
	 * @param array $keywordIds Delete the keywords with these IDs.
	 * @return int The number of deleted keywords.
	 */
	private function deleteKeywords($keywordIds){
		$keywordIds = array_map('intval', $keywordIds);
		$keywordIds = implode(', ', $keywordIds);

		//Delete historical rankings first.
		$query = sprintf("DELETE FROM `{$this->tableHistory}` WHERE keyword_id IN (%s)", $keywordIds);
		$this->wpdb->query($query);

		//Delete they keywords themselves.
		$query = sprintf("DELETE FROM `{$this->tableKeywords}` WHERE keyword_id IN (%s)", $keywordIds);
		return $this->wpdb->query($query);
	}

	/**
	 * Render a view, in the MVC sense of "view".
	 * 
	 * All views live in the "views" subdirectory.
	 * 
	 * @param string $view View filename (without the ".php" extension).
	 * @param array $arguments Optional arguments to pass to the view.
	 * @return void
	 */
	public function render($view, $arguments = null){
		if ( !empty($arguments) ){
			extract($arguments);
		}
		if ( !isset($config) ){
			/** @noinspection PhpUnusedLocalVariableInspection */
			$config = $this->config; //Also pass $config to the view so that it can access configuration.
		}
		/** @noinspection PhpIncludeInspection */
		require sprintf('views/%s.php', $view);
	}
	
	public function catchSearchKeywords(){
		$keyword = wsUtil::getGoogleSearchQuery();
		if ( empty($keyword) ){
			return;
		}
		
		//Case doesn't matter for Google Search, so we normalize all kw's to lowercase.
		$keyword = $this->keywordToLowercase($keyword);
		
		//Store the search keyword.
		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->tableKeywords}(keyword, created_on) VALUES(%s, %d)",
			$keyword, time()
		);
		$this->wpdb->query($sql);
	}

	/**
	 * Add a "Settings" link to our row in the "Plugins" tab.
	 *
	 * @param array $actions
	 * @return array
	 */
	public function addSettingsLink($actions){
		$actions['settings'] = sprintf(
			'<a href="%s">Settings</a>',
			esc_attr(admin_url('options-general.php?page='.$this->settingsPageSlug))
		);
		return $actions;
	}

	/**
	 * Activation hook. Creates the plugin's tables.
	 *
	 * @return void
	 */
	public function onActivation(){
		if ( !function_exists('dbDelta') ){
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		}

		$query = "
			CREATE TABLE `{$this->tableKeywords}` (
			  `keyword_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `keyword` varchar(250) NOT NULL,
			  `created_on` int(10) unsigned DEFAULT NULL,
			  `is_user_added` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `last_checked_on` int(11) unsigned NOT NULL DEFAULT '0',
			  `rank` int(11) unsigned DEFAULT NULL,
			  `rank_change` int(11) DEFAULT NULL,
			  `google_results` mediumtext,
			  PRIMARY KEY  (`keyword_id`),
			  UNIQUE KEY `keyword`(`keyword`)
			);
		";
		dbDelta($query);

		$query = "
			CREATE TABLE `{$this->tableHistory}` (
				`keyword_id` int(10) unsigned NOT NULL,
			  `checked_on` int(10) unsigned NOT NULL,
			  `rank` int(11) DEFAULT NULL,
			  PRIMARY KEY  (`keyword_id`,`checked_on`)
			);
		";
		/** @noinspection PhpParamsInspection */
		dbDelta($query);
	}

	public function onDeactivation(){
		wp_clear_scheduled_hook($this->cronUpdatesHook);
	}

	/**
	 * Initialize the WordPress Settings API for our settings page.
	 * The actual rendering, output and validation are handled elsewhere.
	 *
	 * @return void
	 */
	public function initSettingsApi(){
		require GRM_PLUGIN_DIR.'/settings-ui.php';
		$this->settingsUi = new grmSettingsUI($this->config, $this->settingsPageSlug);
	}
}

?>