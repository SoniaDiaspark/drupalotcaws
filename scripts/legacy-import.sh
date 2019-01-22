#!/bin/bash

# This script can only be run from the Acquia cloud server
# 
echo "Queueing legacy import jobs."
/usr/local/drush8/drush -v @otc.$AH_SITE_ENVIRONMENT scr /var/www/html/otc.$AH_SITE_ENVIRONMENT/drush-scripts/legacy-import.php

echo "Processing legacy import jobs."
while [ $(/usr/local/drush8/drush -v @otc.$AH_SITE_ENVIRONMENT sqlq 'SELECT COUNT(*) FROM queue') -gt 0 ];
  do /usr/local/drush8/drush -v @otc.$AH_SITE_ENVIRONMENT cron;
done
