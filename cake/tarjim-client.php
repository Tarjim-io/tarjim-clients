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
	 * pass config params to construct
	 */
	public function __construct($project_id = null, $apikey = null, $default_namespace = null, $additional_namespaces = []) {
		$this->project_id = $project_id;
		$this->apikey = $apikey;
		$this->default_namespace = $default_namespace;
		$this->additional_namespaces = $additional_namespaces;

		if (empty($additional_namespaces) || !is_array($additional_namespaces)) {
			$additional_namespaces = [];
		}

		$this->namespaces = $additional_namespaces;
		array_unshift($this->namespaces, $default_namespace);
		//$this->cache_dir = ROOT . '/' . APP_DIR . '/tmp/cache/locale/';
		$this->cache_dir = __DIR__.'/cache/';
		$this->cache_backup_file = $this->cache_dir.'translations_backup.json';
		$this->cache_file = $this->cache_dir.'translations.json';
		$this->sanitized_html_cache_file = $this->cache_dir.'sanitized_html.json';
		$this->logs_dir = __DIR__.'/logs/';
		$this->errors_file = $this->logs_dir.'errors.log';

		//$cmd = 'touch '.$this->cache_file.' && chmod 666 '.$this->cache_file;
		//passthru($cmd);
	}

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		set_error_handler('tarjimErrorHandler');

		if (!file_exists($this->cache_file) || !filesize($this->cache_file)) {
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
				$endpoint = 'http://tarjim.io/api/v1/translationkeys/json/meta/'.$this->project_id.'?apikey='.$this->apikey;
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $endpoint);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$meta = curl_exec($ch);

				## Get translations from cache if curl failed
				if (curl_error($ch)) {
					file_put_contents($this->errors_file, 'Curl error line '.__LINE__.': ' . curl_error($ch).PHP_EOL, FILE_APPEND);
					//CakeLog::write('vendors/tarjim_client/errors', 'Curl error line '.__LINE__.': ' . curl_error($ch));
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}
				curl_close($ch);

				$meta = json_decode($meta, true);

				## Forward compatibility		
				if (array_key_exists('result', $meta)) {
					$meta = $meta['result']['data'];
				}


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

		//$endpoint = 'http://tarjim.io/api/v1/translationkeys/json/full/'.$this->project_id.'?apikey='.$this->apikey;
		$endpoint = 'http://tarjim.io/api/v1/translationkeys/jsonByNameSpaces';

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'namespaces' => $this->namespaces,
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
		curl_setopt($ch, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($ch);

		if (curl_error($ch)) {
			file_put_contents($this->errors_file, 'Curl error line '.__LINE__.': ' . curl_error($ch).PHP_EOL, FILE_APPEND);
			$cache_data = file_get_contents($this->cache_file);
			$final = json_decode($cache_data, true);

			## Restore default error handler
			restore_error_handler();
			return $final;
		}

		$decoded = json_decode($result, true);
		
		## Forward compatibility		
		if (array_key_exists('result', $decoded)) {
			$decoded = $decoded['result']['data'];
		}

		## Restore default error handler
		restore_error_handler();

		return $decoded;
	}

}

/**
 * Tarjim error handler
 */
function tarjimErrorHandler($errno, $errstr, $errfile, $errline) {
	$Tarjim = new Tarjimclient();
	file_put_contents($Tarjim->errors_file, 'Tarjim client error file '.$errfile.' (line '.$errline.'): '.$errstr.PHP_EOL, FILE_APPEND);
	//CakeLog::write('vendors/tarjim_client/errors', 'Tarjim client error file '.$errfile.' (line '.$errline.'): '.$errstr);
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

	## Check for mappings
	if (isset($config['mappings'])) {
		$mappings = $config['mappings'];
	}
	
	$namespace = '';
	if (isset($config['namespace'])) {
		$namespace = $config['namespace'];
	}

	$result = getTarjimValue($key, $namespace);
	$value = $result['value'];
	$assign_tarjim_id = $result['assign_tarjim_id'];
	$tarjim_id = $result['tarjim_id'];
	$full_value = $result['full_value'];

	## If type = image call _TM()
//	if (
//		(isset($config['type']) && 'image' == $config['type']) ||
//		(isset($full_value['type']) && 'image' == $full_value['type'])
//	) {
//		return _TM($key);
//	}

	## Check config keys and skip assigning tid and wrapping in a span for certain keys
	# ex: page title, input placeholders, image hrefs...
	if (
		(isset($config['is_page_title']) || in_array('is_page_title', $config)) ||
		(isset($config['skip_assign_tid']) || in_array('skip_assign_tid', $config)) ||
		(isset($config['skip_tid']) || in_array('skip_tid', $config)) ||
		(isset($full_value['skip_tid']) && $full_value['skip_tid'])
	) {
		$assign_tarjim_id = false;
	}

	## Debug mode
	if (!empty($debug)) {
		echo $mode ."\n";
		echo $key . "\n" .$value;
	}

	if (isset($config['do_addslashes']) && $config['do_addslashes']) {
		$result = addslashes($value);
	}

	if (isset($mappings)) {
		$value = injectValuesIntoTranslation($value, $mappings);
	}

	$sanitized_value = sanitizeResult($key, $value);

	## Restore default error handler
	restore_error_handler();

	if ($assign_tarjim_id) {
		$final_value = assignTarjimId($tarjim_id, $sanitized_value);
		return $final_value;
	}
	else {
		return strip_tags($sanitized_value);
	}
}

