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
	}

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		## Get cache file names in descending order
		# then get the newest file
		$cache_dir = __DIR__ . '/../tmp/cache/locale/';
		$cache_files = scandir($cache_dir, 1);
		$newest_file = $cache_files[1];
		$ttl_in_minutes = 15;

		$time_now = time();
		$time_now_in_minutes = (int) ($time_now / 60);
		$locale_last_updated = file_get_contents($cache_dir.'locale_last_updated');
		if (!empty($locale_last_updated)) {
			$locale_last_updated_in_minutes = (int) ($locale_last_updated / 60);
			$diff = $time_now_in_minutes - $locale_last_updated_in_minutes;
		}

		## If cache was updated in last $ttl_in_minutes min get data directly from cache
		if (isset($diff) && $diff < $ttl_in_minutes && !is_dir($cache_dir . $newest_file)) {
			$cache_data = file_get_contents($cache_dir . $newest_file);
			$final = json_decode($cache_data, true);
		}
		else {
			## Pull meta
			$endpoint = 'http://tarjim.io/translationkeys/json/meta/'.$this->project_id.'?apikey='.$this->apikey;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$meta = curl_exec($ch);

			if (curl_error($ch)) {
				$cache_data = file_get_contents($cache_dir . $newest_file);
				$final = json_decode($cache_data, true);
				return $final;
			}
			curl_close($ch);

			$meta = json_decode($meta, true);

			if (!is_dir($newest_file)) {
				if ($newest_file < $meta['meta']['results_last_update']) {
					$final = $this->getLatestFromTarjim();
					$this->updateCache($final);
				}
				else {
					$cache_data = file_get_contents($cache_dir . $newest_file);
					$final = json_decode($cache_data, true);
				}
			}
			else {
				$final = $this->getLatestFromTarjim();
				$this->updateCache($final);
			}
		}

		return $final;
	}

	/**
	 * Update cache files
	 */
	public function updateCache($latest) {
		$cache_dir = __DIR__ . '/../tmp/cache/locale/';

		$locale_last_updated = time();
		file_put_contents($cache_dir.'locale_last_updated', $locale_last_updated);

		$encoded = json_encode($latest);
		file_put_contents($cache_dir . $latest['meta']['results_last_update'], $encoded);
	}

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$this->project_id.'?apikey='.$this->apikey;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);

		if (curl_error($ch)) {
			$cache_dir = __DIR__ . '/../tmp/cache/locale/';
			$cache_files = scandir($cache_dir, 1);
			$newest_file = end($cache_files[1]);
			$cache_data = file_get_contents($cache_dir . $newest_file);
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
