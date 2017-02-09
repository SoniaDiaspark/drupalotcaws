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
    if ( ! isset($article['filesTodo']) ) {
      $article['filesTodo'] = [];
    }

    foreach ($document as $key => $value) {

      if ( ($field = $this->straightMapping($key)) ) {
        $article[$field] = (string) $value;
        continue;
      }

      switch ($key) {
        // @TODO individual files when skyword xml is fixed
        case 'file':
          $article['filesTodo'][] = $this->fileUrlPrefix . $value;
          break;
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
}
