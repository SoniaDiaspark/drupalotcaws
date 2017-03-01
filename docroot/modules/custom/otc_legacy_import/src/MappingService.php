<?php

namespace Drupal\otc_legacy_import;

class MappingService implements MappingServiceInterface {
  private $mappers;

  public function __construct() {
    $this->mappers = [
      'contributor' => new ContributorMapper,
      'article' => new DefaultMapper,
      'project' => new DefaultMapper,
      'recipe' => new DefaultMapper,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function get($type) {
    if ( in_array($type, array_keys($this->mappers) ) ) {
      return $this->mappers[$type];
    }

    return new NullMapper;
  }
}
