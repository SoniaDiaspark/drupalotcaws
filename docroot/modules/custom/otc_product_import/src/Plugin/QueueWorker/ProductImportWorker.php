<?php

namespace Drupal\otc_product_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Product Import Queue Worker.
 *
 * @QueueWorker(
 *   id = "otc_product_import",
 *   label = @Translation("Product Importer"),
 *   cron = {"time" = 60}
 * )
 */
class ProductImportWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($lines) {
    $Importer = \Drupal::service('otc_product_import.default');

    foreach ( $lines as $data ) {
      try {
        if ( ($nids = $Importer->product_exists($data['field_sku'])) ) {
          $Importer->update(current($nids), $data);
          $Importer->getLogger()->notice("Updating product @sku", ["@sku" => $data['field_sku']]);
        } else {
          $Importer->create($data);
          $Importer->getLogger()->notice("Creating product @sku", ["@sku" => $data['field_sku']]);
        }
      } catch(Exception $e) {
        $error = $Importer->getLogger()->error("Error creating or updating product @title (@sku). Message: @message", [
          '@title' => $data['title'],
          '@sku' => $data['field_sku'],
          '@message' => $e->getMessage()
        ]);
      }
    }
  }
}
