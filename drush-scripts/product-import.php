<?php

define('FORCE_PRODUCT_UPDATE', 'force');

list(,,$force) = drush_get_arguments();

/**
 * Queue import jobs from product export.
 * Use drush @site.env scr path/to/product-import.php to execute this script.
 */
Drupal::service('otc_product_import.default')->batchImport($force === FORCE_PRODUCT_UPDATE);
