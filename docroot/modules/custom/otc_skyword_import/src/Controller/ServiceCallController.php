<?php

namespace Drupal\otc_skyword_import\Controller;

class ServiceCallController {

	public function callImportService() {   
            
            
            
//      \Drupal::service('otc_skyword_import.default')->queueImportJobs();    
//      	$markup = "<p> Job has been triggered</p>";
//      	return array('#markup' => $markup,);   
        
        
        if($_GET['api'] == 'skywordimport'){ 
            drupal_flush_all_caches();  
            \Drupal::service('otc_skyword_import.default')->queueImportJobs();     
            $markup = "<p> Job has been triggered successfully.</p>";
            return array('#markup' => $markup,);
        }else{
            $markup = "<p> Job has been triggered successfully.</p>";
            return array('#markup' => $markup,);
        }
        
	}
}