<?php

namespace Drupal\otc_skyword_import;

interface MappingServiceInterface {
  /**
   * Get import mapper for a type
   * @param  string $type mapper for a type
   * @return mixed Drupal\otc_skyword_import\FeedMapperInterface or FALSE for not found
   */
  public function get($type);

}
