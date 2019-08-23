<?php

namespace Drupal\otc_legacy_import;

use \DateTime;
use \DateTimeZone;

use \PDO;

$tz = \Drupal::config('system.date')->get('timezone.default');
$dateTimeZone = new DateTimeZone($tz);
$args = drush_get_arguments();
$date = false;
if (
  isset($args[2]) &&
  ! (
    preg_match('/\d{4}-\d{2}-\d{2}/', $args[2])
    && ( $date = DateTime::createFromFormat("Y-m-d H:i:s", $args[2] . " 00:00:00", $dateTimeZone) )
  )
) {
  echo "Invalid date.\n";
  drush_set_error('Usage: drush @<alias> scr legacy-import.php [yyyy-mm-dd]');
  die();
}

$dateString = '';
if ($date) {
  $dateString = $date->format('Y-m-d');
}

$importer = \Drupal::service('otc_legacy_import.default');
$importer->queueImportJobs($dateString);
