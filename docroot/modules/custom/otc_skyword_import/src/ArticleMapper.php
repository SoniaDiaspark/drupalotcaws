<?php

namespace Drupal\otc_skyword_import;

use ZendXml\Security as XmlSecurity;
use SimpleXMLElement;

class ArticleMapper implements FeedMapperInterface {
  protected $fileUriPrefix;

  protected function straightMapping($key = '') {
    $mappings = [
      'id' => 'field_skyword_id',
      'title' => 'field_display_title',
      'meta_title' => 'field_meta_title',
      'meta_description' => 'field_meta_description',
      'meta_keywords' => 'field_meta_keyword',
      'author' => 'field_contributor', // further processing needed
      'article_content_heading' => 'field_content_heading',
      'article_content_1' => 'field_content_1',
      'article_content_2' => 'field_content_2',
      'article_content_3' => 'field_content_3',
      'article_quote_content' => 'field_quote_content',
      'article_products_used' => 'field_products', // further processing needed
      'article_items_needed' => 'field_items_needed', // further processing needed, single-value in skyword
      'article_video_url' => 'field_video_embed', // further processing needed, only a URL in skyword?
      'photo_credit_content' => 'field_photo_credit',
      'wyn_description' => 'field_needed_description',
      'article_description_2' => 'field_description',
      'article_carousel_heading' => 'field_carousel_heading',
      'article_carousel_content' => 'field_carousel_content',
    ];

    return ( in_array($key, array_keys($mappings)) ? $mappings[$key] : false );
  }

  public function __construct($fileUrlPrefix) {
    $this->fileUrlPrefix = $fileUrlPrefix;
  }

  public function map(SimpleXMLElement $document, $article = []) {
    $article = $this->fileMap($document, $article);

    foreach ($document as $key => $value) {
      if ( $this->isFileKey($key) ) continue;

      if ( ($field = $this->straightMapping($key)) ) {
        $article[$field] = (string) $value;
        continue;
      }

      switch ($key) {
        case 'articles_content':
        case 'carousel':
          // recurse for simple mappings
          $article = $this->map($value, $article);
          break;

        // ignored skyword fields
        case 'publishedDate':
        case 'keyword':
        case 'assignment_title':
        case 'otc_featured_products':
        case 'action':
        case 'body':
        case 'photo_article_inspiration':
          break;
        default:
          echo "UNMAPPED KEY: $key\n";
      }
    }

    if ( $article['field_display_title'] ) {
      $article['title'] = $article['field_display_title'];
    }

    return $article;
  }

  protected static function fileFieldMappings() {
    return [
      'field_1858x1062_img' => [
        'field_1858x1062_img',
        'field_1858x1062_img_url',
        'field_1858x1062_img_name',
      ],
      'field_3200x1391_img' => [
        'field_3200x1391_img',
        'field_3200x1391_img_url',
        'field_3200x1391_img_name',
      ],
      'field_828x473_img' => [
        'field_828x473_img',
        'field_828x473_img_url',
        'field_828x473_img_name',
      ],
      'field_896x896_img' => [
        'field_896x896_img',
        'field_896x896_img_url',
        'field_896x896_img_name',
      ],
      'field_929x1239_img' => [
        'field_929x1239_img',
        'field_929x1239_img_url',
        'field_929x1239_img_name',
      ],
      'field_1088x818_img' => [
        'field_1088x818_img',
        'field_1088x818_img_url',
        'field_1088x818_img_name',
      ],
      'field_1088x818_img' => [
        'field_1088x818_img',
        'field_1088x818_img_url',
        'field_1088x818_img_name',
      ],
      'field_900x677_img' => [
        'field_900x677_img',
        'field_900x677_img_url',
        'field_900x677_img_name',
      ],
      'field_1858x1062_multi_img' => [
        'image',
        'image_url',
        'image_name',
      ],
      'field_download_file' => [
        'PDF_upload',
        'PDF_upload_url',
        'PDF_upload_name',
      ],
    ];
  }

  protected function isFileKey($key) {
    $files = [];
    foreach (self::fileFieldMappings() as $fieldName => $elements) {
      $files = array_merge($files, $elements);
    }

    return in_array($key, $files);
  }

  protected function fileMap(SimpleXMLElement $document, $article = []) {
    $files = [];
    foreach ( self::fileFieldMappings() as $fieldName => $elements ) {
      $files[$fieldName] = [];
      $items = [
        'url' => [],
        'name' => [],
      ];

      // gather
      foreach ( $elements as $element ) {
        if ( $fieldname === 'field_1858x1062_multi_img' ) {
          $field = $document;
        } else {
          $field = $document->{$element};
        }

        foreach ($field as $key => $value) {
          preg_match('/(url|name)$/', $key, $matches);
          if ($matches[1]) {
            $items[$matches[1]][] = (string) $value;
          }
        }

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
