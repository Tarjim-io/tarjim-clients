<?php
App::import('Controller', 'ApiV1');

class TarjimV1Controller extends ApiV1Controller {

  /**
   *
   */
  public function getFrontendLocale() {
    if (empty($_GET['locale_last_updated'])) {
      $frontend_locale_last_updated = 0;
    }
    else {
      $frontend_locale_last_updated = $_GET['locale_last_updated'];
    }

    $Tarjim = new Tarjimclient();
		$Tarjim->project_id = Configure::read('TARJIM_PROJECT_ID');
    $api_translations = $Tarjim->getTranslations();

    if (!empty($api_translations) && $frontend_locale_last_updated < $api_translations['meta']['results_last_update']) {
      $result = $api_translations['results']['react'];
    }
    else {
      $result = 'locale up to date';
    }

    $this->outputSuccessfulApi($result);
  }

  /**
   *
   */
  public function updateLocaleCache() {
		if (empty($_POST['translations'])) {
			$error['message'] = 'missing field translations';
			$this->outputFailedApi($error, 400);
    }

		$latest = json_decode($_POST['translations'], true);

    $Tarjim = new Tarjimclient();
		$Tarjim->project_id = Configure::read('TARJIM_PROJECT_ID');
    $result = $Tarjim->updateCache($latest);
    
    $this->outputSuccessfulApi();
  }
}
