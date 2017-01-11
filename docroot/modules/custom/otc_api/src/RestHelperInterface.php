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
  public function contentTypePermitted($contentType);

  /**
   * Fetch a list of nodes from a content type, in clean format for REST.
   * @param  string  $contentType the content type
   * @param  integer $page        page number
   * @param  boolean $published   true for published, false for all.
   * @return array of nodes.
   */
  public function fetchAll($contentType, $page, $published);

  /**
   * Get one node by uuid.
   * @param  string $contentType content type for validation
   * @param  string $uuid        uuid of the content
   * @return array processed node, simplified for rest
   */
  public function fetchOne($contentType, $uuid);

  /**
   * Get CacheMetaData for content list or specific result.
   * @param  array $result processed content array
   * @param  string $entity_type (optional) defaults to node
   *   can be node or taxonomy_term
   * @return CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result, $entity_type);
}
