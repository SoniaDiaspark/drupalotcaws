<?php

namespace Drupal\otc_legacy_import;

class ContributorMapper implements WordPressMapperInterface {
  public function map($documents = []) {
    $contributors = [];

    foreach ( $documents as $document ) {
      $contributor = [
        'type' => 'contributor',
      ];

      foreach ( $document as $field => $value ) {
        if ( ( $target = $this->straightMap($field) ) && $value ) {
          $contributor[$target] = $value;
        } elseif ( ($target = $this->urlMap($field) )  && $value ) {
          $url = [
            'uri' => $value,
            'title' => $field,
          ];
          if ( $field === 'user_url' ) {
            $url['title'] = $document['blogname'];
          }
          $contributor[$target] = $url;
        }
      }

      $contributor['title'] = $contributor['field_full_name'];
      $contributors[] = $contributor;
    }

    return $contributors;
  }

  protected function straightMap($field) {
    $fields = [
      'ID' => 'field_wordpress_id',
      'display_name' => 'field_full_name',
      'first_name' => 'field_first_name',
      'last_name' => 'field_last_name',
      'description' => 'field_bio',
      'avatar' => 'field_800x800_img',
    ];

    return isset($fields[$field]) ? $fields[$field] : false;
  }

  protected function urlMap($field) {
    $fields = [
      'user_url' => 'field_website',
      'twitter' => 'field_twitter',
      'pinterest' => 'field_pinterest',
      'instagram' => 'field_instagram',
      'facebook' => 'field_facebook',
    ];

    return isset($fields[$field]) ? $fields[$field] : false;
  }
}
