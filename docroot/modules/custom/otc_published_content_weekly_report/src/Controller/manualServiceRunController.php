<?php

namespace Drupal\otc_published_content_weekly_report\Controller;

class manualServiceRunController {

	public function manualServiceRun() {    
        $importer = \Drupal::service('otc_published_content_weekly_report.default')->sendContentReportEmail();
      	$markup = "";
      	return array('#markup' => $markup);  	
	}
}