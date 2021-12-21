<?php
require_once(__DIR__.'/config.php');

$project_id = PROJECT_ID;

$locale_file = LOCALE_DIR; 

$apikey = APIKEY;

$locale = @file_get_contents($locale_file);
$locale = @json_decode($locale, true);

$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$project_id.'?apikey='.$apikey;
$result = file_get_contents($endpoint);
$api_result = json_decode($result, true);

if (empty($locale) || $locale['meta']['results_last_update'] < $api_result['meta']['results_last_update']) {
  file_put_contents($locale_file, json_encode($api_result));
  echo "Locale file updated" . PHP_EOL;
}
else {
  echo "Locale up to date" . PHP_EOL;
}
