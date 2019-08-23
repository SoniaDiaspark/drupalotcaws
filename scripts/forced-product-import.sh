#!/bin/bash

# This script can only be run from the Acquia cloud server

echo "Queueing product import jobs."
/usr/local/drush8/drush @otc.$AH_SITE_ENVIRONMENT scr /var/www/html/otc.$AH_SITE_ENVIRONMENT/drush-scripts/product-import.php force

echo "Processingimport jobs."
while [ $(/usr/local/drush8/drush @otc.$AH_SITE_ENVIRONMENT sqlq 'SELECT COUNT(*) FROM queue') -gt 0 ];
  do /usr/local/drush8/drush @otc.$AH_SITE_ENVIRONMENT cron;
done
