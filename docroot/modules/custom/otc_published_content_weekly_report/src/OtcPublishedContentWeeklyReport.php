<?php
/**
 * @file
 * Contains \Drupal\otc_published_content_weekly_report\OtcPublishedContentWeeklyReport.
 */

namespace Drupal\otc_published_content_weekly_report;

use Drupal\views\Views;

/**
 * Service class for sending publish content weekly report over email.
 * The email contains xlsx file as attachment.
 */
class OtcPublishedContentWeeklyReport {

    public function sendContentReportEmail() {

        // Create directory if it doesnt exist
        $dir = 'public://otc-report/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, TRUE);
        }

        // Get report from database and convert it in xlsx format.
        $view = Views::getView('category_level_report');
        $view->setDisplay('data_export_1');
        $output = $view->render();
        $result = (string) $output['#markup'];
        // Save report on server.
        file_unmanaged_save_data($result, 'public://otc-report/content-report.xlsx', FILE_EXISTS_REPLACE);

        $message = "";
        $message .= "Hi Everyone\n";
        $message .= " Please find attached content-report.xlsx file for published content details.\n";

        $attachment = array(
            'filepath' => 'sites/default/files/otc-report/content-report.xlsx',
            'filename' => 'content-report.xlsx',
            'filemime' => 'application/vnd.ms-excel'
        );

        $config = \Drupal::config('otc_group_email.settings');
        $otc_group_email = $config->get('otc_group_email');
        // Send report over email as xlsx attachment.
        // Email subject change for AWS
        \Drupal::service('plugin.manager.mail')->mail('otc_published_content_weekly_report', 'content_report', $otc_group_email, 'en', ['subject'=> 'AWS-JOB-Weekly Published Content', 'message' => $message, 'attachments' => $attachment]);

        return drupal_set_message(t('An email notification has been sent to @email ', array('@email' => $otc_group_email)), "status", TRUE);

    }

}
