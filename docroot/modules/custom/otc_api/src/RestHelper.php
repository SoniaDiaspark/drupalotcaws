<?php

namespace Drupal\otc_api;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Cache\CacheableMetadata;

class RestHelper implements RestHelperInterface {
  /**
   * For creating entity queries.
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * To query entities by uuid
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param QueryFactory $queryFactory entity query factory
   */
  public function __construct() {
    $this->queryFactory = \Drupal::service('entity.query');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * Get CacheMetaData for content list or specific result.
   * @param  mixed $result processed content array
   * @param  string $entity_type (optional) defaults to node
   *   can be node or taxonomy_term
   * @return CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result, $entity_type = 'node') {
    $cacheMetaData = new CacheableMetadata;
    $cacheMetaData->setCacheContexts(['url']);

    if ( empty($result) || ! is_array($result) ) {
      $result = [];
    }

    if ( $entity_type === 'node' ) {
      return $this->cacheNodeMetaData($cacheMetaData, $result);
    } else if ( $entity_type === 'taxonomy_term' ) {
      return $this->cacheTermMetaData($cacheMetaData, $result);
    }
  }

  /**
   * Get CacheMetaData for term list or specific term result.
   * @return CacheableMetadata cache metadata object
   */
  protected function cacheTermMetaData(CacheableMetadata $cacheMetaData, $result = []) {
    if ( ! empty($result['tid']) ) {
      $cacheMetaData->setCacheTags(['taxonomy_term:' . $result['tid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['taxonomy_term']);

    return $cacheMetaData;
  }

  /**
   * Get CacheMetaData for node list or specific result.
   * @return CacheableMetadata cache metadata object
   */
  protected function cacheNodeMetaData(CacheableMetadata $cacheMetaData, $result = []) {
    if ( ! empty($result['nid']) ) {
      $cacheMetaData->setCacheTags(['node:' . $result['nid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['node_list']);

    return $cacheMetaData;
  }

  /**
   * validate a content type exists
   * @param  [type]  $contentType [description]
   * @return boolean              [description]
   */
  public static function isContentType($contentType = NULL) {
    return in_array($contentType, array_keys(NodeType::loadMultiple()));
  }

  /**
   * Validate content type string.
   * @param  string $contentType the content type
   * @return boolean
   */
  public static function contentTypePermitted($contentType = NULL) {
    $allowedContentTypes = [
      'landing',
      'article',
      'contributor',
      'download',
      'featured_content',
      'look',
      'product',
      'project',
      'recipe',
      'step',
    ];

    return in_array($contentType, $allowedContentTypes);
  }

  /**
  * Check to see if a given vocabulary is permitted in the api call.
  * @param  string $vocabulary the vocabulary name/id
  * @return boolean
  */
  protected static function vocabularyPermitted($vocabulary) {
    return in_array($vocabulary, [
      'category',
      'tag',
      'contributor_group',
    ]);
  }


  public function fetchAllIdeas($options = []) {
    $defaults = [
      'page' => 0,
      'published' => true,
      'limit' => 10, // result limit
      'recurse' => true, // toggle off recursion
      'maxDepth' => 2, // deepest level of recursion
      'currentDepth' => 0, // current depth of recursion
      'multiValueGroups' => [],
    ];
    $options = array_merge($defaults, $options);

    $category_uuids = [];
    if ($options['category'] && is_array($options['category'])) {
      $category_uuids = $this->lookupTermUuids($options['category']);
      if ( $category_uuids ) {
        $options['multiValueGroups']['field_category.entity.uuid'] = $category_uuids;
      }
    }

    $tag_uuids = [];
    if ($options['tag'] && is_array($options['tag'])) {
      $tag_uuids = $this->lookupTermUuids($options['tag']);
      if ( $tag_uuids ) {
        $options['multiValueGroups']['field_tag.entity.uuid'] = $tag_uuids;
      }
    }

    $ideaTypes = array('look', 'project', 'article', 'recipe', 'download');
    $options['multiValueGroups']['type'] = $ideaTypes;
    if ( $options['type'] && is_array($options['type']) ) {
      $types = array_intersect($options['type'], $ideaTypes);
      if ($types) {
        $options['multiValueGroups']['type'] = $types;
      }
    }

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published']
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
    ->range($options['page'] * $limit, $limit)
    ->execute();

    if ( ! $entity_ids ) {
      $response['results'] = [];
      return $response;
    }

    $response['results'] = $this->processNodes(
      \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($entity_ids),
      $options
    );

    return $response;
  }

  /**
  * Fetch a list of nodes from a content type, in clean format for REST.
  * @param  string $contentType the content type
  * @param array $options
  * - integer $page page number (default 0)
  * - boolean $published true for published, false for all. (default true)
  * - boolean $recurse references are recursively dereferenced
  * - integer $maxDepth levels of recursion
  * @return array of nodes.
  */
  public function fetchAll($contentType, $options = []) {
    if ( ! self::isContentType($contentType) ) {
      throw new Rest404Exception;
    }

    if ( ! self::contentTypePermitted($contentType) ) {
      throw new Rest403Exception;
    }

    $defaults = [
      'page' => 0,
      'published' => true,
      'limit' => 10, // result limit
      'recurse' => true, // toggle off recursion
      'maxDepth' => 2, // deepest level of recursion
      'currentDepth' => 0, // current depth of recursion
      'conditions' => [
        'type' => $contentType,
      ],
    ];
    $options = array_merge($defaults, $options);

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published']
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
    ->range($options['page'] * $limit, $limit)
    ->execute();

    if ( ! $entity_ids ) {
      $response['results'] = [];
      return $response;
    }

    $response['results'] = $this->processNodes(
      \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($entity_ids),
      $options
    );

    return $response;
  }

  /**
   * Get all terms from a vocabulary.
   * @param  string $vocabulary the vocabulary
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * @return array of terms.
   */
  public function fetchAllTerms($vocabulary, $options = []) {
    if ( ! in_array($vocabulary, taxonomy_vocabulary_get_names())) {
      throw new Rest404Exception;
    }

    if ( ! self::vocabularyPermitted($vocabulary) ) {
      throw new Rest403Exception;
    }

    $defaults = [
      'page' => 0,
      'limit' => 10, // result limit per page
      'recurse' => true, // toggle off recursion
      'maxDepth' => 2, // deepest level of recursion
      'currentDepth' => 0, // current depth of recursion
    ];
    $options = array_merge($defaults, $options);

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
    ];

    $response['count'] = intval($this->newTermQuery($vocabulary)->count()->execute());

    $entity_ids = $this->newTermQuery($vocabulary, $options)
      ->range($options['page'] * $limit, $limit)
      ->execute();

    if ( ! $entity_ids ) {
      $response['results'] = [];
      return $response;
    }

    $response['results'] = $this->processTerms(
      \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadMultiple($entity_ids),
      $options
    );

    return $response;

  }

  /**
   * Get one node by uuid/alias.
   * @param  string $contentType content type for validation
   * @param  string $id uuid/alias of the content
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * @return array processed node, simplified for rest
   */
  public function fetchOne($contentType, $id = '', $options = []) {
    if ( ! self::contentTypePermitted($contentType) ) {
      throw new Rest403Exception;
    }

    $defaults = [
      'recurse' => true, // toggle off recursion
      'maxDepth' => 2, // deepest level of recursion
      'currentDepth' => 0, // current depth of recursion
    ];
    $options = array_merge($defaults, $options);

    if ( self::isUuid($id) ) {
      $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $id]);
      if ( ! $result ) throw new Rest404Exception;

      $node = current($result);
    } else {
      $node = $this->lookupNodeByAlias($id);
    }

    if ( ! $node || ! self::contentTypePermitted($node->getType()) || $node->getType() !== $contentType ) throw new Rest404Exception;

    return $this->processNode($node, $options);
  }

  /**
   * Get one term by uuid.
   * @param  string $vocabular type for validation
   * @param  string $id uuid of the term or path alias
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   *
   * @return array processed term, simplified for rest
   */
  public function fetchOneTerm($vocabulary, $id = '', $options = []) {
    if ( ! self::vocabularyPermitted($vocabulary) ) {
      throw new Rest403Exception;
    }

    $defaults = [
      'recurse' => true, // toggle off recursion
      'maxDepth' => 2, // deepest level of recursion
      'currentDepth' => 0, // current depth of recursion
    ];
    $options = array_merge($defaults, $options);

    if ( self::isUuid($id) ) {
      $result = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['uuid' => $id]);
      if ( ! $result ) {
        throw new Rest404Exception;
      }
      $term = current($result);
    } else {
      $term = $this->lookupTermByAlias($id);
    }

    if (! $term) {
      throw new Rest404Exception;
    }

    if ( ! self::vocabularyPermitted($term->getVocabularyId()) ) {
      throw new Rest403Exception;
    }

    return $this->processTerm($term, $options);
  }

  /**
   * Fetch all paginated content associated with a particular reference.
   * @param  string $uuid the uuid of the referenced id
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * - integer $page the current page
   *
   * @param  string $field_name the field name referencing a content
   * @return object page of content results for a given reference
   */
  protected function fetchReferencedContent($uuid = '', $options = [], $field_name = 'field_category') {
    $defaults = [
      'page' => 0,
      'limit' => 10, // result limit per page
      'published' => true,
      'conditions' => [
        $field_name . '.entity.uuid' => $uuid,
      ]
    ];
    $options = array_merge($defaults, $options);

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published']
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
    ->range($options['page'] * $limit, $limit)
    ->execute();

    if ( ! $entity_ids ) {
      $response['results'] = [];
      return $response;
    }

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($entity_ids);
    foreach ($nodes as $node) {
      if ($options['recurse']) {
        $response['results'][] = $this->processNode($node);
      } else {
        $response['results'][] = $this->shallowEntity($node);
      }
    }

    return $response;
  }

  /**
   * Fetch all paginated content associated with a particular contributor group.
   * @param  string $id the uuid or path alias of the contributor group
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * - integer $page the current page
   *
   * @return object page of content results for a given contributor group
   */
  public function fetchContributorGroupContent($id = '', $options = []) {
    $uuid = $id;

    if ( ! self::isUuid($id) ) {
      $term = $this->lookupTermByAlias($id);
      if ( ! $term ) throw new Rest404Exception;

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_contributor_category');
  }

  /**
   * Fetch all paginated content associated with a particular category.
   * @param  string $id the uuid or path alias of the category
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * - integer $page the current page
   *
   * @return object page of content results for a given category
   */
  public function fetchCategoryContent($id = '', $options = []) {
    $uuid = $id;

    if ( ! self::isUuid($id) ) {
      $term = $this->lookupTermByAlias($id);
      if ( ! $term ) {
        throw new Rest404Exception;
      }

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_category');
  }

  /**
   * Fetch all paginated content associated with a particular contributor.
   * @param  string $id the uuid or path alias of the contributor
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * - integer $page the current page
   *
   * @return object page of content results for a given contributor
   */
  public function fetchContributorContent($id = '', $options = []) {
    if ( self::isUuid($id) ) {
      $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $id]);
      if ( ! $result ) {
        throw new Rest404Exception;
      }
      $node = current($result);
    } else {
      $node = $this->lookupNodeByAlias($id);
    }

    if ( ! $node ) {
      throw new Rest404Exception;
    }

    $defaults = [
      'multiValueGroups' => [
        'type' => [
          'article',
          'look',
          'project',
          'recipe',
          'download',
        ]
      ]
    ];
    $options = array_merge($defaults, $options);

    $uuid = $node->uuid->value;
    return $this->fetchReferencedContent($uuid, $options, 'field_contributor');
  }

  /**
   * Fetch all paginated content associated with a particular tag.
   * @param  string $id the uuid or path alias of the tag
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * - integer $page the current page
   *
   * @return object page of content results for a given tag
   */
  public function fetchTagContent($id = '', $options = []) {
    $uuid = $id;

    if ( ! self::isUuid($id) ) {
      $term = $this->lookupTermByAlias($id);
      if ( ! $term ) throw new Rest404Exception;

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_tag');
  }

  /**
   * Lookup term uuids from list of aliases or uuids.
   * @param  mixed $ids uuids or path aliases
   * @return [type]      [description]
   */
  protected function lookupTermUuids($ids = []) {
    $uuids = [];
    foreach ($ids as $id) {
      if ( self::isUuid($id) ) {
        $uuids[] = $id;
      } else {
        $term = $this->lookupTermByAlias($id);
        if ( ! $term ) continue;
        $uuids[] = $term->uuid->value;
      }
    }

    return $uuids;
  }

  /**
   * Lookup a term by path alias.
   * @param  string $alias the path alias
   * @return Term or false on failure
   */
  protected function lookupTermByAlias($alias = '') {
    if ( ! $alias ) {
      return FALSE;
    }

    $source = $this->lookupPathSource($alias);
    preg_match('/taxonomy\/term\/(\d+)/', $source, $matches);

    if ( ! isset($matches[1]) ) {
      return FALSE;
    }
    $tid = $matches[1];
    return Term::load($tid);
  }

  /**
   * Lookup a node by path alias.
   * @param  string $alias the path alias
   * @return Node or false on failure
   */
  protected function lookupNodeByAlias($alias = '') {
    if ( ! $alias ) {
      return FALSE;
    }

    $source = $this->lookupPathSource($alias);
    preg_match('/node\/(\d+)/', $source, $matches);
    if ( ! isset($matches[1]) ) {
      return FALSE;
    }
    $nid = $matches[1];
    return Node::load($nid);
  }

  /**
   * Lookup source path from path alias.
   * @param  string $alias the content alias
   * @return string the source path or FALSE
   */
  protected function lookupPathSource($alias = '') {
    if ( ! $alias ) {
      return FALSE;
    }

    return \Drupal::service('path.alias_storage')->lookupPathSource('/' . $alias, 'en');
  }

  /**
   * Get new entity query for a content type.
   * @param  array $options
   * - string $type (optional) content type to query on
   * - boolean $published  true for published only, false for everything
   * - array $conditions entity query conditions
   * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
   *  preset for the content type.
   */
  protected function newNodeQuery($options = []) {
    $query = \Drupal::entityQuery('node');
    if (! $options['published'] ) {
      $options['multiValueGroups']['status'] = [1, 0];
    } else {
      $query->condition('status', 1);
    }

    if ( ! empty($options['orConditionGroups']) ) {
      foreach ($options['orConditionGroups'] as $conditions) {
        if ( ! empty($conditions) ) {
          $group = $query->orConditionGroup();
          foreach ( $conditions as $key => $value ) {
            $group->condition($key, $value);
          }
          $query->condition($group);
        }
      }
    }

    if ( ! empty($options['multiValueGroups']) ) {
      foreach ($options['multiValueGroups'] as $key => $values) {
        if ( ! empty($values) ) {
          $group = $query->orConditionGroup();
          foreach ( $values as $value ) {
            $group->condition($key, $value);
          }
          $query->condition($group);
        }
      }
    }

    if ( ! empty($options['conditions']) ) {
      foreach ($options['conditions'] as $key => $value ) {
        $query->condition($key, $value);
      }
    }

    return $query;
  }

  /**
  * Get an entity query for taxonomy lookup.
  * @param  string $vocabulary the vocabulary
  * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
  *  preset for the content type.
  */
  protected function newTermQuery($vocabulary) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);

    return $query;
  }

  /**
  * Process list of nodes.
  * @param  array $nodes array of Node objects
  * @param array $options
  * - boolean $recurse references are recursively dereferenced
  * - integer $maxDepth levels of recursion
  * @return array of arrays representing a node in clean REST format
  */
  protected function processNodes ($nodes = [], $options = []) {
    $results = [];
    foreach ( $nodes as $node ) {
      $results[] = $this->processNode($node, $options);
    }

    return $results;
  }

  /**
  * Process all fields in a node.
  * @param  Node   $node the node object.
  * @param array $options
  * - boolean $recurse references are recursively dereferenced
  * - integer $maxDepth levels of recursion
  * @return array node information in clean format for REST
  */
  protected function processNode (Node $node, $options = []) {
    $view = [];
    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());
    $storageDefinitions = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');

    foreach ( $fieldDefinitions as $name => $fieldDefinition ) {
      $options['fieldDefinition'] = $fieldDefinition;
      $options['storageDefinition'] = $storageDefinitions[$name];
      $options['multiValue'] = method_exists($options['storageDefinition'], 'isMultiple') && ($options['storageDefinition']->isMultiple() || $options['storageDefinition']->getCardinality() > 1);

      if ( ! $fieldDefinition->getType() ) continue;

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $ignored = in_array($name, self::ignoredFieldNames());

      if ( $supported && ! $ignored ) {
        // no value
        if ( ! $node->$name ) {
          $view[$name] = NULL;
          continue;
        }

        $view[$name] = $this->processField($node->{$name}, $options);
      }
    }

    return $view;
  }

  /**
  * Process list of taxonomy terms.
  * @param  array $terms array of Term objects
  * @param array $options
  * - boolean $recurse references are recursively dereferenced
  * - integer $maxDepth levels of recursion
  * @return array of arrays representing a node in clean REST format
  */
  protected function processTerms ($terms, $options = []) {
    $results = [];
    foreach ( $terms as $term ) {
      $results[] = $this->processTerm($term, $options);
    }

    return $results;
  }

  /**
   * Process all fields in a term.
   * @param  Term   $term the $term object.
   * @return array term information in clean format for REST
   */
  protected function processTerm (Term $term, $options = []) {
    $parents = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadParents($term->tid->value);
    $parent = '';
    if ( $parents ) {
      $parent = Term::load(current($parents)->tid->value)->uuid->value;
    }

    $view = [
      'parent' => $parent,
      'type' => $term->getVocabularyId(),
    ];

    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('taxonomy_term', $term->getVocabularyId());
    $storageDefinitions = \Drupal::service('entity.manager')->getFieldStorageDefinitions('taxonomy_term');

    foreach ( $fieldDefinitions as $name => $fieldDefinition ) {
      $options['fieldDefinition'] = $fieldDefinition;
      $options['storageDefinition'] = $storageDefinitions[$name];
      $options['multiValue'] = method_exists($options['storageDefinition'], 'isMultiple') && ($options['storageDefinition']->isMultiple() || $options['storageDefinition']->getCardinality() > 1);

      if ( ! $fieldDefinition->getType() ) continue;

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $ignored = in_array($name, self::ignoredFieldNames());

      if ( $supported && ! $ignored ) {
        // no value
        if ( ! $term->$name ) {
          $view[$name] = '';
          continue;
        }

        $view[$name] = $this->processField($term->{$name}, $options);
      }
    }

    return $view;
  }

  /**
   * General case: process a field value. Will automatically choose correct
   *  "formatter" method.
   * @see self::supportedFieldTypes()
   *
   * @param  FieldItemListInterface   $field the field item list
   * @param  array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   * @return mixed "formatted" value of the field
   */
  protected function processField(FieldItemListInterface $field, $options = []) {
    $method = self::supportedFieldTypes()[$options['fieldDefinition']->getType()];
    return $this->{$method}($field, $options);
  }

  /**
   * Get simple value.
   * @param  FieldItemListInterface   $field field item list
   * @return string simple string value
   */
  protected function getFieldValue(FieldItemListInterface $field, $options = []) {
    if ( $options['multiValue'] ) {
      $return = [];
      foreach ( $field->getValue() as $item ) {
        $return[] = $item['value'];
      }
      return $return;
    }

    return $field->value;
  }

  /**
   * Get path alias field value.
   * @param  FieldItemListInterface   $field field item list
   * @param array $options
   * - boolean $recurse references are recursively dereferenced
   * - integer $maxDepth levels of recursion
   * @return string path alias
   */
  protected function getPathFieldValue(FieldItemListInterface $field, $options = []) {
    $entity = $field->getEntity();
    $source = $entity->toUrl()->getInternalPath();
    $lang = $entity->language()->getId();
    $path = \Drupal::service('path.alias_storage')->lookupPathAlias('/' . $source, $lang);
    return preg_replace('/^\//', '', $path);
  }

  /**
   * Get simple integer value.
   * @param  FieldItemListInterface   $field field item list
   * @return int
   */
  protected function getIntFieldValue(FieldItemListInterface $field, $options = []) {
    if ( $options['multiValue'] ) {
      $return = [];
      foreach ( $field->getValue() as $item ) {
        $return[] = intval($item['value']);
      }
      return $return;
    }

    return intval($field->value);
  }

  /**
   * Get simple float value.
   * @param  FieldItemListInterface   $field field item list
   * @return float
   */
  protected function getFloatFieldValue(FieldItemListInterface $field, $options = []) {
    if ( $options['multiValue'] ) {
      $return = [];
      foreach ( $field->getValue() as $item ) {
        $return[] = floatval($item['value']);
      }
      return $return;
    }

    return floatval($field->value);
  }

  /**
   * Get link value.
   * @param FieldItemListInterface   $field field item list
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - includes FieldStorageDefinitionInterface $fieldStorage field storage information
   *     to get field cardinality.
   * @return string simple string value
   */
  protected function getLinkFieldValue(FieldItemListInterface $field, $options = []) {
    $values = $field->getValue();
    $return = ($options['multiValue'] ? [] : NULL);

    if ( $values ) {
      if ( $options['multiValue'] ) {
        foreach ( $values as $linkData ) {
          $return[] = [
            'url' => $linkData['uri'],
            'title' => $linkData['title'],
          ];
        }
        return $return;
      } else {
        $linkData = current($values);
        return [
          'url' => $linkData['uri'],
          'title' => $linkData['title'],
        ];
      }
    }

    return $return;
  }

  /**
   * Get simple date value.
   * @param  FieldItemListInterface   $field field item list
   * @return string simple string value
   */
  protected function getDateFieldValue(FieldItemListInterface $field, $options = []) {
    if ( $options['multiValue'] ) {
      $return = [];
      foreach ( $field->getValue() as $item ) {
        $return[] = \Drupal::service('date.formatter')->format($item['value'], 'html_datetime');
      }
      return $return;
    }

    return \Drupal::service('date.formatter')->format($field->value, 'html_datetime');
  }

  /**
   * Get true/false value from boolean
   * @param  FieldItemListInterface   $field           the field item list
   * @return boolean
   */
  protected function getFieldBoolean(FieldItemListInterface $field, $options = []) {
    // If for some reason a multi-value boolean field is selected, which is
    // non-sense.
    if ( $options['multiValue'] ) {
      $items = $field->getValue();
      if ( $items ) {
        return current($items)['value'] === "1";
      }
      return false;
    }

    return $field->value === "1";
  }

  /**
   * Get one or more entity reference object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   *   - int referenceDepth to prevent infinite recursion
   * @return array of arrays representing referenced node
   */
  protected function getReferencedFieldValue(FieldItemListInterface $field, $options = []) {
    $referenceType = $options['fieldDefinition']->getSettings()['target_type'];

    switch ( $referenceType ) {
      case 'node':
        return $this->getReferencedNode($field, $options);
        break;
      case 'node_type':
        return $this->getNodeType($field);
        break;
      case 'taxonomy_term':
        return $this->getReferencedTerm($field, $options);
        break;
      default:
        return NULL;
    }
  }

  /**
  * Get one or more entity reference object arrays.
  * @param  FieldItemListInterface   $field the field items
  * @param  array options
  *   - FieldDefinitionInterface $fieldDefinition field instance info
  *     used to get field instance information.
  *   - FieldStorageDefinitionInterface $storageDefinition field storage information
  *     to get field cardinality.
  *   - int referenceDepth to prevent infinite recursion
  * @return array of arrays representing referenced node
  */
  protected function getReferencedNode(FieldItemListInterface $field, $options = []) { $referenceData = $field->getValue();

    // Reference Field
    $recurse = $options['currentDepth'] < $options['maxDepth'] && $options['recurse'];
    $options['currentDepth'] = ($recurse ? $options['currentDepth'] + 1 : $options['currentDepth']);

    $return = ($options['multiValue'] ? [] : NULL);
    if ( $referenceData ) {
      if ( $options['multiValue'] ) {
        foreach ( $referenceData as $index => $target ) {
          $node = Node::load($target['target_id']);
          $return[] = ($recurse ? $this->processNode($node, $options) : $this->shallowEntity($node));
        }
        return $return;
      } else {
        $node = Node::load(current($referenceData)['target_id']);
        return ($recurse ? $this->processNode($node, $options) : $this->shallowEntity($node));
      }
    }

    return $return;
  }

  /**
  * Dereference a term reference field.
  * @param  FieldItemListInterface $field term reference field list
  * @param  array $options options array
  * @return mixes term object or array of terms
  */
  protected function getReferencedTerm(FieldItemListInterface $field, $options = []) {
    $referenceData = $field->getValue();

    $recurse = $options['currentDepth'] < $options['maxDepth'] && $options['recurse'];
    $options['currentDepth'] = ($recurse ? $options['currentDepth'] + 1 : $options['currentDepth']);

    $return = ($options['multiValue'] ? [] : NULL);
    if ( $referenceData ) {
      if ( $options['multiValue'] ) {
        foreach ( $referenceData as $index => $target ) {
          $term = Term::load($target['target_id']);
          $return[] = ($recurse ? $this->processTerm($term, $options) : $this->shallowEntity($term));
        }
        return $return;
      } else {
        $term = Term::load(current($referenceData)['target_id']);
        return ($recurse ? $this->processTerm($term, $options) : $this->shallowEntity($term));
      }
    }

    return $return;
  }

  /**
  * Get simple object with type and uuid for referenced entity.
  * @param  mixed $entity node or taxonomy_term
  * @return array representing simple type and uuid object.
  */
  protected function shallowEntity($entity) {
    $type = '';
    if ( ! empty($entity->type) ) {
      $type = $this->getNodeType($entity->type);
    } else if ( ! empty($entity->vid) ) {
      $type = current($entity->vid->getValue())['target_id'];
    }

    return [
      'uuid' => $entity->uuid->value,
      'type' => $type,
    ];
  }

  /**
  * Get one or more file object arrays.
  * @param  FieldItemListInterface   $field the field items
  * @param  array options
  *   - FieldDefinitionInterface $fieldDefinition field instance info
  *     used to get field instance information.
  *   - FieldStorageDefinitionInterface $storageDefinition field storage information
  *     to get field cardinality.
  * @return array of arrays of file urls.
  */
  protected function getFileFieldValue(FieldItemListInterface $field, $options = []) {
    $fileData = $field->getValue();

    $return = ($options['multiValue'] ? [] : NULL);
    if ( $fileData ) {
      if ( $options['multiValue'] ) {
        foreach ( $fileData as $target ) {
          $return[] = File::load($target['target_id'])->url();
        }
        return $return;
      }

      // single
      return File::load(current($fileData)['target_id'])->url();
    }

    return $return;
  }

  /**
   * Get one or more entity reference object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @return string node type
   */
  protected function getNodeType(FieldItemListInterface $field) {
    $value = $field->getValue();
    if ( $value ) {
      return current($value)['target_id'];
    }

    return '';
  }

  /**
   * Get one or more image object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get image resolution constraints.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   * @return array of arrays of image urls.
   */
  protected function getImageFieldValue(FieldItemListInterface $field, $options = []) {
    $imageData = $field->getValue();
    $resolution = $options['fieldDefinition']->getSettings()['max_resolution'];
    $resolutions = $this->imageStyles($resolution);

    $return = ($options['multiValue'] ? [] : NULL);
    if ( $imageData ) {
      if ( $options['multiValue'] ) {
        foreach ( $imageData as $image ) {
          $return[] = $this->processImage($image['target_id'], $resolutions);
        }
        return $return;
      }

      // single
      return $this->processImage(current($imageData)['target_id'], $resolutions);
    }

    return $return;
  }

  /**
   * Process an image field.
   * @param  int $target_id file entity id
   * @param  array $resolutions image style names that might apply to this image.
   * @return array of image urls
   */
  protected function processImage($target_id, $resolutions) {
    $streamWrapper = \Drupal::service('stream_wrapper_manager');
    $baseFile = \Drupal::service('entity.manager')
      ->getStorage('file')
      ->load($target_id);

    $internalUri = $baseFile->getFileUri();

    $result = [
      'full' => $streamWrapper->getViaUri($internalUri)->getExternalUrl()
    ];

    foreach ( $resolutions as $resolution ) {
      $styleName = $resolution;
      $style = ImageStyle::load($resolution);
      if ($style) {
        $result[$styleName] =  $style ->buildUrl($internalUri);
      }
    }

    return $result;
  }

  /**
   * Based on string max resolution from image field configuration, get the
   * list of image styles that share the same aspect ratio.
   * @param  string $resolution [width]x[height] string
   * @return array list of image styles
   */
  protected function imageStyles($resolution) {
    $resolutions = self::resolutions();

    preg_match('/(\d+)x(\d+)/', $resolution, $matches);

    if ( ! $matches || ! $matches[2] ) {
      return [];
    }

    $aspectRatio = number_format(round($matches[1] / $matches[2], 2), 2);
    if ( ! in_array($aspectRatio, array_keys($resolutions)) ) {
      return [];
    }

    return $resolutions[$aspectRatio];
  }

  /**
   * Is the argument a uuid?
   * @param  string  $uuid string to test
   * @return boolean
   */
  protected static function isUuid($uuid = '') {
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid) === 1;
  }

  /**
   * Get image styles for each aspect ratio.
   * @return array list of resolutions/image styles per aspect ratio
   */
  protected static function resolutions() { return [
        '0.75' => [
          '465x620_img',
        ],

        '1.00' => [
          '400x400_img',
          '414x414_img',
          '448x448_img',
        ],

        '1.33' => [
          '414x312_img',
          '544x409_img',
          '640x481_img',
          '828x623_img',
          '912x686_img',
        ],

        '1.75' => [
          '414x237_img',
          '533x305_img',
          '828x473_img',
          '929x531_img',
        ],

        '2.30' => [
          '533x232_img',
          '1600x696_img',
        ],
      ];
  }

  /**
   * Methods for processing different field types.
   * @return array methods for handling differnent field types.
   */
  protected static function supportedFieldTypes() {
    return [
      'string' => 'getFieldValue',
      'string_long' => 'getFieldValue',
      'text' => 'getFieldValue',
      'text_long' => 'getFieldValue',
      'created' => 'getDateFieldValue',
      'changed' => 'getDateFieldValue',
      'path' => 'getPathFieldValue',
      'float' => 'getFloatFieldValue',
      'boolean' => 'getFieldBoolean',
      'uuid' => 'getFieldValue',
      'integer' => 'getIntFieldValue',
      'image' => 'getImageFieldValue',
      'file' => 'getFileFieldValue',
      'entity_reference' => 'getReferencedFieldValue',
      'link' => 'getLinkFieldValue',
    ];
  }

  /**
   * Ignored fields used processing nodes.
   * @return array list of ignored field names.
   */
  protected static function ignoredFieldNames() {
    return [
      'parent',
      'tid',
      'vid',
      'title',
      'langcode',
      'uid',
      'promote',
      'sticky',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'default_langcode',
      'publish_on',
      'unpublish_on',
    ];
  }
}
