<?php
/**
 * Tarjim.io PHP Translation client
 * version: 1.4
 *
 * Requires PHP 5+
 * This file includes the Translationclient Class and
 * the _T() function definition
 *
 */

class Tarjimclient {
	/**
	 *
	 */
	public function __construct() {
		$this->project_id = Configure::read('TARJIM_PROJECT_ID');
		$this->apikey = Configure::read('TARJIM_APIKEY');
		$this->cache_dir = ROOT . '/' . APP_DIR . '/tmp/cache/locale/';
		$this->cache_backup_file = $this->cache_dir.'translations_backup.json';
		$this->cache_file = $this->cache_dir.'translations.json';
		$this->sanitized_html_cache_file = $this->cache_dir.'sanitized_html.json';
	}

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		set_error_handler('tarjimErrorHandler');

		if (!file_exists($this->cache_file)) {
			$final = $this->getLatestFromTarjim();
			$this->updateCache($final);
		}
		else {
			$ttl_in_minutes = 15;

			$time_now = time();
			$time_now_in_minutes = (int) ($time_now / 60);
			$locale_last_updated = filemtime($this->cache_file);
			$locale_last_updated_in_minutes = (int) ($locale_last_updated / 60);
			$diff = $time_now_in_minutes - $locale_last_updated_in_minutes;
			## If cache was updated in last $ttl_in_minutes min get data directly from cache
			if ((isset($diff) && $diff < $ttl_in_minutes)) {
				$cache_data = file_get_contents($this->cache_file);
				$final = json_decode($cache_data, true);
			}
			else {
				## Pull meta
				$endpoint = 'http://tarjim.io/translationkeys/json/meta/'.$this->project_id.'?apikey='.$this->apikey;
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $endpoint);
				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$meta = curl_exec($ch);

				## Get translations from cache if curl failed
				if (curl_error($ch)) {
					CakeLog::write('vendors/tarjim_client/errors', 'Curl error line '.__LINE__.': ' . curl_error($ch));
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}
				curl_close($ch);

				$meta = json_decode($meta, true);
				
				## Get cache meta tags
				$cache_meta = file_get_contents($this->cache_file);
				$cache_meta = json_decode($cache_meta, true);
				
				## If cache if older than tarjim get latest and update cache
				if ($cache_meta['meta']['results_last_update'] < $meta['meta']['results_last_update']) {
					$final = $this->getLatestFromTarjim();
					$this->updateCache($final);
				}
				else {
					## Update cache file timestamp
					touch($this->cache_file);
					$locale_last_updated = filemtime($this->cache_file);

					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);
				}
			}
		}

		## Restore default error handler
		restore_error_handler();

		return $final;
	}

	/**
	 * Update cache files
	 */
	public function updateCache($latest) {
		set_error_handler('tarjimErrorHandler');
		if (file_exists($this->cache_file)) {
			$cache_backup = file_get_contents($this->cache_file);
			file_put_contents($this->cache_backup_file, $cache_backup);
			$cmd = 'chmod 777 '.$this->cache_backup_file;
			exec($cmd);
		}

		$encoded = json_encode($latest);
		file_put_contents($this->cache_file, $encoded);
		$cmd = 'chmod 777 '.$this->cache_file;
		exec($cmd);
		
		## Restore default error handler
		restore_error_handler();
	}

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		set_error_handler('tarjimErrorHandler');

		$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$this->project_id.'?apikey='.$this->apikey;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);

		if (curl_error($ch)) {
			CakeLog::write('vendors/tarjim_client/errors', 'Curl error line '.__LINE__.': ' . curl_error($ch));
			$cache_data = file_get_contents($this->cache_file);
			$final = json_decode($cache_data, true);

			## Restore default error handler
			restore_error_handler();
			return $final;
		}

		$decoded = json_decode($result, true);

		## Restore default error handler
		restore_error_handler();

		return $decoded;
	}

}

/**
 * Tarjim error handler
 */
