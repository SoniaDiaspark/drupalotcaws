<?php

namespace Drupal\otc_legacy_import;

class DefaultMapper implements WordPressMapperInterface {
  public function map($documents = []) {
    $return = [];
    foreach ( $documents as $document ) {
      $mapped = [
        'type' => $document['type'],
      ];

      foreach ( $document as $field => $value ) {
        if ( ( $target = $this->straightMap($field) ) && $value ) {
          $mapped[$target] = $value;
        }
      }

      $mapped['title'] = $mapped['field_meta_keywords'] = $mapped['field_display_title'];

      if ( ! empty($mapped['field_legacy_content']) ) {
        $mapped = $this->extractProducts($mapped);
        $mapped = $this->extractImages($mapped);
        $mapped = $this->replaceSkywordTag($mapped);
      }

      if ( $document['type'] === 'recipe' ) {
        $mapped = $this->mapRecipeFields($mapped, $document);
      }

      if ( $mapped['field_meta_description'] ) {
        $mapped['field_description'] = $mapped['field_meta_description'];
      }

      $return[] = $mapped;
    }

    return $return;
  }

  protected function mapRecipeFields($mapped, $document) {

    if ( $document['directions']  ) {
      $directions = explode("\n", $document['directions']);
      if ( count($directions) > 1 ) {
        $steps = [];
        foreach ($directions as $index => $direction) {
          $step = [
            'type' => 'step',
            'title' => 'Step ' . ($index + 1),
            'field_description' => $direction,
          ];
          $steps[] = $step;
        }

        $mapped['field_step'] = $steps;
      }
    }

    if ( $document['ingredients'] ) {
      $mapped['field_ingredients'] = explode("\n", $document['ingredients']);
    }

    if ( $document['recipe_yield'] ) {
      $matches = [];

      if ( preg_match('/(\d+)\s*-\s*(\d+)/', $document['recipe_yield'], $matches) ) {
        $mapped['field_servings_min'] = (int) $matches[1];
        $mapped['field_servings_max'] = (int) $matches[2];
      } elseif ( preg_match('/(\d+)/', $document['recipe_yield'], $matches) ) {
        $mapped['field_servings_min'] = max(1, ((int) $matches[1]) - 2);
        $mapped['field_servings_max'] = (int) $matches[1];
      }
    }

    if ( $document['time_to_complete__project'] ) {
      $matches = [];
      $min = 1;
      $max = 1;

      if ( preg_match('/(\d+)\s*-\s*(\d+)/', $document['time_to_complete__project'], $matches) ) {
        $min = max($min, (int) $matches[1]);
        $max = max($max, (int) $matches[2]);
      } elseif ( preg_match('/(\d+)/', $document['time_to_complete__project'], $matches) ) {
        $min = max($min, (int) $matches[1]);
        $max = max($max, (int) $matches[1]);
      }

      if ( preg_match('/(\d+)\s*-\s*(\d+)/', $document['prep_time'], $matches) ) {
        $min = max($min, (int) $matches[1] + $min);
        $max = max($max, (int) $matches[2] + $max);
      } elseif ( preg_match('/(\d+)/', $document['prep_time'], $matches) ) {
        $min = max($min, (int) $matches[1] + $min);
        $max = max($max, (int) $matches[1] + $min + 10);
      }
      $mapped['field_time_min'] = $min;
      $mapped['field_time_max'] = $max;
    }

    return $mapped;
  }

  protected function straightMap($field) {
    $fields = [
      'ID' => 'field_wordpress_id',
      'post_title' => 'field_display_title',
      'post_content' => 'field_legacy_content',
      '_yoast_wpseo_metadesc' => 'field_meta_description',
      '_yoast_wpseo_title' => 'field_meta_title',
      'skyword_content_id' => 'field_skyword_id',
      'skyword_tracking_tag' => 'skyword_tracking_tag',
      'post_author' => 'field_contributor',
      'recipe_yield' => 'field_needed_description',
    ];

    return isset($fields[$field]) ? $fields[$field] : false;
  }

  protected function replaceSkywordTag($mapped) {
    if ( $mapped['skyword_tracking_tag'] ) {
      $mapped['field_legacy_content'] = str_replace('[cf]skyword_tracking_tag[/cf]', $mapped['skyword_tracking_tag'], $mapped['field_legacy_content']);
      unset($mapped['skyword_tracking_tag']);
    }

    return $mapped;
  }

  protected function extractProducts($mapped) {
    $matches = [];
    $products = [];
    preg_match_all('/http.*?a2-([0-9_]+?).fltr/', $mapped['field_legacy_content'], $matches);
    if ( count($matches) === 2 ) {
      foreach ( $matches[1] as $sku ) {
        $sku = str_replace('_', '/', $sku);
        $products[] = $sku;
      }
    }

    if ( $products ) {
      $mapped['field_products'] = array_unique($products);
    }

    return $mapped;
  }

  protected function extractImages($mapped) {
    $matches = [];
    $images = [];
    preg_match_all('/img.*?src="(http.*?wp-content.*?([0-9]{4}).([0-9]{2}).*?)"/', $mapped['field_legacy_content'], $matches);
    if ( ! empty($matches[1]) ) {
      foreach ( $matches[1] as $index => $image ) {
        $image = [
          'sourceUrl' => $image,
          'destinationUri' => "public://inline-images/legacy/{$matches[2][$index]}/{$matches[3][$index]}/" . basename($image),
        ];
        $images[] = $image;
      }
    }

    if ( $images ) {
      $mapped['images'] = $images;
      $mapped['field_896x896_img'] = $images[0]['sourceUrl'];
      $mapped['field_828x828_img'] = $images[0]['sourceUrl'];
      if ( $mapped['type'] === 'article' ) {
        $mapped['field_828x473_img2'] = $images[0]['sourceUrl'];
      }
    }

    return $mapped;
  }

}
