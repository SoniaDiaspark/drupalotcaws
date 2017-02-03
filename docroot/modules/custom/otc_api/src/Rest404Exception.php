<?php

namespace Drupal\otc_api;

use Exception;

class Rest404Exception extends Exception {
  public function __construct() {
    parent::__construct('Not found.', 404);
  }
}
