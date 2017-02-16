<?php

namespace Drupal\otc_skyword_import;

interface MappingServiceInterface {
  /**
   * Get import mapper for a type
   * @param  string $type mapper for a type
   * @return Drupal\otc_skyword_import\FeedMapperInterface
   */
  public function get($type);

}
