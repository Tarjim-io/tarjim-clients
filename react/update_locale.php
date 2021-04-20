<?php

$project_id = '3';

$locale_file = __DIR__ . '/src/locale/locale.json';
$locale = @file_get_contents($locale_file);
$locale = @json_decode($locale, true);

$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$project_id;
$result = file_get_contents($endpoint);
$api_result = json_decode($result, true);

if (empty($locale) || $locale['meta']['results_last_update'] < $api_result['meta']['results_last_update']) {
	file_put_contents($locale_file, json_encode($api_result));
	echo "Locale file updated" . PHP_EOL;
}
