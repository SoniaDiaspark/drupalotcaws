<?php

namespace Drupal\otc_legacy_import;

interface MappingServiceInterface {
  /**
   * Get import mapper for a type
   * @param  string $type mapper for a type
   * @return Drupal\otc_legacy_import\FeedMapperInterface
   */
  public function get($type);

}
