<?php

namespace Drupal\otc_api;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\image\Entity\ImageStyle;

class NodeHelper {
  private $queryFactory;

  public function __construct(QueryFactory $queryFactory) {
    $this->queryFactory = $queryFactory;
  }

  public function contentTypeExists($contentType = NULL) {
    return $contentType && in_array($contentType, array_keys(NodeType::loadMultiple()));
  }

  public function newQuery($contentType, $published = true) {
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

  protected function processNodes ($nodes) {
    $results = [];
    foreach ( $nodes as $node ) {
      $results[] = $this->processNode($node);
    }

    return $results;
  }

  protected function processNode (Node $node) {
    $view = [];

    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());

    foreach ( $fieldDefinitions as $name => $fieldDefinition ) {
      if ( ! $fieldDefinition->getType() ) continue;

      $supported = in_array($fieldDefinition->getType(), array_keys($this->supportedFieldTypes()));
      $notIgnored = ! in_array($name, $this->ignoredFieldNames());
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

  protected function processField(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    $method = $this->supportedFieldTypes()[$fieldDefinition->getType()];
    return $this->{$method}($field, $fieldDefinition);
  }

  protected function getFieldValue(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    return $field->value;
  }

  protected function getFieldBoolean(FieldItemListInterface $field, FieldDefinitionInterface $fieldDefinition) {
    return $field->value === "1";
  }

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

  protected function imageStyles($resolution) {
    $resolutions = $this->resolutions();

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

  protected function resolutions() {
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

  protected function supportedFieldTypes() {
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

  protected function ignoredFieldNames() {
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
