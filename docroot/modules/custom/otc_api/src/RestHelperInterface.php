<?php

namespace Drupal\otc_api;

/**
 * Interface RestHelperInterface.
 *
 * @package Drupal\otc_api
 */
interface RestHelperInterface {

  /**
   * Fetch a list of nodes from a content type, in clean format for REST.
   * @param  string  $contentType the content type
   * @param  array $options
   * @return array of nodes.
   */
  public function fetchAll($contentType, $options);

  /**
   * Get all terms from a vocabulary.
   * @param string $vocabulary the vocabulary
   * @param array $options
   * @return array of terms.
   */
  public function fetchAllTerms($vocabulary, $options);

  /**
   * Get one node by uuid.
   * @param  string $contentType content type for validation
   * @param  string $uuid        uuid of the content
   * @param array $options
   * @return array processed node, simplified for rest
   */
  public function fetchOne($contentType, $uuid, $options);

  /**
   * Get all terms from a vocabulary.
   * @param string $vocabulary the vocabulary
   * @param  string $uuid        uuid of the content
   * @param array $options
   * @return array processed term, simplified for reset.
   */
  public function fetchOneTerm($vocabulary, $uuid, $options);

  /**
   * Get CacheMetaData for content list or specific result.
   * @param  array $result processed content array
   * @param  string $entity_type (optional) defaults to node
   *   can be node or taxonomy_term
   * @return CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result, $entity_type);
}
