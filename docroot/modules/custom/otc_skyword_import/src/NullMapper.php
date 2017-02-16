<?php

namespace Drupal\otc_skyword_import;

use SimpleXMLElement;

class NullMapper implements FeedMapperInterface {
  public function map(SimpleXMLElement $document, $thing = []) {
    return $thing;
  }
}
