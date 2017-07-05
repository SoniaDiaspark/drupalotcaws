<?php

namespace Drupal\otc_skyword_import;

use SimpleXMLElement;

class ProjectMapper implements FeedMapperInterface {

  public function map(SimpleXMLElement $document, $project = []) {
    $project = $this->fileMap($document, $project);
    $project = $this->multiFieldMap($document, $project);
    $project = $this->linkMap($document, $project);

    foreach ($document as $key => $value) {
      if ( $this->isFileKey($key) ) continue;
      if ( $this->isMultiValue($key) ) continue;

      if ( ($field = $this->straightMapping($key)) ) {
        $project[$field] = (string) $value;
        continue;
      }

      switch ($key) {
        case 'metainformation':
        case 'additional_project_fields':
        case 'featured_data':
          // recurse for simple mappings
          $project = $this->map($value, $project);
          break;

        // ignored skyword fields
        case 'author':
        case 'photo_article_inspiration':
        case 'project_step_data':
        case 'field_howtouse_description':
        case 'publishedDate':
        case 'keyword':
        case 'assignment_title':
        case 'otc_featured_products':
        case 'action':
        case 'field_related_look':
        case 'field_short_description': // not in CMS, can add later
        case 'itemsused':
        case 'field_items_needed':
        case 'link':
        case 'steptitle':
        case 'field_article_thumb_img_2x':
        case 'field_article_thumb_img_2x_url':
        case 'field_article_thumb_img_2x_name':
        case 'numberitmakes':
        case 'field_products_used':
          break;
        default:
          echo "UNMAPPED KEY: $key\n";
      }
    }

    if ( isset($document->field_products_used) ) {
      print_r($project['field_products']);
      $skus = !empty($project['field_products']) ? explode(',', (string) $project['field_products']) : [];
      print_r($skus);
      $skus = array_filter(array_unique(array_merge(
        $skus, array_map(function($sku){
          return trim($sku);
        }, explode(',', (string) $document->field_products_used))
      )));
      if ( ! empty($skus) ) {
        $project['field_products'] = implode(',', $skus);
      }
    }

    if ( isset($document->itemsused) ) {
      print_r($project['field_products']);
      $skus = !empty($project['field_products']) ? explode(',', (string) $project['field_products']) : [];
      print_r($skus);
      $skus = array_filter(array_unique(array_merge(
        $skus, array_map(function($sku){
          return trim($sku);
        }, explode(',', (string) $document->itemsused))
      )));
      if ( ! empty($skus) ) {
        $project['field_products'] = implode(',', $skus);
      }
    }

    if ( isset($document->field_items_needed) ) {
      $items = [];
      $items = array_filter(array_unique(array_merge(
        $items, array_map(function($item){
          return trim($item);
        }, explode(',', (string) $document->field_items_needed))
      )));
      if ( ! empty($items) ) {
        $project['field_items_needed'] = $items;
      }
    }

    if ( isset($document->field_howtouse_description) && (string) $document->field_howtouse_description) {
      $project['field_howtouse_description'] = [
        'title' => 'HOW TO USE',
        'field_display_title' => 'HOW TO USE',
        'field_description' => (string) $document->field_howtouse_description,
      ];
    }

    if ( isset($document->project_step_data) ) {
      $index = 0;
      foreach ( $document->project_step_data as $key => $step ) {
        $stepData = $this->map($step);
        if ($stepData) {
          $stepIndex = $index++;
          if ( ! $stepData['title'] ) {
            $stepData['title'] = 'Step ' . ($stepIndex + 1);
          }
          $project['field_step'][$stepIndex] = $stepData;
        }
      }

      if ( ! empty($project['field_howtouse_description']) ) {
        $project['field_step'][$index++] = $project['field_howtouse_description'];
        unset($project['field_howtouse_description']);
      }

      if ( ! empty($project['field_step']) ) {
        print_r($project['field_products']);
        $skus = !empty($project['field_products']) ? explode(',', (string) $project['field_products']) : [];
        print_r($skus);
        foreach ( $project['field_step'] as $step ) {
          if ( $step['field_products'] ) {
            $skus = array_filter(array_unique(array_merge(
              $skus, array_map(function($sku){
                return trim($sku);
              }, explode(',', $step['field_products']))
            )));
          }
        }
        if ( ! empty($skus) ) {
          $project['field_products'] = implode(',', $skus);
        }
      }
    }

    if ( $project['field_display_title'] ) {
      $project['title'] = $project['field_display_title'];
    }
    return $project;
  }


  protected function straightMapping($key = '') {
    $mappings = [
      // feed => drupal
      'id' => 'field_skyword_id',
      'title' => 'field_display_title',
      'field_meta_title' => 'field_meta_title',
      'field_skill' => 'field_skill',
      'field_skill_level' => 'field_skill',
      'field_photo_credit' => 'field_photo_credit',
      'field_time_min' => 'field_time_min',
      'field_time_max' => 'field_time_max',
      'field_meta_description' => 'field_meta_description',
      'field_meta_keywords' => 'field_meta_keywords',
      'seoTitle' => 'field_meta_title',
      'seoDescription' => 'field_meta_description',
      'meta_keywords' => 'field_meta_keywords',
      'authorId' => 'field_contributor', // further processing needed
      'field_product_own' => 'field_product_own',
      'field_product_need_description' => 'field_needed_description',
      'body' => 'field_description',
      'field_description' => 'field_description',
      // 'field_short_description' => 'field_short_description', // not in CMS, can add later
    ];

    return ( in_array($key, array_keys($mappings)) ? $mappings[$key] : false );
  }

