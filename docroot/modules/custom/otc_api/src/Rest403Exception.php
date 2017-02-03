<?php

namespace Drupal\otc_api;

use Exception;

class Rest403Exception extends Exception {
  public function __construct() {
    parent::__construct('Forbidden.', 403);
  }
}
