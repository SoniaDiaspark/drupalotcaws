<?php

namespace Drupal\otc_product_import\Controller;

define('FORCE_PRODUCT_UPDATE', 'force');

class ProductCallController {

	public function callProductService() {
            // Add productimport keyword for security purpose
           if($_GET['api'] == 'productimport'){  
                $importer = \Drupal::service('otc_product_import.default')->batchImport($force === FORCE_PRODUCT_UPDATE);    
                $markup = "<p> Job has been triggered</p>";
                return array('#markup' => $markup);
           } else {
                $markup = "<p> Please enter correct url for product import job.</p>";
                return array('#markup' => $markup,);
           }        
	}
}