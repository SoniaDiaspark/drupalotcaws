<?php

namespace Drupal\otc_skyword_import\Controller;

class ServiceCallController {

	public function callImportService() {    
        $importer = \Drupal::service('otc_skyword_import.default')->queueImportJobs();    
      	$markup = "<p> Job has been triggered</p>";
      	return array('#markup' => $markup,);  	
	}
}