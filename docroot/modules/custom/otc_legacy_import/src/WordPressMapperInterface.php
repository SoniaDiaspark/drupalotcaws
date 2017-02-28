<?php

namespace Drupal\otc_legacy_import;

interface WordPressMapperInterface {
  /**
   * Map xml feed into
   * @param  array $document the document content
   *
   * @return array the resulting mapped document for further processing
   */
  public function map($documents = []);
}