/**
 * Shorthand for _T($key, ['skip_tid'])
 * Skip assigning data-tid and wrapping in span
 * used with images, placeholders, title, select/dropdown
 */
function _TS($key, $config = []) {
	$config['skip_tid'] = true;
	return _T($key, $config);
}

/**
 * Alias for _TM()
 */
function _TI($key, $attributes) {
	return _TM($key, $attributes);
}

/**
 * Used for media
 * @param String $key key for media
 * @param Array $attributes attributes for media eg: class, id, width...
 * If received key doesn't have type:image return _T($key) instead
 */
function _TM($key, $attributes=[]) {
	set_error_handler('tarjimErrorHandler');
	
	$namespace = '';
	if (isset($attributes['namespace'])) {
		$namespace = $attributes['namespace'];
		unset($attributes['namespace']);
	}
	
	$result = getTarjimValue($key, $namespace);
	$value = $result['value'];
	$tarjim_id = $result['tarjim_id'];
	$full_value = $result['full_value'];

//	if (isset($full_value['type']) && 'image' == $full_value['type']) {
		$attributes_from_remote = [];
		$sanitized_value = sanitizeResult($key, $value);
		$final_value = 'src='.$sanitized_value.' data-tid='.$tarjim_id;

		if (array_key_exists('attributes', $full_value)) {
			$attributes_from_remote = $full_value['attributes'];
		}

		## Merge attributes from tarjim.io and those received from view
		# for attributes that exist in both arrays take the value from tarjim.io
		$attributes = array_merge($attributes, $attributes_from_remote);
		if (!empty($attributes)) {
			foreach ($attributes as $attribute => $attribute_value) {
				$final_value .= ' ' .$attribute . '="' . $attribute_value .'"';
			}
		}

		## Restore default error handler
		restore_error_handler();
		return $final_value;
//	}
//	## Not an image
//	# fallback to standard _T
//	else {
//		## Restore default error handler
//		restore_error_handler();
//		return _T($key);
//	}
}

/**
 * Get value for key from $_T global object
 * returns array with
 * value => string to render or media src
 * tarjim_id => id to assign to data-tid
 * assign_tarjim_id => boolean
 * full_value => full object for from $_T to retreive extra attributes if needed
 */
function getTarjimValue($key, $namespace = '') {
	set_error_handler('tarjimErrorHandler');
	global $_T;
		
	if (empty($namespace)) {
		$namespace = $_T['meta']['default_namespace'];
	}

	$active_language = $_T['meta']['active_language'];
	$original_key = $key;
	$key = strtolower($key);
	$assign_tarjim_id = false;
	$tarjim_id = '';
	$full_value = [];

	## Direct match
	if (isset($_T[$namespace][$active_language][$key]) && !empty($_T[$namespace][$active_language][$key])) {
		$mode = 'direct';
		if (is_array($_T[$namespace][$active_language][$key])) {
			$value = $_T[$namespace][$active_language][$key]['value'];
			$tarjim_id = $_T[$namespace][$active_language][$key]['id'];
			$assign_tarjim_id = true;
			$full_value = $_T[$namespace][$active_language][$key];
		}
		else {
			$value = $_T[$namespace][$active_language][$key];
		}
	}

	## Fallback key
	if (isset($_T[$namespace][$active_language][$key]) && empty($_T[$namespace][$active_language][$key])) {
		$mode = 'key_fallback';
		$value = $original_key;
	}

	## Empty fall back (return key)
	if (!isset($_T[$namespace][$active_language][$key])) {
		$mode = 'empty_key_fallback';
		$value = $original_key;
	}

	$result = [
		'value' => $value,
		'tarjim_id' => $tarjim_id,
		'assign_tarjim_id' => $assign_tarjim_id,
		'full_value' => $full_value,
	];

	## Restore default error handler
	restore_error_handler();

	return $result;
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

		if (file_exists($Tarjimclient->sanitized_html_cache_file) && filesize($Tarjimclient->sanitized_html_cache_file) && isset($active_language)) {
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

	if (file_exists($sanitized_html_cache_file) && filesize($sanitized_html_cache_file)) {
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
