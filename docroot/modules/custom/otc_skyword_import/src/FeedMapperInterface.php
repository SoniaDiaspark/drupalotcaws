<?php

namespace Drupal\otc_skyword_import;
use SimpleXMLElement;

interface FeedMapperInterface {
  /**
   * Map xml feed into
   * @param  SimpleXMLElement $document the document element or sub element
   * @param  array           $data  (optional) array to map feed recursively
   * @return array the xml document values mapped to drupal content type fieldnames
   */
  public function map(SimpleXMLElement $document, $data = []);
}
