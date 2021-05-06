<?php 

/**
 *
 */
class TarjimShell extends Shell {
	
	/**
	 *
	 */
	public function updateTarjimLocale() {
		$Tarjim = new TarjimClient(); 
		$Tarjim->project_id = Configure::read('TARJIM_PROJECT_ID');
		$translations = $Tarjim->getLatestFromTarjim();
		$Tarjim->updateCache($translations);
	}
}
