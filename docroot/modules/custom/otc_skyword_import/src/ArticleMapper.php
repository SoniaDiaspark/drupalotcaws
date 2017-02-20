<?php

namespace Drupal\otc_skyword_import;

use SimpleXMLElement;

class ArticleMapper implements FeedMapperInterface {

  public function map(SimpleXMLElement $document, $article = []) {
    $article = $this->fileMap($document, $article);
    $article = $this->multiFieldMap($document, $article);
    $article = $this->linkMap($document, $article);

    foreach ($document as $key => $value) {
      if ( $this->isFileKey($key) ) continue;
      if ( $this->isMultiValue($key) ) continue;
      if ( $this->isLinkKey($key) ) continue;

      if ( ($field = $this->straightMapping($key)) ) {
        $article[$field] = (string) $value;
        continue;
      }

      switch ($key) {
        case 'additional_al_requirements':
        case 'articles_content':
        case 'carousel':
          // recurse for simple mappings
          $article = $this->map($value, $article);
          break;

        // ignored skyword fields
        case 'article_list_step_content':
        case 'publishedDate':
        case 'keyword':
        case 'assignment_title':
        case 'otc_featured_products':
        case 'action':
        case 'photo_article_inspiration':
          break;
        default:
          echo "UNMAPPED KEY: $key\n";
      }
    }

    if ( isset($document->article_list_step_content) ) {
      $index = 0;
      foreach ( $document->article_list_step_content as $key => $step ) {
        $stepData = $this->map($step);
        if ($stepData) {
          $article['field_step'][$index++] = $stepData;
        }
      }

      if ( ! empty($article['field_step']) ) {
        $article['field_article_list'] = true;

        $skus = [];
        foreach ( $article['field_step'] as $step ) {
          if ( $step['field_products'] ) {
            $skus = array_filter(array_unique(array_merge(
              $skus, array_map(function($sku){
                return trim($sku);
              }, explode(',', $step['field_products']))
            )));
          }
        }
        if ( ! empty($skus) ) {
          $article['field_products'] = implode(',', $skus);
        }
      }
    }

    if ( $article['field_display_title'] ) {
      $article['title'] = $article['field_display_title'];
    }
    return $article;
  }

  protected function straightMapping($key = '') {
    $mappings = [
      'id' => 'field_skyword_id',
      'title' => 'field_display_title',
      'field_meta_title' => 'field_meta_title',
      'field_meta_description' => 'field_meta_description',
      'field_meta_keywords' => 'field_meta_keywords',
      'meta_title' => 'field_meta_title',
      'meta_description' => 'field_meta_description',
      'meta_keywords' => 'field_meta_keywords',
      'author' => 'field_contributor', // further processing needed
      'field_content_heading' => 'field_content_heading',
      'field_content_1' => 'field_content_1',
      'field_content_2' => 'field_content_2',
      'field_content_3' => 'field_content_3',
      'field_quote_content' => 'field_quote_content',
      'field_products_used' => 'field_products', // further processing needed
      'field_video_url' => 'field_video_embed',
      'field_photo_credit' => 'field_photo_credit',
      'field_product_need_description' => 'field_needed_description',
      'body' => 'field_description',
      'field_description_2' => 'field_description_2',
      'field_carousel_heading' => 'field_carousel_heading',
      'field_carousel_content' => 'field_carousel_content',
      'field_list_heading' => 'field_display_title',
      'field_list_content' => 'field_description',
    ];

    return ( in_array($key, array_keys($mappings)) ? $mappings[$key] : false );
  }

  protected static function multiValue() {
    // source/skyword => target/drupal
    return [
      'article_items_needed' => 'field_items_needed',
      'field_items_needed' => 'field_items_needed',
    ];
  }

  protected function isMultiValue($fieldName) {
    return in_array($fieldName, array_keys(self::multiValue()));
  }

  protected function multiFieldMap(SimpleXMLElement $document, $article = []) {
    foreach (self::multiValue() as $source => $target) {
      if ( isset($document->{$source}) ) {
        $article[$target] = [];
        foreach ($document->{$source} as $key => $value) {
          $article[$target][] = (string) $value;
        }
      }
    }

    return $article;
  }

