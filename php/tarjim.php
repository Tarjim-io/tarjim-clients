<?php

/**
 *
 */
class TarjimShell extends Shell {

  /**
   *
   */
  public function updateTarjimLocale() {
    $project_id = Configure::read('TARJIM_PROJECT_ID');
    $apikey = Configure::read('TARJIM_APIKEY');
    $default_namespace = Configure::read('TARJIM_DEFAULT_NAMESPACE');
    $additional_namespaces = Configure::read('TARJIM_ADDITIONAL_NAMESPACES');


    ## Set translation keys
    $Tarjim = new Tarjimclient($project_id, $apikey, $default_namespace, $additional_namespaces);
    $translations = $Tarjim->getLatestFromTarjim();
    $Tarjim->updateCache($translations);
  }

  /**
   *
   */
  public function exportKeysFromView($file_path = null) {

    $cli = false;

    #check if the function called from api or cli
		if (php_sapi_name() == 'cli') {
      $cli = true;
		}

    $project_id = Configure::read('TARJIM_PROJECT_ID');
    $apikey = Configure::read('TARJIM_APIKEY');
    $default_namespace = Configure::read('TARJIM_DEFAULT_NAMESPACE');
    $additional_namespaces = Configure::read('TARJIM_ADDITIONAL_NAMESPACES');

    ## Set translation keys
    $Tarjim = new Tarjimclient($project_id, $apikey, $default_namespace, $additional_namespaces);
    $active_languages = $Tarjim->getActivelanguages();

    if(empty($active_languages)) {
      if($cli) {
        echo 'curl error';
        exit();
      }
      else {
        return 'curl error';
      }
    }

    $active_languages = json_decode($active_languages, true);

    if(isset($active_languages['result']['error'])){
      if($cli) {
        echo 'Error:'.$active_languages['result']['error'];
        exit();
      }
      else {
        return 'Error:'.$active_languages['result']['error'];
      }
    }

    $active_languages = $active_languages['result']['data'];

    #Dir or file name
    $view_file = trim($file_path);

    $path_to_file = ROOT.'/'.APP_DIR.'/views/'.$view_file ;
    $path_to_tmp = ROOT.'/'.APP_DIR.'/tmp/tmp.txt';
    $path_to_keys = ROOT.'/'.APP_DIR.'/tmp/keys.txt';
    $path_to_csv = ROOT.'/'.APP_DIR.'/tmp/tarjim_Keys.csv';

    #Get all the line that contains _T and put it in tmp file
    shell_exec('grep -r _T '.$path_to_file.'>'.$path_to_tmp);

    /*
     * Put all the _T in new line
     * From:
     * text1 _T('key1') text2 _T('key2')
     * To:
     * text1
     * _T('key1') string
     * _T('key2')
     */
    shell_exec('sed -i -e \'s/_T/\n_T/g\' '.$path_to_tmp);

    # Take all line that contains _T only from tmp (remove lines like "text1")
    shell_exec('grep -r _T '.$path_to_tmp.'>'.$path_to_keys);

    # Remove all before _T( or _TS or _TM(
    shell_exec('sed -i  \'s/^.*_T[A-Z]\?(//\' '.$path_to_keys);

    # Remove all after )
    shell_exec('sed -i \'s/).*//\' '.$path_to_keys);
    # Remove keys that contains $ ($title)
    shell_exec('sed -i \'/\$/d\' '.$path_to_keys);

    $keys = [];

    #To patren to remove the secend param in _T()
    $pattern = '/\',\'|\' , \'|\', \'|\' ,\'|","|", "|" ,"|" , "/';

    $keys_file = fopen($path_to_keys, "r");
    if ($keys_file) {
      while (($line = fgets($keys_file)) !== false) {

        # Check if there are two parameters in _T() and remove second one
        if (preg_match_all($pattern,$line)) {
          $key = preg_split($pattern,$line)[0];
          $keys[] = substr($key, 1);
        }else{

          # Check if the key is already in the array
          if (!in_array($line, $keys)) {
            $keys[] = preg_replace('~^[\'"]?(.*?)[\'"]?$~', '$1', $line);;
          }
        }
      }
      fclose($keys_file);


      $csv_header = $active_languages;
      array_unshift($csv_header, "key");

      $header_length = count($csv_header);
      $csv_object[] = $csv_header;

      foreach ($keys as $key) {

        $tmp = array_fill(0, $header_length, '');
        $tmp[0] = $key;
        $tmp[1] = $key;

        $csv_object[] = $tmp;
      }

      // Save csv_object in file
      $csv_output = fopen($path_to_csv, 'w');
      foreach ($csv_object as $row) {
        // generate csv lines from the inner arrays
        fputcsv($csv_output, $row);
      }

      fclose($csv_output);

    }
    else {
      die('There is no keys');
    }
    echo 'You can download CSV file from https://<YOPUR DOMAIN>/api/v1/export-keys-from-view'. PHP_EOL;
  }

}
