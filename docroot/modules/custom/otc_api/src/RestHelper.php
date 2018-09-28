<?php

namespace Drupal\otc_api;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\paragraphs\Entity\Paragraph;

/**
 *
 */
class RestHelper implements RestHelperInterface {
  /**
   * For creating entity queries.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * To query entities by uuid.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\Query\QueryFactory $queryFactory
   *   entity query factory.
   */
  public function __construct() {
    $this->queryFactory = \Drupal::service('entity.query');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * Get CacheMetaData for content list or specific result.
   *
   * @param mixed $result
   *   processed content array.
   * @param string $entity_type
   *   (optional) defaults to node
   *   can be node or taxonomy_term.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result, $entity_type = 'node') {
    $cacheMetaData = new CacheableMetadata();
    $cacheMetaData->setCacheContexts(['url']);

    if (empty($result) || !is_array($result)) {
      $result = [];
    }

    if ($entity_type === 'node') {
      return $this->cacheNodeMetaData($cacheMetaData, $result);
    }
    elseif ($entity_type === 'taxonomy_term') {
      return $this->cacheTermMetaData($cacheMetaData, $result);
    }
  }

  /**
   * Get CacheMetaData for term list or specific term result.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata cache metadata object
   */
  protected function cacheTermMetaData(CacheableMetadata $cacheMetaData, $result = []) {
    if (!empty($result['tid'])) {
      $cacheMetaData->setCacheTags(['taxonomy_term:' . $result['tid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['taxonomy_term']);

    return $cacheMetaData;
  }

  /**
   * Get CacheMetaData for node list or specific result.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata cache metadata object
   */
  protected function cacheNodeMetaData(CacheableMetadata $cacheMetaData, $result = []) {
    if (!empty($result['nid'])) {
      $cacheMetaData->setCacheTags(['node:' . $result['nid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['node_list']);

    return $cacheMetaData;
  }

  /**
   * Validate a content type exists.
   *
   * @param [type] $contentType
   *   [description].
   *
   * @return boolean              [description]
   */
  public static function isContentType($contentType = NULL) {
    return in_array($contentType, array_keys(NodeType::loadMultiple()));
  }

  /**
   * Validate content type string.
   *
   * @param string $contentType
   *   the content type.
   *
   * @return bool
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
      'bricky',
    ];

    return in_array($contentType, $allowedContentTypes);
  }

  /**
   * Check to see if a given vocabulary is permitted in the api call.
   *
   * @param string $vocabulary
   *   the vocabulary name/id.
   *
   * @return bool
   */
  protected static function vocabularyPermitted($vocabulary) {
    return in_array($vocabulary, [
      'category',
      'tag',
      'contributor_group',
    ]);
  }

  /**
   *
   */
  public function fetchAllIdeas($options = []) {
    $defaults = [
      'page' => 0,
      'published' => TRUE,
    // Result limit.
      'limit' => 10,
    // Toggle off recursion.
      'recurse' => TRUE,
    // Deepest level of recursion.
      'maxDepth' => 2,
    // Current depth of recursion.
      'currentDepth' => 0,
      'multiValueGroups' => [],
      'sort' => [
        'field_sort_by_date' => 'DESC',
        'changed' => 'DESC',
      ],
    ];
    $options = array_merge($defaults, $options);

    $category_uuids = [];
    if ($options['category'] && is_array($options['category'])) {
      $category_uuids = $this->lookupTermUuids($options['category']);
      if ($category_uuids) {
        $options['multiValueGroups']['field_category.entity.uuid'] = $category_uuids;
      }
    }

    $tag_uuids = [];
    if ($options['tag'] && is_array($options['tag'])) {
      $tag_uuids = $this->lookupTermUuids($options['tag']);
      if ($tag_uuids) {
        $options['multiValueGroups']['field_tag.entity.uuid'] = $tag_uuids;
      }
    }

    $ideaTypes = array('look', 'project', 'article', 'recipe', 'download');
    $options['multiValueGroups']['type'] = $ideaTypes;
    if ($options['type'] && is_array($options['type'])) {
      $types = array_intersect($options['type'], $ideaTypes);
      if ($types) {
        $options['multiValueGroups']['type'] = $types;
      }
    }

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published'],
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
      ->range($options['page'] * $limit, $limit)
      ->execute();

    if (!$entity_ids) {
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
   *
   * @param string $contentType
   *   the content type.
   * @param array $options
   *   - integer $page page number (default 0)
   *   - boolean $published true for published, false for all. (default true)
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array of nodes.
   */
  public function fetchAll($actions, $contentType, $options = []) {

    if (!self::isContentType($contentType)) {
      throw new Rest404Exception();
    }

    if (!self::contentTypePermitted($contentType)) {
      throw new Rest403Exception();
    }

    $defaults = [
      'page' => 0,
      'published' => TRUE,
    // Result limit.
      'limit' => 10,
    // Toggle off recursion.
      'recurse' => TRUE,
    // Deepest level of recursion.
      'maxDepth' => 2,
    // Current depth of recursion.
      'currentDepth' => 0,
      'conditions' => [
        'type' => $contentType,
      ],
    ];
    if ($contentType === 'contributor') {
      $defaults['sort'] = [
        'field_full_name' => 'ASC',
        'changed' => 'DESC',
      ];
    }
    $options = array_merge($defaults, $options);

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published'],
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
      ->range($options['page'] * $limit, $limit)
      ->execute();

    if (!$entity_ids) {
      $response['results'] = [];
      return $response;
    }

    $business_unit = \Drupal::request()->query->get('business_unit');
    $page_type = \Drupal::request()->query->get('page_type');
    $page_alias = \Drupal::request()->query->get('page_title');

    if ($page_alias != "" && $business_unit != "" && $page_type != "") {
      $response['count'] = 1;
      $path = \Drupal::service('path.alias_manager')->getPathByAlias('/' . $page_alias);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = Node::load($matches[1]);
        $nid = $node->id();
        $entity_ids = array($nid => $nid);
      }
    }

    $response['results'] = $this->processNodes(
      \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($entity_ids),
      $options
    );

    if ($page_alias != "" && $business_unit != "" && $page_type != "") {

      if (empty($response['results'][0])) {
        $response = array();
        $response['error'] = "Not found.";
      }
    }

    return $response;
  }

  /**
   * Get all terms from a vocabulary.
   *
   * @param string $vocabulary
   *   the vocabulary.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array of terms.
   */
  public function fetchAllTerms($vocabulary, $options = []) {
    if (!in_array($vocabulary, taxonomy_vocabulary_get_names())) {
      throw new Rest404Exception();
    }

    if (!self::vocabularyPermitted($vocabulary)) {
      throw new Rest403Exception();
    }

    $defaults = [
      'page' => 0,
    // Result limit per page.
      'limit' => 10,
    // Toggle off recursion.
      'recurse' => TRUE,
    // Deepest level of recursion.
      'maxDepth' => 2,
    // Current depth of recursion.
      'currentDepth' => 0,
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

    if (!$entity_ids) {
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
   *
   * @param string $contentType
   *   content type for validation.
   * @param string $id
   *   uuid/alias of the content.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array processed node, simplified for rest
   */
  public function fetchOne($contentType, $id = '', $options = []) {
    if (!self::contentTypePermitted($contentType)) {
      throw new Rest403Exception();
    }

    $defaults = [
    // Toggle off recursion.
      'recurse' => TRUE,
    // Deepest level of recursion.
      'maxDepth' => 2,
    // Current depth of recursion.
      'currentDepth' => 0,
    ];
    $options = array_merge($defaults, $options);

    if (self::isUuid($id)) {
      $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $id]);
      if (!$result) {
        throw new Rest404Exception();
      }

      $node = current($result);
    }
    else {
      $node = $this->lookupNodeByAlias($id);
    }

    if (!$node || !self::contentTypePermitted($node->getType()) || $node->getType() !== $contentType) {
      throw new Rest404Exception();
    }

    return $this->processNode($node, $options);
  }

  /**
   * Get one term by uuid.
   *
   * @param string $vocabular
   *   type for validation.
   * @param string $id
   *   uuid of the term or path alias.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array processed term, simplified for rest
   */
  public function fetchOneTerm($vocabulary, $id = '', $options = []) {
    if (!self::vocabularyPermitted($vocabulary)) {
      throw new Rest403Exception();
    }

    $defaults = [
    // Toggle off recursion.
      'recurse' => TRUE,
    // Deepest level of recursion.
      'maxDepth' => 2,
    // Current depth of recursion.
      'currentDepth' => 0,
    ];
    $options = array_merge($defaults, $options);

    if (self::isUuid($id)) {
      $result = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['uuid' => $id]);
      if (!$result) {
        throw new Rest404Exception();
      }
      $term = current($result);
    }
    else {
      $term = $this->lookupTermByAlias($id);
    }

    if (!$term) {
      throw new Rest404Exception();
    }

    if (!self::vocabularyPermitted($term->getVocabularyId())) {
      throw new Rest403Exception();
    }

    return $this->processTerm($term, $options);
  }

  /**
   * Fetch all paginated content associated with a particular reference.
   *
   * @param string $uuid
   *   the uuid of the referenced id.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @param string $field_name
   *   the field name referencing a content.
   *
   * @return object page of content results for a given reference
   */
  protected function fetchReferencedContent($uuid = '', $options = [], $field_name = 'field_category') {
    $defaults = [
      'page' => 0,
    // Result limit per page.
      'limit' => 10,
      'published' => TRUE,
      'conditions' => [
        $field_name . '.entity.uuid' => $uuid,
      ],
    ];

    if ($field_name === 'field_contributor_category') {
      $options['sort'] = [
        'field_full_name' => 'ASC',
        'changed' => 'DESC',
      ];
    }

    if ($options['isReferencedContentBySKU'] == 'yes') {
      $options['sort'] = [
        'created' => 'DESC',
      ];
    }

    $options = array_merge($defaults, $options);

    $limit = $options['limit'];
    $response = [
      'limit' => $limit,
      'page' => $options['page'],
      'published' => $options['published'],
    ];

    $response['count'] = intval($this->newNodeQuery($options)->count()->execute());

    $entity_ids = $this->newNodeQuery($options)
      ->range($options['page'] * $limit, $limit)
      ->execute();

    // Return result count content by sku.
    if ($options['countSKUContent'] == 'yes') {
      return array('count' => $response['count']);
    }
    if (!$entity_ids) {
      $response['results'] = [];
      return $response;
    }

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($entity_ids);

    foreach ($nodes as $node) {
      if ($options['recurse']) {
        $response['results'][] = $this->processNode($node, $options);
      }
      else {
        $response['results'][] = $this->shallowEntity($node);
      }
    }

    return $response;
  }

  /*protected function fetchReferencedProductContent($uuid = '', $options = [], $field_name = 'field_products') {

  $defaults = [
  'page' => 0,
  'limit' => 10, // result limit per page
  'published' => true,
  'conditions' => [
  $field_name . '.entity.uuid' => $uuid,
  ]
  ];

  //$options['recurse'] = true;
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
  $response['results'][] = $this->processNode($node, $options);
  } else {
  $response['results'][] = $this->productReferencedEntity($node, $options);
  }
  }

  return $response;
  } */

  /**
   * Fetch all paginated content associated with a particular contributor group.
   *
   * @param string $id
   *   the uuid or path alias of the contributor group.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @return object page of content results for a given contributor group
   */
  public function fetchContributorGroupContent($id = '', $options = []) {
    $uuid = $id;

    if (!self::isUuid($id)) {
      $term = $this->lookupTermByAlias($id);
      if (!$term) {
        throw new Rest404Exception();
      }

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_contributor_category');
  }

  /**
   * Fetch all paginated content associated with a particular category.
   *
   * @param string $id
   *   the uuid or path alias of the category.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @return object page of content results for a given category
   */
  public function fetchCategoryContent($id = '', $options = []) {
    $defaults = [
      'sort' => [
        'field_sort_by_date' => 'DESC',
        'changed' => 'DESC',
      ],
    ];
    $options = array_merge($defaults, $options);

    $uuid = $id;

    if (!self::isUuid($id)) {
      $term = $this->lookupTermByAlias($id);
      if (!$term) {
        throw new Rest404Exception();
      }

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_category');
  }

  /**
   * Fetch all paginated content associated with a particular contributor.
   *
   * @param string $id
   *   the uuid or path alias of the contributor.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @return object page of content results for a given contributor
   */
  public function fetchContributorContent($id = '', $options = []) {
    if (self::isUuid($id)) {
      $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $id]);
      if (!$result) {
        throw new Rest404Exception();
      }
      $node = current($result);
    }
    else {
      $node = $this->lookupNodeByAlias($id);
    }

    if (!$node) {
      throw new Rest404Exception();
    }

    $defaults = [
      'multiValueGroups' => [
        'type' => [
          'article',
          'look',
          'project',
          'recipe',
          'download',
        ],
      ],
    ];
    $options = array_merge($defaults, $options);

    $uuid = $node->uuid->value;
    return $this->fetchReferencedContent($uuid, $options, 'field_contributor');
  }

  /**
   * Fetch all paginated content associated with a particular product sku.
   *
   * @param string $id
   *   the sku of the product.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @return object page of content results for a given contributor
   */
  public function fetchProductSKUContent($id = '', $options = []) {

    $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['field_sku' => $id]);

    if (!$result) {
      throw new Rest404Exception();
    }
    $node = current($result);

    if (!$node) {
      throw new Rest404Exception();
    }

    $defaults = [
      'multiValueGroups' => [
        'type' => [
          'article',
          'step',
          'project',
          'look',
          'recipe',
        ],
      ],
    ];

    $options = array_merge($defaults, $options);

    // Set flag for product referenced content.
    $options['isReferencedContentBySKU'] = 'yes';
    // Show only full style of image.
    $options['full_image_style'] = 'yes';
    // Show only listed field on product referenced content.
    $options['referencedContentBySKUField'] = array(
      "type" => "type",
      "created" => "created",
      "path" => "path",
      "field_896x896_img" => "field_896x896_img",
      "field_display_title" => "field_display_title",
    );

    $uuid = $node->uuid->value;

    return $this->fetchReferencedContent($uuid, $options, 'field_products');
  }

  /**
   * Fetch all paginated content associated with a particular tag.
   *
   * @param string $id
   *   the uuid or path alias of the tag.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion
   *   - integer $page the current page.
   *
   * @return object page of content results for a given tag
   */
  public function fetchTagContent($id = '', $options = []) {
    $uuid = $id;

    if (!self::isUuid($id)) {
      $term = $this->lookupTermByAlias($id);
      if (!$term) {
        throw new Rest404Exception();
      }

      $uuid = $term->uuid->value;
    }

    return $this->fetchReferencedContent($uuid, $options, 'field_tag');
  }

  /**
   * Lookup term uuids from list of aliases or uuids.
   *
   * @param mixed $ids
   *   uuids or path aliases.
   *
   * @return [type]      [description]
   */
  protected function lookupTermUuids($ids = []) {
    $uuids = [];
    foreach ($ids as $id) {
      if (self::isUuid($id)) {
        $uuids[] = $id;
      }
      else {
        $term = $this->lookupTermByAlias($id);
        if (!$term) {
          continue;
        }
        $uuids[] = $term->uuid->value;
      }
    }

    return $uuids;
  }

  /**
   * Lookup a term by path alias.
   *
   * @param string $alias
   *   the path alias.
   *
   * @return \Drupal\taxonomy\Entity\Term or false on failure
   */
  protected function lookupTermByAlias($alias = '') {
    if (!$alias) {
      return FALSE;
    }

    $source = $this->lookupPathSource($alias);
    preg_match('/taxonomy\/term\/(\d+)/', $source, $matches);

    if (!isset($matches[1])) {
      return FALSE;
    }
    $tid = $matches[1];
    $term = Term::load($tid);

    return ($term ? $term : FALSE);
  }

  /**
   * Lookup a node by path alias.
   *
   * @param string $alias
   *   the path alias.
   *
   * @return \Drupal\node\Entity\Node or false on failure
   */
  protected function lookupNodeByAlias($alias = '') {
    if (!$alias) {
      return FALSE;
    }

    $source = $this->lookupPathSource($alias);
    preg_match('/node\/(\d+)/', $source, $matches);
    if (!isset($matches[1])) {
      return FALSE;
    }
    $nid = $matches[1];
    $node = Node::load($nid);

    return ($node ? $node : FALSE);
  }

  /**
   * Lookup source path from path alias.
   *
   * @param string $alias
   *   the content alias.
   *
   * @return string the source path or FALSE
   */
  protected function lookupPathSource($alias = '') {
    if (!$alias) {
      return FALSE;
    }

    return \Drupal::service('path.alias_storage')->lookupPathSource('/' . $alias, 'en');
  }

  /**
   * Get new entity query for a content type.
   *
   * @param array $options
   *   - string $type (optional) content type to query on
   *   - boolean $published  true for published only, false for everything
   *   - array $conditions entity query conditions.
   *
   * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
   *   preset for the content type.
   */
  protected function newNodeQuery($options = []) {
    $query = \Drupal::entityQuery('node');
    if (!$options['published']) {
      $options['multiValueGroups']['status'] = [1, 0];
    }
    else {
      $query->condition('status', 1);
    }

    if (!empty($options['orConditionGroups'])) {
      foreach ($options['orConditionGroups'] as $conditions) {
        if (!empty($conditions)) {
          $group = $query->orConditionGroup();
          foreach ($conditions as $key => $value) {
            $group->condition($key, $value);
          }
          $query->condition($group);
        }
      }
    }

    if (!empty($options['multiValueGroups'])) {
      foreach ($options['multiValueGroups'] as $key => $values) {
        if (!empty($values)) {
          $group = $query->orConditionGroup();
          foreach ($values as $value) {
            $group->condition($key, $value);
          }
          $query->condition($group);
        }
      }
    }

    if (!empty($options['conditions'])) {
      foreach ($options['conditions'] as $key => $value) {
        $query->condition($key, $value);
      }
    }

    if (!empty($options['sort'])) {
      foreach ($options['sort'] as $field => $direction) {
        $query->sort($field, $direction);
      }
    }
    else {
      $query->sort('changed', 'DESC');
    }

    return $query;
  }

  /**
   * Get an entity query for taxonomy lookup.
   *
   * @param string $vocabulary
   *   the vocabulary.
   *
   * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
   *   preset for the content type.
   */
  protected function newTermQuery($vocabulary) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);

    return $query;
  }

  /**
   * Process list of nodes.
   *
   * @param array $nodes
   *   array of Node objects.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array of arrays representing a node in clean REST format
   */
  protected function processNodes($nodes = [], $options = []) {
    $results = [];
    foreach ($nodes as $node) {
      $results[] = $this->processNode($node, $options);
    }

    return $results;
  }

  /**
   * Process all fields in a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   the node object.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array node information in clean format for REST
   */
  protected function processNode(Node $node, $options = []) {

    $business_unit = \Drupal::request()->query->get('business_unit');
    $page_type = \Drupal::request()->query->get('page_type');
    $page_alias = \Drupal::request()->query->get('page_title');

    if ($node->getType() == 'bricky' && $business_unit != "" && $page_type != "" && $page_alias != "") {
      $id = $node->id();
      $response = $this->fetchAllBricky($options, $id, $business_unit);
      return $response;
    }

    $view = [];
    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());
    $storageDefinitions = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');

    foreach ($fieldDefinitions as $name => $fieldDefinition) {
      if ($options['isReferencedContentBySKU'] == 'yes') {
        if (!in_array($name, $options['referencedContentBySKUField'])) {
          continue;
        }
      }
      $options['fieldDefinition'] = $fieldDefinition;
      $options['storageDefinition'] = $storageDefinitions[$name];
      $options['multiValue'] = method_exists($options['storageDefinition'], 'isMultiple') && ($options['storageDefinition']->isMultiple() || $options['storageDefinition']->getCardinality() > 1);

      if (!$fieldDefinition->getType()) {
        continue;
      }

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $ignored = in_array($name, self::ignoredFieldNames());

      if ($supported && !$ignored) {
        // No value.
        if (!$node->$name) {
          if ($options['isReferencedContentBySKU'] != 'yes') {
            $view[$name] = NULL;
          }

          continue;
        }

        $view[$name] = $this->processField($node->{$name}, $options);
      }
    }

    return $view;
  }

  /**
   * Process list of taxonomy terms.
   *
   * @param array $terms
   *   array of Term objects.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array of arrays representing a node in clean REST format
   */
  protected function processTerms($terms, $options = []) {
    $results = [];
    foreach ($terms as $term) {
      $results[] = $this->processTerm($term, $options);
    }

    return $results;
  }

  /**
   * Process all fields in a term.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   the $term object.
   *
   * @return array term information in clean format for REST
   */
  protected function processTerm(Term $term, $options = []) {
    $parents = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadParents($term->tid->value);
    $parent = '';
    if ($parents) {
      $parentTerm = Term::load(current($parents)->tid->value);
      if ($parentTerm) {
        $parent = $parentTerm->uuid->value;
      }
    }

    $view = [
      'parent' => $parent,
      'type' => $term->getVocabularyId(),
    ];

    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('taxonomy_term', $term->getVocabularyId());
    $storageDefinitions = \Drupal::service('entity.manager')->getFieldStorageDefinitions('taxonomy_term');

    foreach ($fieldDefinitions as $name => $fieldDefinition) {
      $options['fieldDefinition'] = $fieldDefinition;
      $options['storageDefinition'] = $storageDefinitions[$name];
      $options['multiValue'] = method_exists($options['storageDefinition'], 'isMultiple') && ($options['storageDefinition']->isMultiple() || $options['storageDefinition']->getCardinality() > 1);

      if (!$fieldDefinition->getType()) {
        continue;
      }

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $ignored = in_array($name, self::ignoredFieldNames());

      if ($supported && !$ignored) {
        // No value.
        if (!$term->$name) {
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
   *
   * @see self::supportedFieldTypes()
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field item list.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *
   * @return mixed "formatted" value of the field
   */
  protected function processField(FieldItemListInterface $field, $options = []) {
    $method = self::supportedFieldTypes()[$options['fieldDefinition']->getType()];
    return $this->{$method}($field, $options);
  }

  /**
   * Get simple value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return string simple string value
   */
  protected function getFieldValue(FieldItemListInterface $field, $options = []) {
    if ($options['multiValue']) {
      $return = [];
      foreach ($field->getValue() as $item) {
        $return[] = $item['value'];
      }
      return $return;
    }

//9/28 commeting for changing image URls to be cms.orientaltrading.com

   if ($field->getName() == "field_legacy_content") { 
        $matchArray = array('http://otc.prod.acquia-sites.com/','https://cms.orientaltrading.com/');                
        $field_legacy_content = str_replace($matchArray,'https://cms.orientaltrading.com/',$field->value);        
      return $field_legacy_content; 
    }
    if ($field->getName() == "field_product_in_stock_status") {
      return trim($field->value);
    }

    return $field->value;
  }

  /**
   * Get path alias field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
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
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return int
   */
  protected function getIntFieldValue(FieldItemListInterface $field, $options = []) {
    if ($options['multiValue']) {
      $return = [];
      foreach ($field->getValue() as $item) {
        $return[] = intval($item['value']);
      }
      return $return;
    }

    return intval($field->value);
  }

  /**
   * Get simple float value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return float
   */
  protected function getFloatFieldValue(FieldItemListInterface $field, $options = []) {
    if ($options['multiValue']) {
      $return = [];
      foreach ($field->getValue() as $item) {
        $return[] = floatval($item['value']);
      }
      return $return;
    }

    return floatval($field->value);
  }

  /**
   * Get link value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - includes FieldStorageDefinitionInterface $fieldStorage field storage information
   *     to get field cardinality.
   *
   * @return string simple string value
   */
  protected function getLinkFieldValue(FieldItemListInterface $field, $options = []) {
    $values = $field->getValue();
    $return = ($options['multiValue'] ? [] : NULL);

    if ($values) {
      if ($options['multiValue']) {
        foreach ($values as $linkData) {
          $return[] = [
            'url' => $linkData['uri'],
            'title' => $linkData['title'],
          ];
        }
        return $return;
      }
      else {
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
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return string simple string value
   */
  protected function getDateFieldValue(FieldItemListInterface $field, $options = []) {
    if ($options['multiValue']) {
      $return = [];
      foreach ($field->getValue() as $item) {
        $return[] = \Drupal::service('date.formatter')->format($item['value'], 'html_datetime');
      }
      return $return;
    }

    return \Drupal::service('date.formatter')->format($field->value, 'html_datetime');
  }

  /**
   * Get true/false value from boolean.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field item list.
   *
   * @return bool
   */
  protected function getFieldBoolean(FieldItemListInterface $field, $options = []) {
    // If for some reason a multi-value boolean field is selected, which is
    // non-sense.
    if ($options['multiValue']) {
      $items = $field->getValue();
      if ($items) {
        return current($items)['value'] === "1";
      }
      return FALSE;
    }

    return $field->value === "1";
  }

  /**
   * Get one or more entity reference object arrays.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field items.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   *   - int referenceDepth to prevent infinite recursion
   *
   * @return array of arrays representing referenced node
   */
  protected function getReferencedFieldValue(FieldItemListInterface $field, $options = []) {
    $referenceType = $options['fieldDefinition']->getSettings()['target_type'];

    switch ($referenceType) {
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
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field items.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   *   - int referenceDepth to prevent infinite recursion
   *
   * @return array of arrays representing referenced node
   */
  protected function getReferencedNode(FieldItemListInterface $field, $options = []) {
    $referenceData = $field->getValue();

    // Reference Field.
    $recurse = $options['currentDepth'] < $options['maxDepth'] && $options['recurse'];
    $options['currentDepth'] = ($recurse ? $options['currentDepth'] + 1 : $options['currentDepth']);

    $return = ($options['multiValue'] ? [] : NULL);
    if ($referenceData) {
      if ($options['multiValue']) {
        foreach ($referenceData as $index => $target) {
          $node = Node::load($target['target_id']);
          if ($node) {
            $return[] = ($recurse ? $this->processNode($node, $options) : $this->shallowEntity($node));
          }
        }
        return $return;
      }
      else {
        $node = Node::load(current($referenceData)['target_id']);
        if ($node) {
          return ($recurse ? $this->processNode($node, $options) : $this->shallowEntity($node));
        }
      }
    }

    return $return;
  }

  /**
   * Dereference a term reference field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   term reference field list.
   * @param array $options
   *   options array.
   *
   * @return mixes term object or array of terms
   */
  protected function getReferencedTerm(FieldItemListInterface $field, $options = []) {
    $referenceData = $field->getValue();

    $recurse = $options['currentDepth'] < $options['maxDepth'] && $options['recurse'];
    $options['currentDepth'] = ($recurse ? $options['currentDepth'] + 1 : $options['currentDepth']);

    $return = ($options['multiValue'] ? [] : NULL);
    if ($referenceData) {
      if ($options['multiValue']) {
        foreach ($referenceData as $index => $target) {
          $term = Term::load($target['target_id']);
          if ($term) {
            $return[] = ($recurse ? $this->processTerm($term, $options) : $this->shallowEntity($term));
          }
        }
        return $return;
      }
      else {
        $term = Term::load(current($referenceData)['target_id']);
        if ($term) {
          return ($recurse ? $this->processTerm($term, $options) : $this->shallowEntity($term));
        }
      }
    }

    return $return;
  }

  /**
   * Get simple object with type and uuid for referenced entity.
   *
   * @param mixed $entity
   *   node or taxonomy_term.
   *
   * @return array representing simple type and uuid object.
   */
  protected function shallowEntity($entity) {
    $type = '';
    if (!empty($entity->type)) {
      $type = $this->getNodeType($entity->type);
    }
    elseif (!empty($entity->vid)) {
      $type = current($entity->vid->getValue())['target_id'];
    }

    return [
      'uuid' => $entity->uuid->value,
      'type' => $type,
    ];
  }

  /**
   * Get one or more file object arrays.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field items.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   *
   * @return array of arrays of file urls.
   */
  protected function getFileFieldValue(FieldItemListInterface $field, $options = []) {
     $fileData = $field->getValue();
    Global $base_url;    
    $return = ($options['multiValue'] ? [] : NULL);
    if ($fileData) {
      if ($options['multiValue']) {
        foreach ($fileData as $target) {
          $file = File::load($target['target_id']);
          if ($file) { 
          //   $fileURL = str_replace($base_url,'https://www.fun365.orientaltrading.com',$file->url());
             $return[] = $file->url();
          }
        }
        return $return;
      }

      // Single.
      $file = File::load(current($fileData)['target_id']);
      if ($file) { 
        // $fileURL = str_replace($base_url,'https://www.fun365.orientaltrading.com',$file->url());
         return $file->url();  
      }
    }

    return $return;
  }

  /**
   * Get one or more entity reference object arrays.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field items.
   *
   * @return string node type
   */
  protected function getNodeType(FieldItemListInterface $field) {
    $value = $field->getValue();
    if ($value) {
      return current($value)['target_id'];
    }

    return '';
  }

  /**
   * Get one or more image object arrays.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field items.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get image resolution constraints.
   *   - FieldStorageDefinitionInterface $storageDefinition field storage information
   *     to get field cardinality.
   *
   * @return array of arrays of image urls.
   */
  protected function getImageFieldValue(FieldItemListInterface $field, $options = []) {
    $imageData = $field->getValue();
    $resolution = $options['fieldDefinition']->getSettings()['max_resolution'];

    $resolutions = $this->imageStyles($resolution);

    $return = ($options['multiValue'] ? [] : NULL);
    if ($imageData) {
      if ($options['multiValue']) {
        foreach ($imageData as $image) {
          $return[] = $this->processImage($image['target_id'], $resolutions);
        }
        return $return;
      }
      if ($options['full_image_style'] == 'yes') {
        return $this->processImage(current($imageData)['target_id'], []);
      }
      // Single.
      return $this->processImage(current($imageData)['target_id'], $resolutions);
    }

    return $return;
  }

  /**
   * Process an image field.
   *
   * @param int $target_id
   *   file entity id.
   * @param array $resolutions
   *   image style names that might apply to this image.
   *
   * @return array of image urls
   */
  protected function processImage($target_id, $resolutions = []) {
    /*
    $streamWrapper = \Drupal::service('stream_wrapper_manager');
    $baseFile = \Drupal::service('entity.manager')
      ->getStorage('file')
      ->load($target_id);

    $internalUri = $baseFile->getFileUri();
    $repalceURL =  $streamWrapper->getViaUri($internalUri)->getExternalUrl();
    
    // Relace Base URL
    Global $base_url;
    $base_url; 
    $repalceURL = str_replace($base_url,'https://www.fun365.orientaltrading.com',$repalceURL);
    
    $result = [
      'full' => $repalceURL,
    ];
    
    $styleURL = "";
    foreach ($resolutions as $resolution) {
      $styleName = $resolution;
      $style = ImageStyle::load($resolution);
      if ($style) {           
        $styleURL = $style->buildUrl($internalUri);
        $styleURL =  str_replace($base_url,'https://www.fun365.orientaltrading.com',$styleURL);
        $result[$styleName] = $styleURL; 
      }
    }

    return $result;

    */

    $streamWrapper = \Drupal::service('stream_wrapper_manager');
    $baseFile = \Drupal::service('entity.manager')
      ->getStorage('file')
      ->load($target_id);

    $internalUri = $baseFile->getFileUri();

    $result = [
      'full' => $streamWrapper->getViaUri($internalUri)->getExternalUrl(),
    ];

    foreach ($resolutions as $resolution) {
      $styleName = $resolution;
      $style = ImageStyle::load($resolution);
      if ($style) {
        $result[$styleName] = $style->buildUrl($internalUri);
      }
    }
    return $result;
  }

  /**
   * Based on string max resolution from image field configuration, get the
   * list of image styles that share the same aspect ratio.
   *
   * @param string $resolution
   *   [width]x[height] string.
   *
   * @return array list of image styles
   */
  protected function imageStyles($resolution) {
    $resolutions = self::resolutions();

    preg_match('/(\d+)x(\d+)/', $resolution, $matches);

    if (!$matches || !$matches[2]) {
      return [];
    }

    $aspectRatio = number_format(round($matches[1] / $matches[2], 2), 2);
    if (!in_array($aspectRatio, array_keys($resolutions))) {
      return [];
    }

    return $resolutions[$aspectRatio];
  }

  /**
   * Is the argument a uuid?
   *
   * @param string $uuid
   *   string to test.
   *
   * @return bool
   */
  protected static function isUuid($uuid = '') {
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid) === 1;
  }

  /**
   * Get image styles for each aspect ratio.
   *
   * @return array list of resolutions/image styles per aspect ratio
   */
  protected static function resolutions() {
    return [
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
   *
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
   *
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

  /**
   *
   */
  public function fetchAllBricky($options = [], $id, $actions) {

    $business_unit = \Drupal::request()->query->get('business_unit');
    $page_type = \Drupal::request()->query->get('page_type');
    $page_alias = \Drupal::request()->query->get('page_title');

    $path = \Drupal::service('path.alias_manager')->getPathByAlias('/' . $page_alias);

    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = Node::load($matches[1]);
    }

    $iscount = 0;

    if (!empty($node)) {

      $id = $node->id();

      $cartridgeArray = $this->getParagraphId();

      // $node = Node::load($id);
      $field_brand = $node->get('field_brand')->getValue();

      $actions = strtolower($business_unit);

      if ($actions != "") {
        $termid = $this->getTidByName($actions);
      }

      $field_page_type = $node->get('field_page_type')->getValue();

      $term_id = $field_page_type[0]['target_id'];

      if ($term_id != "") {
        $termname = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term_id);
        $termname = $termname->getName();
      }

      if (isset($field_brand[0]['target_id'])) {
        $field_brand = $field_brand[0]['target_id'];
      }

      if ($field_brand == $termid) {
        $iscount = 1;

        $field_body = $node->get('field_body')->getValue();

        $field_title = $node->getTitle();
        $field_id = $id;
        $paragrah_reference_id = "";
        $node_paragraph = array();
        $resutlArray = [];
        $index = [];
        $flag = 0;
        $i = 0;

        $resutlArray['field_id'] = $field_id;
        $resutlArray['nid'] = $field_id;

        $resutlArray['field_title'] = $field_title;

        foreach ($field_body as $key => $value) {

          $brick_id_depth = $value['depth'];
          if ($brick_id_depth == 0) {

            $resutlArray[$brick_id_depth] = $value;
            $i++;
          }
          elseif ($brick_id_depth == 1) {
            $resutlArray = $value;
          }
          elseif ($brick_id_depth == 2 && !in_array($brick_id_depth, $index)) {
            $resutlArray['left'] = $value;
            $flag = 0;
          }
          elseif ($brick_id_depth == 2 && in_array($brick_id_depth, $index)) {
            $resutlArray['right'] = $value;
            $flag = 1;
            $i = 0;
          }
          elseif ($brick_id_depth == 3 && $flag == 0) {
            $resutlArray['left'][$i] = $value;
            $paragrah_reference_id = $value['target_id'];
            if ($paragrah_reference_id != "") {

              $paragrah_id = $this->getBricksParagraphId($paragrah_reference_id);
              $p = 0;
              foreach ($paragrah_id as $paragrah_id_details) {
                $node_paragraph = Paragraph::load($paragrah_id_details)->toArray();
                foreach ($node_paragraph as $key => $node_paragraph_details) {
                  if ($key == 'field_menu_links') {
                    $l = 0;
                    foreach ($node_paragraph_details as $node_paragraph_details_val) {
                      $resutlArray['left'][$i][$p][$key]['uri'] = $node_paragraph_details_val['uri'];
                      $resutlArray['left'][$i][$p][$key]['title'] = $node_paragraph_details_val['title'];
                      $l++;
                    }
                  }
                  if ($key == 'field_menu_without_category') {
                    $l = 0;
                    foreach ($node_paragraph_details as $node_paragraph_details_val) {
                      $resutlArray['left'][$i][$p][$key]['uri'] = $node_paragraph_details_val['uri'];
                      $resutlArray['left'][$i][$p][$key]['title'] = $node_paragraph_details_val['title'];
                      $l++;
                    }
                  }
                }
                $p++;
              }
            }
            $i++;
          }
          elseif ($brick_id_depth == 3 && $flag == 1) {

            $paragrah_reference_id = $value['target_id'];

            if ($paragrah_reference_id != "") {

              $paragrah_id = $this->getBricksParagraphId($paragrah_reference_id);

              $paragrah_title = $this->getParagraphTitle($paragrah_reference_id);

              $brick_field_text = $this->getBricksText($paragrah_reference_id);

              $p = 0;
              $cartridgeid = "";

              $panelcount = $panelcount ? $panelcount : 1;

              foreach ($paragrah_id as $paragrah_id_details) {
                $node_paragraph = Paragraph::load($paragrah_id_details)->toArray();
                $cartridgeid = $node_paragraph['type'][0]['target_id'];

                $parent_id = $node_paragraph['parent_id'][0]['value'];

                $parentid = $this->getBricksParagraphParentId($parent_id);

                $m = 0;

                if (isset($resutlArray['right'][$i][$p][$cartridgeid]['title'])) {
                  $brick_field_text = $resutlArray['right'][$i][$p][$cartridgeid]['title'];
                }

                $parentid = $parentid ? $parentid : $cartridgeid;

                $statusonoff = $node_paragraph['field_cartridge_show_hide'][0]['value'];

                foreach ($node_paragraph as $key => $node_paragraph_details) {

                  if ($cartridgeid == "title") {
                    $parentid = $parentid ? $parentid : $cartridgeid;
                    $resutlArray['right'][$i][$parentid][$p]['title'] = $node_paragraph_details['0']['value'];
                  }

                  if ($cartridgeid == "reflektion_cartridge") {
                    $parentid = $parentid ? $parentid : $cartridgeid;
                    $resutlArray['right'][$i][$parentid][$p]['reflektion_cartridge'] = $node_paragraph_details['0']['value'];
                  }

                  if ($cartridgeid == "just_for_you") {
                    $parentid = $parentid ? $parentid : $cartridgeid;
                    $resutlArray['right'][$i][$parentid][$p]['just_for_you'] = $node_paragraph_details['0']['value'];
                  }

                  if ($cartridgeid == "cartridge_block_html") {
                    $parentid = $parentid ? $parentid : $cartridgeid;
                    $resutlArray['right'][$i][$parentid][$p]['cartridge_block_html'] = $node_paragraph_details['0']['value'];
                  }

                  if ($cartridgeid == "cartridge_show_hide") {
                    $parentid = $parentid ? $parentid : $cartridgeid;
                    $resutlArray['right'][$i][$parentid][$p]['field_cartridge_show_hide'] = $node_paragraph_details['0']['value'];
                  }

                  if ($key == "field_cartridge_common_title") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_cartridge_common_title'] = $node_paragraph_details['0']['value'];
                  }
                  if ($key == "field_cartridge_common_image") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_cartridge_common_image'] = $node_paragraph_details['0']['value'];

                    $resutlArray['right'][$i][$cartridgeid][$p]['manual_cm_re'] = 'Version_A-_-Zone1_panel' . $panelcount;

                    $panelcount = $panelcount + 1;
                  }
                  if ($key == "field_cartridge_common_url") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_cartridge_common_url'] = $node_paragraph_details['0']['value'];
                  }
                  if ($key == "field_cartridge_common_desc") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_cartridge_common_desc'] = $node_paragraph_details['0']['value'];
                  }

                  if ($key == "field_curalate_cartridge_html") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_curalate_cartridge_html'] = $node_paragraph_details['0']['value'];
                  }

                  if ($key == "field_curalate_cartridge_title") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_curalate_cartridge_title'] = $node_paragraph_details['0']['value'];
                  }

                  if ($key == "field_image_url") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_image_url'] = $node_paragraph_details['0']['value'];
                  }

                  if ($key == "field_slider_link") {
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_slider_link'] = $node_paragraph_details['0']['uri'];
                    $resutlArray['right'][$i][$cartridgeid][$p]['field_slider_link_text'] = $node_paragraph_details['0']['title'];
                  }

                  if ($key == 'field_text_link') {
                    $l = 0;
                    foreach ($node_paragraph_details as $node_paragraph_details_val) {

                      $paragrah_slider_id = $node_paragraph_details_val['target_id'];

                      if ($paragrah_slider_id != "") {

                        $node_paragraph_slider = Paragraph::load($paragrah_slider_id)->toArray();

                        $q = 0;
                        foreach ($node_paragraph_slider as $keyslider => $node_paragraph_slider_details) {

                          if ($keyslider == "field_link") {

                            $resutlArray['right'][$i][$cartridgeid][$p][$l]['linktext']['field_link'] = $node_paragraph_slider_details[0]['uri'];
                            $resutlArray['right'][$i][$cartridgeid][$p][$l]['linktext']['field_text'] = $node_paragraph_slider_details[0]['title'];
                          }
                          $q++;
                        }
                      }
                      $l++;
                    }
                  }

                  $m++;
                }
                $p++;
              }
              if ($brick_field_text != "") {
                $resutlArray['right'][$i][0]['field_text'] = $brick_field_text;
              }
            }
            $i++;
          }
          $index[] = $value['depth'];
        }
      }

      $newaray = array();
      $newaray['cartridges'] = array();
      $ij = 0;
      $panelcount = $panelcount ? $panelcount : 1;

      foreach ($resutlArray['right'] as $keytitleparent => $resutlArrayDetailsparent) {
        foreach ($resutlArrayDetailsparent as $keytitle => $resutlArrayDetails) {

          if (($resutlArrayDetails[0]['field_cartridge_show_hide'] == 1) || ($resutlArrayDetails[0]['field_cartridge_show_hide'] == "")) {
            if (in_array($keytitle, $cartridgeArray, TRUE)) {

              $jk = 0;
              $newaray['cartridges'][$ij]['name'] = $keytitle;

              if ($resutlArrayDetails[0]['title'] != "" || $resutlArrayDetails[1]['title'] != "") {
                $newaray['cartridges'][$ij]['title'] = $resutlArrayDetails[1]['title'] ? $resutlArrayDetails[1]['title'] : $resutlArrayDetails[0]['title'];
              }

              if ($resutlArrayDetails[0]['cartridge_block_html'] != "" || $resutlArrayDetails[1]['cartridge_block_html'] != "") {
                $newaray['cartridges'][$ij]['cartridge_block_html'] = $resutlArrayDetails[1]['cartridge_block_html'] ? $resutlArrayDetails[1]['cartridge_block_html'] : $resutlArrayDetails[0]['cartridge_block_html'];
              }

              foreach ($resutlArrayDetails as $resutlArrayDetailsResult) {

                if (in_array($keytitle, $cartridgeArray, TRUE)) {

                  if ($resutlArrayDetailsResult['field_cartridge_common_url'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_cartridge_common_url'] = $resutlArrayDetailsResult['field_cartridge_common_url'];
                  }

                  if ($resutlArrayDetailsResult['field_cartridge_common_title'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_cartridge_common_title'] = $resutlArrayDetailsResult['field_cartridge_common_title'];
                  }

                  if ($resutlArrayDetailsResult['field_cartridge_common_desc'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_cartridge_common_desc'] = $resutlArrayDetailsResult['field_cartridge_common_desc'];
                  }

                  if ($resutlArrayDetailsResult['field_cartridge_common_image'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_cartridge_common_image'] = $resutlArrayDetailsResult['field_cartridge_common_image'];
                    $newaray['cartridges'][$ij]['section'][$jk]['manual_cm_re'] = $resutlArrayDetailsResult['manual_cm_re'];
                    $jk++;
                  }

                  if ($resutlArrayDetailsResult['field_curalate_cartridge_title'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_curalate_cartridge_title'] = $resutlArrayDetailsResult['field_curalate_cartridge_title'];
                  }
                  if ($resutlArrayDetailsResult['field_curalate_cartridge_html'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_curalate_cartridge_html'] = $resutlArrayDetailsResult['field_curalate_cartridge_html'];
                  }

                  if ($resutlArrayDetailsResult['field_image_url'] != "") {
                    $newaray['cartridges'][$ij]['section'][$jk]['field_image_url'] = $resutlArrayDetailsResult['field_image_url'];
                    $newaray['cartridges'][$ij]['section'][$jk]['field_slider_link'] = $resutlArrayDetailsResult['field_slider_link'];
                    $newaray['cartridges'][$ij]['section'][$jk]['field_slider_link_text'] = $resutlArrayDetailsResult['field_slider_link_text'];
                    $h = 0;
                    foreach ($resutlArrayDetailsResult as $keysliders => $resutlArrayDetailsResultDeatils) {
                      if (is_numeric($keysliders) && $resutlArrayDetailsResultDeatils['linktext']['field_text'] != "") {

                        $newaray['cartridges'][$ij]['section'][$jk]['linktext'][$h]['field_text'] = $resutlArrayDetailsResultDeatils['linktext']['field_text'];
                        $newaray['cartridges'][$ij]['section'][$jk]['linktext'][$h]['field_link'] = $resutlArrayDetailsResultDeatils['linktext']['field_link'];

                        $h++;
                      }
                    }

                    $jk++;
                  }
                }
              }

              $ij++;
            }
          }
        }
      }

      $newarayvalues = array();
      foreach ($newaray as $keys => $newaraydetails) {
        $newarayvalues[]['cartridges'] = $newaraydetails;
      }

      $resutlArray['right'] = $newarayvalues[0];
      $resutlArray['field_id'] = $field_id;
      $resutlArray['nid'] = $field_id;
      $resutlArray['field_title'] = $field_title;
      $resutlArray['field_brand'] = $actions ? $actions : 'fun365';
      $resutlArray['field_type'] = $termname ? $termname : $page_type;

      // $resutlArray['right'] = $newaray;.
      $counts = array_count_values($index);
      if ($counts['0'] > 1) {

        $resutlArray = [];

        $resutlArray['field_id'] = $field_id;
        $resutlArray['nid'] = $field_id;

        $resutlArray['field_title'] = $field_title;
        $i = 0;
        foreach ($field_body as $key => $value) {

          $brick_id_depth = $value['depth'];
          if ($brick_id_depth == 0) {

            $paragrah_reference_id = $value['target_id'];

            if ($paragrah_reference_id != "") {

              $paragrah_id = $this->getBricksParagraphId($paragrah_reference_id);

              $brick_field_text = $this->getBricksText($paragrah_reference_id);

              $p = 0;
              $cartridgeid = "";
              $panelcount = $panelcount ? $panelcount : 1;
              foreach ($paragrah_id as $paragrah_id_details) {
                $node_paragraph = Paragraph::load($paragrah_id_details)->toArray();
                $cartridgeid = $node_paragraph['type'][0]['target_id'];
                $m = 0;

                if (isset($resutlArray[$i][0][$p][$cartridgeid]['title'])) {
                  $brick_field_text = $resutlArray[$i][0][$p][$cartridgeid]['title'];
                }

                if ($p == 0) {
                  $resutlArray[$i][0]['cartridge_name'] = $cartridgeid;
                }

                foreach ($node_paragraph as $key => $node_paragraph_details) {

                  if ($key == "field_cartridge_common_title") {
                    $resutlArray[$i][0][$p]['field_cartridge_common_title'] = $node_paragraph_details['0']['value'];
                  }
                  if ($key == "field_cartridge_common_image") {
                    $resutlArray[$i][0][$p]['field_cartridge_common_image'] = $node_paragraph_details['0']['value'];

                    $resutlArray[$i][0][$p]['manual_cm_re'] = 'Version_A-_-Zone1_panel' . $panelcount;

                    $panelcount = $panelcount + 1;
                  }
                  if ($key == "field_cartridge_common_url") {
                    $resutlArray[$i][0][$p]['field_cartridge_common_url'] = $node_paragraph_details['0']['value'];
                  }
                  if ($key == "field_cartridge_common_desc") {
                    $resutlArray[$i][0][$p]['field_cartridge_common_desc'] = $node_paragraph_details['0']['value'];
                  }

                  $m++;
                }

                $p++;
              }

              if ($brick_field_text != "") {
                $resutlArray[$i][0][$p]['field_text'] = $brick_field_text;
              }
            }
          }
          $i++;
        }
      }

      $defaults = [
        'page' => 0,
        'published' => TRUE,
      // Result limit.
        'limit' => $id ? 1 : 1,
      // Toggle off recursion.
        'recurse' => TRUE,
      // Deepest level of recursion.
        'maxDepth' => 2,
      // Current depth of recursion.
        'currentDepth' => 0,
        'multiValueGroups' => [],
        'sort' => [
          'field_sort_by_date' => 'DESC',
          'changed' => 'DESC',
        ],
      ];
      $options = array_merge($defaults, $options);

      $limit = $options['limit'];
      $response = [
        'limit' => $id ? 1 : $limit,
        'page' => $options['page'],
        'published' => $options['published'],
      ];

      if ($iscount == 1) {
        $response['count'] = $id ? 1 : intval($this->newNodeQuery($options)->count()->execute());
      }
      else {
        $response['count'] = $id ? 0 : intval($this->newNodeQuery($options)->count()->execute());
      }

      $response['cartridges_details'] = $resutlArray;

      return $response;

    }

  }

  /**
   * Is the argument a id?
   *
   * @param string $id
   *   String to test.
   *
   * @return array of paragraph id.
   */
  protected static function getBricksParagraphId($id = '') {
    $query = \Drupal::database()->select('brick__field_paragraph_brick', 'bfpb');
    $query->fields('bfpb', ['field_paragraph_brick_target_id', 'entity_id']);
    $query->condition('bfpb.entity_id', $id);
    $query->condition('bfpb.bundle', 'paragraph_reference');
    $z_results = $query->execute()->fetchAll();
    $field_paragraph_brick_target_id = array();
    if (!empty($z_results)) {
      foreach ($z_results as $z_results_value) {
        $field_paragraph_brick_target_id[] = $z_results_value->field_paragraph_brick_target_id;
      }
    }
    return $field_paragraph_brick_target_id ? $field_paragraph_brick_target_id : "";
  }

  /**
   * Is the argument a id?
   *
   * @param string $id
   *   String to test.
   *
   * @return brick text
   */
  protected static function getBricksText($id = '') {
    $query = \Drupal::database()->select('brick__field_text', 'ftv');
    $query->fields('ftv', ['field_text_value']);
    $query->condition('ftv.entity_id', $id);
    $z_results = $query->execute()->fetchAll();

    $field_text = array();
    if (!empty($z_results)) {
      foreach ($z_results as $z_results_value) {
        $field_text_value = $z_results_value->field_text_value;
      }
    }
    return (isset($field_text_value)) ? $field_text_value : "";
  }

  /**
   * Is the argument a id?
   *
   * @param string $id
   *   String to test.
   *
   * @return paragraph title
   */
  protected static function getParagraphTitle($id = '') {
    $query = \Drupal::database()->select('brick_field_data', 'ftd');
    $query->fields('ftd', ['title']);
    $query->condition('ftd.type', 'paragraph_reference');
    $query->condition('ftd.id', $id);
    $z_results = $query->execute()->fetchAll();

    $field_text = array();
    if (!empty($z_results)) {
      foreach ($z_results as $z_results_value) {
        $field_paragraph_title = $z_results_value->title;
      }
    }
    return (isset($field_paragraph_title)) ? $field_paragraph_title : "";
  }

  /**
   * Is the argument a id?
   *
   * @param string $id
   *   String to test.
   *
   * @return paragraph machine name
   */
  protected static function getParagraphId() {
    $database = \Drupal::database();
    $result = $database->select('config', 'cnfg')
      ->fields('cnfg', ['name'])
      ->condition('name', "%" . $database->escapeLike('paragraphs.paragraphs_type') . "%", 'LIKE')
      ->execute()
      ->fetchAll();

    $field_text = array();
    if (!empty($result)) {
      foreach ($result as $z_results_value) {
        $title_replace = str_replace('paragraphs.paragraphs_type.', '', $z_results_value->name);
        $field_paragraph_title[] = trim($title_replace);
      }
    }
    return (!empty($field_paragraph_title)) ? $field_paragraph_title : "";

  }

  /**
   * Is the argument a id?
   *
   * @param string $id
   *   String to test.
   *
   * @return paragraph parent id
   */
  protected static function getBricksParagraphParentId($id = '') {
    $query = \Drupal::database()->select('paragraphs_item_field_data', 'pifd');
    $query->fields('pifd', ['type']);
    $query->condition('pifd.parent_id', $id);
    $query->condition('pifd.type', 'title', '!=');
    $query->condition('pifd.type', 'cartridge_show_hide', '!=');
    $z_results = $query->execute()->fetchAll();

    $field_text = array();
    if (!empty($z_results)) {
      foreach ($z_results as $z_results_value) {
        $field_paragraph_parentid = $z_results_value->type;
      }
    }
    return (isset($field_paragraph_parentid)) ? $field_paragraph_parentid : "";
  }

  /**
   * Get new entity query for a content type.
   *
   * @param array $options
   *   - string $type (optional) content type to query on
   *   - boolean $published  true for published only, false for everything
   *   - array $conditions entity query conditions.
   *
   * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
   *   preset for the content type.
   */
  protected function getTidByName($name = NULL, $vid = NULL) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);

    return !empty($term) ? $term->id() : 0;
  }

}
