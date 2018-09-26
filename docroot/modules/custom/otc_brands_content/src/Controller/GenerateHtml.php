<?php

namespace Drupal\otc_brands_content\Controller;

/**
 *
 */
class GenerateHtml {

  /**
   *
   */
  public function GetHtml() {

    global $base_url;

    $page_alias = \Drupal::request()->query->get('page_title');

    if ($page_alias != "") {

      $path = \Drupal::service('path.alias_manager')->getPathByAlias('/' . $page_alias);
      // To avoid caching.
      $url = $base_url . $path . '?q=' . time();

      $page_alias_explode = explode('?', $page_alias);

      if (isset($page_alias_explode[0])) {
        $filename = strtolower($page_alias_explode[0]) . '-home-page-static-content.html';
      }
      if (function_exists('file_get_html')) {
        $html = file_get_html($url);
        // Point to the body, then get the innertext.
        if ($html) {
            
           $otchtml = $html->find('article', 0)->innertext;
           
          if (!is_dir('public://otc_brands')) {
            mkdir('public://otc_brands', 0755, TRUE);
          }

          $dir = 'public://otc_brands';
          if (is_dir($dir)) {
            if ($dh = opendir($dir)) {

              if ($filename != "") {
                $my_file = 'public://otc_brands/' . $filename;
                $handle = fopen($my_file, 'w') or die('Cannot open file:  ' . $my_file);
                $my_file = 'public://otc_brands/' . $filename;
                $handle = fopen($my_file, 'w') or die('Cannot open file:  ' . $my_file);
                $data = $otchtml;
                if (fwrite($handle, $data) === FALSE) {
                  echo "Cannot write to file ($filename)";
                  fclose($my_file);
                  exit;
                }
                else {
                  $markup = "<p> HTML has been generated Successfully </p>";
                  return ['#markup' => $markup];
                }
              }
              closedir($dh);
            }
          }
        }
      }
      else {
        $markup = "<p> Page title is not correct </p>";
        return ['#markup' => $markup];
      }
    }
  }

}
