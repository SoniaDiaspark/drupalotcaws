<?php

namespace Drupal\otc_skyword_import\Controller;

class ServiceCallController {

	public function callImportService() {
        
            // Add skywordimport keyword for security purpose
            if($_GET['api'] == 'skywordimport'){  
                \Drupal::service('otc_skyword_import.default')->queueImportJobs();     
                $markup = "<p> Job has been triggered successfully.</p>";
                return array('#markup' => $markup,);
            }else{
                $markup = "<p> Please enter correct url for skyword import job.</p>";
                return array('#markup' => $markup,);
            }
        
	}
}