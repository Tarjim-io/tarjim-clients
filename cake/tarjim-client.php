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
					CakeLog::write('tarjim_curl_error', 'Curl error: ' . curl_error($ch));
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);
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

		return $final;
	}

	/**
	 * Update cache files
	 */
	public function updateCache($latest) {
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
	}

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$this->project_id.'?apikey='.$this->apikey;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);

		if (curl_error($ch)) {
			$cache_files = scandir($this->cache_dir, 1);
			$newest_file = end($cache_files[1]);
			$cache_data = file_get_contents($this->cache_dir . $newest_file);
			$final = json_decode($cache_data, true);
			return $final;
		}

		$decoded = json_decode($result, true);

		return $decoded;
	}

}

/**
 * Tarjim.io Translation helper
 * N.B: if calling _T() inside Javascript code, pass the do_addslashes as true
 *
 * Read from the global $_T
 */
///////////////////////////////
function _T($key, $do_addslashes = false, $debug = false) {
	global $_T;
	$key = strtolower($key);

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

	return $result;
}


/**
 * Helper function to create a keys file
 */
function InjectViewKeysIntoTranslationTable() {
	## TODO 1. Exec the command, and inject the keys into the translations DB (indicating which namespace & language)
	#$cmd = 'grep -ohriE "_T\('.*'\)" ./views/* > keys';
	#exec ($cmd);

}
