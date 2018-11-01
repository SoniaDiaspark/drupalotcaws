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
           
    $content_section = \Drupal::request()->query->get('content_section'); 
    
    $content_section = explode('?', $content_section);
    
    if (isset($content_section[0])) {
       $content_section = strtolower($content_section[0]); 
    }
    
    

      $path = \Drupal::service('path.alias_manager')->getPathByAlias('/' . $page_alias);
      // To avoid caching.
      $url = $base_url . $path . '?q=' . time();

      $page_alias_explode = explode('?', $page_alias);
      
      if (isset($page_alias_explode[0])) {
        $page_url_alias = strtolower($page_alias_explode[0]);
      }
      
    
      if($page_url_alias !="" && $content_section !="" ){
        $filename = $page_url_alias.'-'.$content_section.'-static-content.html'; 
      }
      
      if($page_url_alias !="" && $content_section =="" ){
        $filename = strtolower($page_url_alias) . '-home-page-static-content.html'; 
      }
      
      
      $otchtml = '';
      if (function_exists('file_get_html')) {
        $html = file_get_html($url);
        // Point to the body, then get the innertext.
        if ($html) {
            
           if($content_section == ''){
            $otchtml = $html->find('article', 0)->innertext;        
           }             
           if($content_section == 'header'){
              $otchtml = '<div class="header-module header-module_2">' . $html->find('div.otc-header-module-2', 0)->innertext . '</div>';
              $otchtml .= '<div class="header-module header-module_3">' . $html->find('div.otc-header-module-3', 0)->innertext . '</div>';
              $otchtml .= '<div class="header-module header-module_4">' . $html->find('div.otc-header-module-4', 0)->innertext . '</div>';
              $otchtml .= '<div class="header-module header-module_5">' . $html->find('div.otc-header-module-5', 0)->innertext . '</div>';
           }           

           if($content_section == 'footer'){
              $otchtml  = '<div class="footer-module">' . $html->find('div.otc-footer-module', 0)->innertext . '</div>';
              $otchtml .= '<div class="footer-module footer-module_1">' . $html->find('div.otc-footer-module-1', 0)->innertext . '</div>';
              $otchtml .= '<div class="footer-module footer-module_2">' . $html->find('div.otc-footer-module-2', 0)->innertext . '</div>';
              $otchtml .= '<div class="footer-module footer-module_3">' . $html->find('div.otc-footer-module-3', 0)->innertext . '</div>';
              $otchtml .= '<div class="footer-module footer-module_4">' . $html->find('div.otc-footer-module-4', 0)->innertext . '</div>';
           }           
           
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
