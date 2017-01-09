<?php

namespace Drupal\otc_api;

/**
 * Interface RestHelperInterface.
 *
 * @package Drupal\otc_api
 */
interface RestHelperInterface {

  /**
   * Validate content type string.
   * @param  string $contentType the content type
   * @return boolean
   */
  public function contentTypeExists($contentType);

  /**
   * Fetch a list of nodes from a content type, in clean format for REST.
   * @param  string  $contentType the content type
   * @param  integer $page        page number
   * @param  boolean $published   true for published, false for all.
   * @return array of nodes.
   */
  public function fetchAll($contentType, $page, $published);
}
