<?php

namespace Drupal\otc_legacy_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Legacy Import Queue Worker.
 *
 * @QueueWorker(
 *   id = "otc_legacy_import",
 *   label = @Translation("Legacy Importer"),
 *   cron = {"time" = 60}
 * )
 */
class LegacyImportWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($job) {
    $Importer = \Drupal::service('otc_legacy_import.default');
    try {
      if ( $job['type'] === 'image' ) {
        $Importer->downloadImage($job['document']['sourceUrl'], $job['document']['targetFilePath']);
      } else {
        $Importer->create($job['document'], $job['type']);
      }
    } catch(Exception $e) {
      $Importer->getLogger()->error("Error creating @type @title. Message: @message", [
        '@type' => $data['type'],
        '@title' => $data['document']['title'],
        '@message' => $e->getMessage()
      ]);

      throw new \Drupal\Core\Queue\RequeueException;
    }

  }
}