function tarjimErrorHandler($errno, $errstr, $errfile, $errline) {
	CakeLog::write('vendors/tarjim_client/errors', 'Tarjim client error file '.$errfile.' (line '.$errline.'): '.$errstr);
}

/**
 * Tarjim.io Translation helper
 * N.B: if calling _T() inside Javascript code, pass the do_addslashes as true
 *
 * Read from the global $_T
 */
///////////////////////////////
function _T($key, $config = [], $debug = false) {
	set_error_handler('tarjimErrorHandler');
	global $_T;
	$assign_tarjim_id = false;

	## Check for mappings
	if (is_array($key)) {
		$mappings = $key['mappings'];
		$original_key = $key;
		$key = strtolower($key['key']);
	}
	else {
		$original_key = $key;
		$key = strtolower($key);
	}

	## Direct match
	if (isset($_T[$key]) && !empty($_T[$key])) {
		$mode = 'direct';
		if (is_array($_T[$key])) {
			$result = $_T[$key]['value'];
			$tarjim_id = $_T[$key]['id'];
			$assign_tarjim_id = true;
		}
		else {
			$result = $_T[$key];
		}
	}

	## Fallback key
	if (isset($_T[$key]) && empty($_T[$key])) {
		$mode = 'key_fallback';
		$result = $original_key;
	}

	## Empty fall back (return key)
	if (!isset($_T[$key])) {
		$mode = 'empty_key_fallback';
		$result = $original_key;
	}

	## Debug mode
	if (!empty($debug)) {
		echo $mode ."\n";
		echo $key . "\n" .$result;
	}

	if (isset($config['do_addslashes']) && $config['do_addslashes']) {
		$result = addslashes($result);
	}

	if (isset($mappings)) {
		$result = injectValuesIntoTranslation($result, $mappings);
	}
	
	$sanitized_result = sanitizeResult($key, $result);


	if (isset($config['is_page_title'])) {
		return strip_tags($sanitized_result);
	}

	if (isset($config['skip_assign_tid'])) {
		return strip_tags($sanitized_result);
	}

	if (isset($config['skip_tid'])) {
		return strip_tags($sanitized_result);
	}

	if ($assign_tarjim_id) {
		$sanitized_result = assignTarjimId($tarjim_id, $sanitized_result);
	}

	## Restore default error handler
	restore_error_handler();

	return $sanitized_result;
}

/**
 *
 */
function assignTarjimId($id, $value) {
	$result = sprintf('<span data-tid=%s>%s</span>', $id, $value);
	return $result;
}

/**
 * Remove <script> tags from translation value
 * Prevent js injection
 */
