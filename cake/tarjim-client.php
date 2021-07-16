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
			$ttl_in_minutes = 0;

			$time_now = time();
			$time_now_in_minutes = (int) ($time_now / 60);
			$locale_last_updated = filemtime($this->cache_file);
			$locale_last_updated_in_minutes = (int) ($locale_last_updated / 60);
			$diff = $time_now_in_minutes - $locale_last_updated_in_minutes;
			## If cache was updated in last $ttl_in_minutes min get data directly from cache
			if (isset($diff) && $diff < $ttl_in_minutes) {
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
			$file_put_contents_success = file_put_contents($this->cache_backup_file, $cache_backup);
			if (!$file_put_contents_success) {
				CakeLog::write('vendors/tarjim_client/errors', 'file_put_contents error line '. __LINE__);
			}
			$cmd = 'chmod 777 '.$this->cache_backup_file;
			exec($cmd);
		}

		$encoded = json_encode($latest);
		$file_put_contents_success = file_put_contents($this->cache_file, $encoded);
		if (!$file_put_contents_success) {
			CakeLog::write('vendors/tarjim_client/errors', 'file_put_contents error line '. __LINE__);
		}
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
function _T($key, $do_addslashes = false, $debug = false) {
	set_error_handler('tarjimErrorHandler');
	global $_T;

	## Check for mappings
	if (is_array($key)) {
		$mappings = $key['mappings'];
		$key = strtolower($key['key']);
	}
	else {
		$key = strtolower($key);
	}

	## Direct match
	if (isset($_T[$key]) && !empty($_T[$key])) {
		$mode = 'direct';
		$result = $_T[$key];
	}

	## Fallback key
	if (isset($_T[$key]) && empty($_T[$key])) {
		$mode = 'key_fallback';
		$result = $key;
	}

	## Empty fall back (return key)
	if (!isset($_T[$key])) {
		$mode = 'empty_key_fallback';
		$result = $key;
	}

	## Debug mode
	if (!empty($debug)) {
		echo $mode ."\n";
		echo $key . "\n" .$result;
	}

	if ($do_addslashes) {
		$result = addslashes($result);
	}

	if (isset($mappings)) {
		$result = injectValuesIntoTranslation($result, $mappings);
	}

	## Restore default error handler
	restore_error_handler();

	return $result;
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
