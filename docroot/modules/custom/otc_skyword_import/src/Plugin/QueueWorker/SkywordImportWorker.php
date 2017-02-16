<?php

namespace Drupal\otc_skyword_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Skyword Import Queue Worker.
 *
 * @QueueWorker(
 *   id = "otc_skyword_import",
 *   label = @Translation("Skyword Importer"),
 *   cron = {"time" = 60}
 * )
 */
class SkywordImportWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($job) {
    $Importer = \Drupal::service('otc_skyword_import.default');
    try {
      $Importer->create($job['document'], $job['type']);
    } catch(Exception $e) {
      $Importer->getLogger()->error("Error creating @type @title. Message: @message", [
        '@type' => $data['type'],
        '@title' => $data['document']['title'],
        '@message' => $e->getMessage()
      ]);

      $Importer->sendFailureMessages('worker', t("Error creating @type @title. Message: @message", [
        '@type' => $data['type'],
        '@title' => $data['document']['title'],
        '@message' => $e->getMessage()
      ]));
    }

  }
}
