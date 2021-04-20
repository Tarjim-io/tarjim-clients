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

	public $project_id;

	public $namespace;

	/**
	 * Retrieve translations from Tarjim
	 */
	public function getTranslations() {
		## Get cache file names in descending order 
		# then get the newest file
		$cache_dir = __DIR__ . '/../tmp/cache/locale/';
		$cache_files = scandir($cache_dir, 1);
		$newest_file = $cache_files[1];

	//	$time_now = date('Y-m-d H:i:s');
	//	$time_now = new DateTime($time_now);
	//	$locale_last_updated = file_get_contents($cache_dir.'locale_last_updated');
	//	$locale_last_updated = new DateTime($locale_last_updated);
	//	$diff = $time_now->diff($locale_last_updated);

	//	## If cache was updated in last 15 min get data directly from cache
	//	if (0 == $diff->h && 0 == $diff->d && '15' > $diff->i) {
	//		$cache_data = file_get_contents($cache_dir . $newest_file);
	//		$final = json_decode($cache_data, true);
	//	}
	//	else {

			$endpoint = 'http://tarjim.io/translationkeys/json/meta/'.$this->project_id;
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
				}
				else {
					$cache_data = file_get_contents($cache_dir . $newest_file);
					$final = json_decode($cache_data, true);
				}
			}
			else {
				$final = $this->getLatestFromTarjim();	
			}
	//	}

		return $final;
	}

	/**
	 *
	 */
	public function updateCache($latest) {
		$cache_dir = __DIR__ . '/../tmp/cache/locale/';
			
		$locale_last_updated = date("Y-m-d H:i:s");
		file_put_contents($cache_dir.'locale_last_updated', $locale_last_updated);

		$encoded = json_encode($latest);

		file_put_contents($cache_dir . $latest['meta']['results_last_update'], $encoded);
	}

	/**
	 *
	 */
	public function getLatestFromTarjim() {
		$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$this->project_id;
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

		$this->updateCache($decoded);

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
function _T($key, $ucfirst = false, $do_addslashes = false, $debug = false) {
	global $_T;

	## Direct match
	if (isset($_T[$key]) && !empty($_T[$key])) {
		$mode = 'direct';
		$result = $ucfirst ? ucfirst($_T[$key]) : $_T[$key];
	}

	## Try ucfirst
	if (isset($_T[ucfirst($key)]) && !empty($_T[ucfirst($key)])) {
		$mode = 'ucfirst';
		$result = $ucfirst ? urldecode(ucfirst($_T[ucfirst($key)])) : urldecode($_T[ucfirst($key)]);
	}

	## Try lcfirst
	if (isset($_T[lcfirst($key)])) {
		$mode = 'lcfirst';
		$result = $ucfirst ? urldecode(ucfirst($_T[lcfirst($key)])) : urldecode($_T[lcfirst($key)]);
	}

	## Fallback key
	if (isset($_T[$key]) && empty($_T[$key])) {
		$mode = 'key_fallback';
		$result = $ucfirst ? ucfirst($key) : $key;
	}

	## Empty fall back (return key)
	if (!isset($_T[$key])) {
		$mode = 'empty_key_fallback';
		$result = $ucfirst ? ucfirst($key) : $key;
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