  protected static function linkFieldMappings() {

    return [
      'field_cta_link' => [
        'uri' => 'field_list_link_url',
        'title' => 'field_link_text',
      ],
    ];
  }

  protected function isLinkKey($key) {
    foreach ( self::linkFieldMappings() as $fieldName => $skywordFields ) {
      if ( in_array($key, $skywordFields) ) return true;
    }

    return false;
  }

  protected function linkMap(SimpleXMLElement $document, $article = []) {

    foreach ( self::linkFieldMappings() as $fieldName => $skywordFields ) {
      if ( isset($document->{$skywordFields['uri']}) && isset($document->{$skywordFields['title']}) ) {
        $article[$fieldName] = [
          'uri' => (string) $document->{$skywordFields['uri']},
          'title' => (string) $document->{$skywordFields['title']},
        ];
      }
    }

    return $article;
  }

  protected static function fileFieldMappings() {
    // source/skyword => target/drupal
    return [
      'field_hero_bleed_d_img_2x' => 'field_3200x1391_img',
      'field_hero_bleed_d_img_2x_url' => 'field_3200x1391_img',
      'field_hero_bleed_d_img_2x_name' => 'field_3200x1391_img',
      'field_3200x1391_img' => 'field_3200x1391_img',
      'field_3200x1391_img_url' => 'field_3200x1391_img',
      'field_3200x1391_img_name' => 'field_3200x1391_img',
      'field_hero_bleed_m_img_2x' => 'field_828x473_img',
      'field_hero_bleed_m_img_2x_url' => 'field_828x473_img',
      'field_hero_bleed_m_img_2x_name' => 'field_828x473_img',
      'field_828x473_img' => 'field_828x473_img',
      'field_828x473_img_url' => 'field_828x473_img',
      'field_828x473_img_name' => 'field_828x473_img',
      'field_article_thumb_img_2x' => 'field_828x473_img2',
      'field_article_thumb_img_2x_url' => 'field_828x473_img2',
      'field_article_thumb_img_2x_name' => 'field_828x473_img2',
      'field_card_tile_img_2x' => 'field_896x896_img',
      'field_card_tile_img_2x_url' => 'field_896x896_img',
      'field_card_tile_img_2x_name' => 'field_896x896_img',
      'field_896x896_img' => 'field_896x896_img',
      'field_896x896_img_url' => 'field_896x896_img',
      'field_896x896_img_name' => 'field_896x896_img',
      'field_article_img_tall_2x' => 'field_929x1239_img',
      'field_article_img_tall_2x_url' => 'field_929x1239_img',
      'field_article_img_tall_2x_name' => 'field_929x1239_img',
      'field_929x1239_img' => 'field_929x1239_img',
      'field_929x1239_img_url' => 'field_929x1239_img',
      'field_929x1239_img_name' => 'field_929x1239_img',
      'field_quote_img_2x' => 'field_1088x818_img',
      'field_quote_img_2x_url' => 'field_1088x818_img',
      'field_quote_img_2x_name' => 'field_1088x818_img',
      'field_quote_img_2x' => 'field_1088x818_img',
      'field_quote_img_2x_url' => 'field_1088x818_img',
      'field_quote_img_2x_name' => 'field_1088x818_img',
      'field_whatneed_img_2x' => 'field_900x677_img',
      'field_whatneed_img_2x_url' => 'field_900x677_img',
      'field_whatneed_img_2x_name' => 'field_900x677_img',
      'field_article_img_wide_2x' => 'field_1858x1062_img',
      'field_article_img_wide_2x_url' => 'field_1858x1062_img',
      'field_article_img_wide_2x_name' => 'field_1858x1062_img',
      'field_article_carousel_img_2x' => 'field_1858x1062_multi_img',
      'field_article_carousel_img_2x_url' => 'field_1858x1062_multi_img',
      'field_article_carousel_img_2x_name' => 'field_1858x1062_multi_img',
      'field_article_PDF' => 'field_download_file',
      'field_article_PDF_url' => 'field_download_file',
      'field_article_PDF_name' => 'field_download_file',
    ];
  }

  protected function isFileKey($key) {
    return in_array($key, array_keys(self::fileFieldMappings()));
  }

  protected function fileMap(SimpleXMLElement $document, $article = []) {
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
        $article[$fieldName] = $files[$fieldName];
      }
    }

    return $article;
  }
}
