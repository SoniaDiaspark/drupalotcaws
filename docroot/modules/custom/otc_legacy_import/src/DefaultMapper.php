<?php

namespace Drupal\otc_legacy_import;

class DefaultMapper implements WordPressMapperInterface {
  public function map($documents = []) {
    return $documents;
  }
}
