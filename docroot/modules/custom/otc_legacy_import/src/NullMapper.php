<?php

namespace Drupal\otc_legacy_import;

class NullMapper implements WordPressMapperInterface {
  public function map($documents = [], $things = []) {
    return $things;
  }
}
