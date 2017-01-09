<?php

namespace Drupal\otc_api;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\image\Entity\ImageStyle;

class RestHelper implements RestHelperInterface {
  /**
   * For creating entity queries.
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  private $queryFactory;

  /**
   * @param QueryFactory $queryFactory entity query factory
   */
  public function __construct(QueryFactory $queryFactory) {
    $this->queryFactory = $queryFactory;
  }

  /**
   * Validate content type string.
   * @param  string $contentType the content type
   * @return boolean
   */
  public function contentTypeExists($contentType = NULL) {
    return $contentType && in_array($contentType, array_keys(NodeType::loadMultiple()));
  }

  /**
   * Fetch a list of nodes from a content type, in clean format for REST.
   * @param  string  $contentType the content type
   * @param  integer $page        page number
   * @param  boolean $published   true for published, false for all.
   * @return array of nodes.
   */
  public function fetchAll($contentType, $page = 0, $published = true) {
    if ( ! self::contentTypeExists($contentType) ) {
      return [];
    }

    $limit = 10;
    $response = [
      'limit' => $limit,
      'page' => $page,
      'published' => $published
    ];

    $response['count'] = $this->newQuery($contentType, $published)->count()->execute();

    $entity_ids = $this->newQuery($contentType, $published)
      ->range($page * $limit, $limit)
      ->execute();

    if ( ! $entity_ids ) {
      $response['results'] = [];
      return $response;
    }

    $response['results'] = $this->processNodes(\Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($entity_ids));

    print_r($response);
    return $response;
  }

  /**
   * Get new entity query for a content type.
   * @param  string  $contentType the content type
   * @param  boolean $published  true for published only, false for everything
   * @return Drupal\Core\Entity\Query\QueryInterface EntityQuery, with some conditions
   *  preset for the content type.
   */
  protected function newQuery($contentType, $published = true) {
    $query = \Drupal::entityQuery('node');
    if (! $published ) {
      $group = $query->orConditionGroup()
        ->condition('status', 1)
        ->condition('status', 0);
      $query->condition($group);
    } else {
      $query->condition('status', 1);
    }
    $query->condition('type', $contentType);

    return $query;
  }

  /**
   * Process list of nodes.
   * @param  array $nodes array of Node objects
   * @return array of arrays representing a node in clean REST format
   */
  protected function processNodes ($nodes) {
    $results = [];
    foreach ( $nodes as $node ) {
      $results[] = $this->processNode($node);
    }

    return $results;
  }

  /**
   * Process all fields in a node.
   * @param  Node   $node the node object.
   * @return array node information in clean format for REST
   */
  protected function processNode (Node $node) {
    $view = [];

    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());

    foreach ( $fieldDefinitions as $name => $fieldDefinition ) {
      if ( ! $fieldDefinition->getType() ) continue;

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $notIgnored = ! in_array($name, self::ignoredFieldNames());
      if ( $supported && $notIgnored ) {
        // no value
        if ( ! $node->$name ) {
          $view[$name] = '';
          continue;
        }

        $view[$name] = $this->processField($node->{$name}, $fieldDefinition);
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
   * @param  FieldDefinitionInterface $fieldDefinition the field instance definition
   * @return mixed "formatted" value of the field
   */
  protected function processField(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    $method = self::supportedFieldTypes()[$fieldDefinition->getType()];
    return $this->{$method}($field, $fieldDefinition);
  }

  /**
   * Get simple value.
   * @param  FieldItemListInterface   $field field item list
   * @param  FieldDefinitionInterface $fieldDefinition field instance info
   * @return string simple string value
   */
  protected function getFieldValue(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    return $field->value;
  }

  /**
   * Get true/false value from boolean
   * @param  FieldItemListInterface   $field           the field item list
   * @param  FieldDefinitionInterface $fieldDefinition field instance configuration
   * @return boolean
   */
  protected function getFieldBoolean(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    return $field->value === "1";
  }

  /**
   * Get one or more image object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  FieldDefinitionInterface $fieldDefinition field instance info
   *   used to get image resolution constraints.
   * @return array or arrays of image urls.
   */
  protected function getImageFieldValue(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    $storage = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');
    $imageData = $field->getValue();
    $resolution = $fieldDefinition->getSettings()['max_resolution'];
    $resolutions = $this->imageStyles($resolution);

    if ( $imageData ) {
      if ( $storage[$field->getName()]->isMultiple() ) {
        $images = [];
        foreach ( $imageData as $image ) {
          $images[] = $this->processImage($image['target_id'], $resolutions);
        }
        return $images;
      }

      // single
      return $this->processImage(current($imageData)['target_id'], $resolutions);
    }

    return [];
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

    $aspectRatio = round($matches[1] / $matches[2], 2);

    if ( ! in_array($aspectRatio, array_keys($resolutions)) ) {
      return [];
    }

    return $resolutions["$aspectRatio"];
  }

  /**
   * Get image styles for each aspect ratio.
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
      'float' => 'getFieldValue',
      'boolean' => 'getFieldBoolean',
      'uuid' => 'getFieldValue',
      'integer' => 'getFieldValue',
      'image' => 'getImageFieldValue',
    ];
  }

  /**
   * Ignored fields used processing nodes.
   * @return array list of ignored field names.
   */
  protected static function ignoredFieldNames() {
    return [
      'nid',
      'vid',
      'title',
      'langcode',
      'uid',
      'created',
      'changed',
      'promote',
      'sticky',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'default_langcode',
      'path',
      'publish_on',
      'unpublish_on',
    ];
  }
}