  protected static function multiValue() {
    // source/skyword => target/drupal
    return [
    ];
  }

  protected function isMultiValue($fieldName) {
    return in_array($fieldName, array_keys(self::multiValue()));
  }

  protected function multiFieldMap(SimpleXMLElement $document, $project = []) {
    foreach (self::multiValue() as $source => $target) {
      if ( isset($document->{$source}) ) {
        $project[$target] = [];
        foreach ($document->{$source} as $key => $value) {
          $project[$target][] = (string) $value;
        }
      }
    }

    return $project;
  }

  protected static function linkFieldMappings() {

    return [
      'field_skyword_related_look' => [
        'uri' => 'field_related_look',
        'title' => 'field_related_look',
      ],
    ];
  }

  protected function isLinkKey($key) {
    foreach ( self::linkFieldMappings() as $fieldName => $skywordFields ) {
      if ( in_array($key, $skywordFields) ) return true;
    }

    return false;
  }

  protected function linkMap(SimpleXMLElement $document, $project = []) {

    foreach ( self::linkFieldMappings() as $fieldName => $skywordFields ) {
      if ( isset($document->{$skywordFields['uri']}) && isset($document->{$skywordFields['title']}) ) {
        $project[$fieldName] = [
          'uri' => (string) $document->{$skywordFields['uri']},
          'title' => (string) $document->{$skywordFields['title']},
        ];
      }
    }

    return $project;
  }


  protected static function fileFieldMappings() {
    // source/skyword => target/drupal
    return [
      'field_1824x1371_img' => 'field_1824x1371_img',
      'field_1824x1371_img_url' => 'field_1824x1371_img',
      'field_1824x1371_img_name' => 'field_1824x1371_img',
      'field_hero_m_img_2x' => 'field_828x828_img',
      'field_hero_m_img_2x_url' => 'field_828x828_img',
      'field_hero_m_img_2x_name' => 'field_828x828_img',
      'field_900x677_img' => 'field_900x677_img',
      'field_900x677_img_url' => 'field_900x677_img',
      'field_900x677_img_name' => 'field_900x677_img',
      'field_hero_bleed_d_img_2x' => 'field_3200x1391_img',
      'field_hero_bleed_d_img_2x_url' => 'field_3200x1391_img',
      'field_hero_bleed_d_img_2x_name' => 'field_3200x1391_img',
      'field_828x473_img' => 'field_828x473_img',
      'field_828x473_img_url' => 'field_828x473_img',
      'field_828x473_img_name' => 'field_828x473_img',
      'field_card_tile_img_2x' => 'field_896x896_img',
      'field_card_tile_img_2x_url' => 'field_896x896_img',
      'field_card_tile_img_2x_name' => 'field_896x896_img',
      'field_929x1239_img' => 'field_929x1239_img',
      'field_929x1239_img_url' => 'field_929x1239_img',
      'field_929x1239_img_name' => 'field_929x1239_img',
      'field_project_PDF' => 'field_download_file',
      'field_project_PDF_url' => 'field_download_file',
      'field_project_PDF_name' => 'field_download_file',
      'field_step_img_2x' => 'field_1280x962_multi_img',
      'field_step_img_2x_url' => 'field_1280x962_multi_img',
      'field_step_img_2x_name' => 'field_1280x962_multi_img',
      'field_hero_half_d_img_2x' => 'field_1824x1371_img',
      'field_hero_half_d_img_2x_url' => 'field_1824x1371_img',
      'field_hero_half_d_img_2x_name' => 'field_1824x1371_img',
    ];
  }

  protected function isFileKey($key) {
    return in_array($key, array_keys(self::fileFieldMappings()));
  }

  protected function fileMap(SimpleXMLElement $document, $project = []) {
    $files = [];
    $fileFieldMappings = self::fileFieldMappings();
    foreach ( $fileFieldMappings as $sourceElement => $fieldName ) {
      // Skip missing sources
      if ( ! isset($document->{$sourceElement}) ) continue;
      // Skip name, url sources
      if ( preg_match('/(_url|_name)$/', $sourceElement) ) continue;

      $files[$fieldName] = [];
      $items = [
        'url' => [],
        'name' => [],
      ];

      // gather
      $urlSources = $sourceElement . "_url";
      foreach ( $document->{$urlSources} as $value ) {
        $items['url'][] = (string) $value;
      }
      $nameSources = $sourceElement . "_name";
      foreach ( $document->{$nameSources} as $value ) {
        $items['name'][] = (string) $value;
      }

      // collate
      foreach ($items['url'] as $index => $value) {
        $files[$fieldName][] = [
          'url' => $value,
          'name' => $items['name'][$index],
        ];
      }


      if ( ! empty($items['url']) ) {
        $project[$fieldName] = $files[$fieldName];
      }
    }

    return $project;
  }
}
