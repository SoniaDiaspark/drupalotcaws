<?php

namespace Drupal\otc_skyword_import;

class MappingService implements MappingServiceInterface {
  private $mappers;

  public function __construct() {
    $this->mappers = [
      'article' => new ArticleMapper,
      'project' => new ProjectMapper,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function get($type) {
    if ( in_array($type, array_keys($this->mappers) ) ) {
      return $this->mappers[$type];
    }

    return false;
  }
}
