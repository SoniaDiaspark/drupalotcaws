<?php

namespace Drupal\otc_skyword_import;

use SimpleXMLElement;

class RecipeMapper implements FeedMapperInterface {

  public function map(SimpleXMLElement $document, $recipe = []) {
    $recipe = $this->fileMap($document, $recipe);

    foreach ($document as $key => $value) {
      if ( $this->isFileKey($key) ) continue;

      if ( ($field = $this->straightMapping($key)) ) {
        $recipe[$field] = (string) $value;
        continue;
      }

      switch ($key) {
        case 'additional_recipe_requirements':
          // recurse for simple mappings
          $recipe = $this->map($value, $recipe);
          break;

        // ignored skyword fields
        case 'recipe_step_requirements':
        case 'publishedDate':
        case 'keyword':
        case 'assignment_title':
        case 'otc_featured_products':
        case 'action':
          break;
        default:
          echo "UNMAPPED KEY: $key\n";
      }
    }

    if ( isset($document->recipe_step_requirements) ) {
      $index = 0;
      foreach ( $document->recipe_step_requirements as $key => $step ) {
        $stepData = $this->map($step);
        if ($stepData) {
          $stepIndex = $index++;
          if ( ! $stepData['title'] ) {
            $stepData['title'] = 'Step ' . ($stepIndex + 1);
          }
          $recipe['field_step'][$stepIndex] = $stepData;
        }
      }

      if ( ! empty($recipe['field_step']) ) {
        $skus = [];
        foreach ( $recipe['field_step'] as $step ) {
          if ( $step['field_products'] ) {
            $skus = array_filter(array_unique(array_merge(
              $skus, array_map(function($sku){
                return trim($sku);
              }, explode(',', $step['field_products']))
            )));
          }
        }
        if ( ! empty($skus) ) {
          $recipe['field_products'] = implode(',', $skus);
        }
      }
    }

    if ( $recipe['field_display_title'] ) {
      $recipe['title'] = $recipe['field_display_title'];
    }
    return $recipe;
  }

  protected function straightMapping($key = '') {
    $mappings = [
      'id' => 'field_skyword_id',
      'title' => 'field_display_title',
      'field_meta_title' => 'field_meta_title',
      'field_skill' => 'field_skill',
      'field_time_min' => 'field_time_min',
      'field_time_max' => 'field_time_max',
      'field_meta_description' => 'field_meta_description',
      'field_meta_keywords' => 'field_meta_keywords',
      'meta_title' => 'field_meta_title',
      'meta_description' => 'field_meta_description',
      'meta_keywords' => 'field_meta_keywords',
      'author' => 'field_contributor', // further processing needed
      'body' => 'field_description',
      'field_description' => 'field_description',
      'field_product_need_description' => 'field_needed_description',
      'field_items_needed' => 'field_items_needed', // @TODO Update when Skyword makes multivalue
      'field_servings' => 'field_servings_max', // @TODO Update when Skyword makes multivalue
      'field_ingredients' => 'field_ingredients', // @TODO Update when Skyword makes multivalue
      // 'field_products_used' => 'field_products', // further processing needed
      // 'field_product_own' => 'field_product_own',
    ];

    return ( in_array($key, array_keys($mappings)) ? $mappings[$key] : false );
  }

  protected static function fileFieldMappings() {
    // source/skyword => target/drupal
    return [
      'field_whatneed_img_2x' => 'field_900x677_img',
      'field_whatneed_img_2x_url' => 'field_900x677_img',
      'field_whatneed_img_2x_name' => 'field_900x677_img',
      'hero_mobile' => 'field_828x828_img',
      'hero_mobile_url' => 'field_828x828_img',
      'hero_mobile_name' => 'field_828x828_img',
      'field_card_tile_img_2x' => 'field_896x896_img',
      'field_card_tile_img_2x_url' => 'field_896x896_img',
      'field_card_tile_img_2x_name' => 'field_896x896_img',
      'hero_half_desktop' => 'field_1824x1371_img',
      'hero_half_desktop_url' => 'field_1824x1371_img',
      'hero_half_desktop_name' => 'field_1824x1371_img',
    ];
  }

  protected function isFileKey($key) {
    return in_array($key, array_keys(self::fileFieldMappings()));
  }

  protected function fileMap(SimpleXMLElement $document, $recipe = []) {
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
        $recipe[$fieldName] = $files[$fieldName];
      }
    }

    return $recipe;
  }
}
