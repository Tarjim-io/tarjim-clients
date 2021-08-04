<?php

$project_id = '3';

$locale_file = __DIR__ . '/../../../locale/locale.json';

$endpoint = 'http://tarjim.io/translationkeys/json/full/'.$project_id;
$result = file_get_contents($endpoint);
$api_result = json_decode($result, true);

file_put_contents($locale_file, json_encode($api_result));
echo "Locale file updated" . PHP_EOL;
