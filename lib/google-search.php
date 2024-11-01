<?php

class GoogleBanException extends RuntimeException {}
class GoogleNoResponseException extends RuntimeException {}

function googleWebSearch($query, $num = 10, $googleTld = '.com'){
	$args = array('q' => $query, 'pws' => 0);
	if ( $num != 10 ){
		$args['num'] = $num;
	}
	$args = array_map('urlencode', $args);
	
    $url = 'http://www.google'.$googleTld.'/search?'.build_query($args);
    $response = wp_remote_get(
		$url, 
		array(
			'timeout' => 20,
			'user-agent' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)', //Spoof.
		)
	);
    $body = wp_remote_retrieve_body($response);

	if ( empty($body) ){
		throw new GoogleNoResponseException("Google Search did not respond.");
	} else if (preg_match("#answer=86640#i", $body)) {
		$e = 'Blocked by Google. Please read: http://www.google.com/support/websearch/' .
			 'bin/answer.py?&answer=86640&hl=en';
		throw new GoogleBanException($e);
    } else {
		$serp = parseGoogleSerp($body);
		return $serp->results;
    }
}

class GoogleSerp {
	public $results = array();
	public $page = null;
	public $nextPageUrl = null;

	public function __construct($results = array(), $page = null, $nextPageUrl = null){
		$this->results = $results;
		$this->page = $page;
		$this->nextPageUrl = $nextPageUrl;
	}
}

function parseGoogleSerp($html){
	if ( function_exists('mb_convert_encoding') ){
		$encoding = mb_detect_encoding($html);
		if ($encoding && ($encoding != 'UTF-8')){
			$html = mb_convert_encoding($html, 'UTF-8', $encoding);
		}
	}

	$dom = new DOMDocument();
	@$dom->loadHtml($html);

	$xpath = new DOMXPath($dom);
	$links = $xpath->query("//ol//li[contains(@class,'g')]//h3//a");

	$results = array();
	/**
	 * @var DOMNode $link
	 */
	foreach($links as $link) {
		$url = $link->getAttribute('href');
		//Skip in-line results from other Google Search services, like News Search.
		if ( substr($url, 0, 1) === '/' ){
			continue;
		}
		$results[] = array(
			'url' => $url,
			'title' => $link->textContent,
		);
	}

	$pageNum = $xpath->query('//table[@id="nav"]//td[contains(@class,"cur")]');
	if ( $pageNum->length > 0 ){
		$page = intval(trim($pageNum->item(0)->textContent));
		if ( $page <= 0 ){
			$page = null;
		}
	} else {
		$page = null;
	}

	$nextPageLink = $xpath->query('//a[@id="pnnext"]');
	if ( $nextPageLink->length > 0 ){
		$nextPageUrl = $nextPageLink->item(0)->getAttribute('href');
		if ( empty($nextPageUrl) ){
			$nextPageUrl = null;
		}
	} else {
		$nextPageUrl = null;
	}

	return new GoogleSerp($results, $page, $nextPageUrl);
}