function sanitizeResult($key, $result) {
	$unacceptable_tags = ['script'];
	$unacceptable_attribute_values = [
		'function',
		'{.*}',
	];

	if ($result != strip_tags($result)) {
		$Tarjimclient = new Tarjimclient;
		## Get meta from cache
		$cache_data = file_get_contents($Tarjimclient->cache_file);
		$cache_data = json_decode($cache_data, true);
		$cache_results_checksum = $cache_data['meta']['results_checksum'];
		
		## Get active language
		if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
			$active_language = $_T['meta']['active_language'];
		}
		elseif (isset($_SESSION['Config']['language'])) {
			$active_language = $_SESSION['Config']['language'];
		}

		if (file_exists($Tarjimclient->sanitized_html_cache_file) && isset($active_language)) {
			global $_T;
			$sanitized_html_cache_file = $Tarjimclient->sanitized_html_cache_file;
			$cache_file = $Tarjimclient->cache_file;


			## Get sanitized cache
			$sanitized_cache = file_get_contents($sanitized_html_cache_file);
			$sanitized_cache = json_decode($sanitized_cache, true);
			$sanitized_cache_checksum = $sanitized_cache['meta']['results_checksum'];
			$sanitized_cache_results = $sanitized_cache['results'][$active_language];
			
			## If locale haven't been updated and key exists in sanitized cache
			# Get from cache
			if ($cache_results_checksum == $sanitized_cache_checksum && array_key_exists($key, $sanitized_cache_results)) {
				return $sanitized_cache['results'][$active_language][$key];
			}
		}

		$dom = new DOMDocument;
		$dom->loadHTML('<?xml encoding="utf-8" ?>'.$result, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		
		## Remove unawanted nodes 
		foreach ($unacceptable_tags as $tag) {
			## Get unwanted nodes
			$unwanted_nodes = $dom->getElementsByTagName($tag);
			## Copy unwanted nodes to loop over without updating length on removal of nodes
			$unwanted_nodes_copy = iterator_to_array($unwanted_nodes);
			foreach ($unwanted_nodes_copy as $unwanted_node) {
				## Delete node
				$unwanted_node->parentNode->removeChild($unwanted_node);
			}
		}
		
		$nodes = $dom->getElementsByTagName('*');
		
		foreach ($nodes as $node) {
			## Remove unwanted attributes
			if ($node->hasAttributes()) {
				$attributes_copy = iterator_to_array($node->attributes);
				foreach ($attributes_copy as $attr) {
					foreach ($unacceptable_attribute_values as $value) {
						$regex = '/'.$value.'/is';
						if (preg_match_all($regex, $attr->nodeValue)) {
							$node->removeAttribute($attr->nodeName);
							break;
						}
					}	
				}
			}	
		}

		$sanitized = $dom->saveHTML($dom);
		$stripped = str_replace(['<p>', '</p>'], '', $sanitized);
		cacheSanitizedHTML($key, $stripped, $cache_results_checksum);
		return $stripped; 
	}

	return $result;
}

/**
 *
 */
function cacheSanitizedHTML($key, $sanitized, $cache_results_checksum) {
	global $_T;
	$Tarjimclient = new Tarjimclient;
	$sanitized_html_cache_file = $Tarjimclient->sanitized_html_cache_file;

	## Get active language
	if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
		$active_language = $_T['meta']['active_language'];
	}
	elseif (isset($_SESSION['Config']['language'])) {
		$active_language = $_SESSION['Config']['language'];
	}
	else {
		return;
	}

	if (file_exists($sanitized_html_cache_file)) {
		$sanitized_html_cache = file_get_contents($sanitized_html_cache_file);
		$sanitized_html_cache = json_decode($sanitized_html_cache, true);

		## If translation cache checksum is changed overwrite sanitized cache
		if ($sanitized_html_cache['meta']['results_checksum'] != $cache_results_checksum) {
			$sanitized_html_cache = [];
		}
	}

	$sanitized_html_cache['meta']['results_checksum'] = $cache_results_checksum;
	$sanitized_html_cache['results'][$active_language][$key] = $sanitized;
	$encoded_sanitized_html_cache = json_encode($sanitized_html_cache);
	file_put_contents($sanitized_html_cache_file, $encoded_sanitized_html_cache);	
	$cmd = 'chmod 777 '.$Tarjimclient->sanitized_html_cache_file;
	exec($cmd);
}

/**
 *
 */
function injectValuesIntoTranslation($translation_string, $mappings) {
	## Get all keys to replace and save into matches
	$matches = [];
	preg_match_all('/%%.*?%%/', $translation_string, $matches);

	## Inject values into result
	foreach ($matches[0] as $match) {
		$match_stripped = str_replace('%', '', $match);
		$regex = '/'.$match.'/';
		$translation_string = preg_replace($regex, $mappings[$match_stripped], $translation_string);
	}

	return $translation_string;
}


/**
 * Helper function to create a keys file
 */
function InjectViewKeysIntoTranslationTable() {
	## TODO 1. Exec the command, and inject the keys into the translations DB (indicating which namespace & language)
	#$cmd = 'grep -ohriE "_T\('.*'\)" ./views/* > keys';
	#exec ($cmd);

}
