<?php

namespace Drupal\otc_skyword_update;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PublishNodeDetails {

    public function emailsend() {
        /**
         * Get current date
         */
        //$config = \Drupal::config('system.date');
        //$config_data_default_timezone = $config->get('timezone.default');
        //$cur_date = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d', $config_data_default_timezone);

        /**
         * Select data from node details table
         */
        $query = \Drupal::database()->select('publish_node_details', 'nd');
        $query->fields('nd', ['node_id', 'node_type', 'node_title', 'date', 'skyword_id']); 
       // $query->condition('nd.date', $cur_date, '=');
        $result = $query->execute()->fetchAll();

        if (!empty($result)) {
            $node_load_id = array();
            $message = "";
            $message .= "<p>Today's published node -</p> \n\n\n <br /><br /><br/> ";
            $message .= " </br></br> ";
            foreach ($result as $result_data) {
                
                if ($result_data->node_id != "" && $result_data->node_id != 0) {                  
                     $node_load_id = $result_data->node_id;               
                } else {
                   $node_load_id = "NA";
                }

                $message .= $node_load_id . " | " . $result_data->skyword_id . " | " . $result_data->node_type . " | " . ucfirst($result_data->node_title);
                $message .= "\n\n<br><br>";

            }  
            
            $config = \Drupal::config('otc_group_email.settings');
            $otc_group_email = $config->get('otc_group_email');

            $key = "pnd";
            \Drupal::service('plugin.manager.mail')->mail('otc_skyword_import', $key, $otc_group_email, 'en', ['message' => $message]);

            $msg = "status";
            $message = t('An email notification has been sent to @email ', array('@email' => $to));
            \Drupal::logger('mail-log')->notice($message);

            /**
             * Truncate table and redirect
             */
            $querytruncate = \Drupal::database()->truncate('publish_node_details');
            $resulttruncate = $querytruncate->execute();

            $response = new RedirectResponse(\Drupal::url('<front>'));
            $response->send();

            drupal_set_message(t('An email notification has been sent to @email ', array('@email' => $to)), $msg, TRUE);

            exit;
        } else {
            $response = new RedirectResponse(\Drupal::url('<front>'));
            $response->send();
            drupal_set_message(t('No result found'), 'error', TRUE);
            die;
        }
    }

}
