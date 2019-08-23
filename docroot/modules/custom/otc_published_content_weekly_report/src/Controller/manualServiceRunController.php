<?php

namespace Drupal\otc_published_content_weekly_report\Controller;

class manualServiceRunController {

	public function manualServiceRun() {
        // Add productimport keyword for security purpose
        if($_GET['api'] == 'weeklyreport'){          
            $importer = \Drupal::service('otc_published_content_weekly_report.default')->sendContentReportEmail();
            $markup = "<p> Job has been triggered</p>";
            return array('#markup' => $markup);  	
        } else {
            $markup = "<p> Please enter correct url for content weekly report job.</p>";
            return array('#markup' => $markup,);
        }        
    }
}