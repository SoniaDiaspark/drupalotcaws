<?php

namespace Drupal\otc_publish_node_details\Controller;

class manualServiceRunController {

	public function manualServiceRun() {    
        $importer = \Drupal::service('otc_publish_node_details.default')->sendPublishedContentEmail();
      	$markup = "";
      	return array('#markup' => $markup);  	
	}
}