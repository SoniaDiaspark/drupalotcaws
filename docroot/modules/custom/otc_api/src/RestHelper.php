<?php

namespace Drupal\otc_api;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
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
  public function __construct(
    QueryFactory $queryFactory,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->queryFactory = $queryFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get CacheMetaData for node list or specific result.
   * @return CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result = []) {
    $cacheMetaData = new CacheableMetadata;
    $cacheMetaData->setCacheContexts(['url']);

    if ( ! empty($result['nid']) ) {
      $cacheMetaData->setCacheTags(['node:' . $result['nid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['node_list']);

    return $cacheMetaData;
  }

  /**
   * Validate content type string.
   * @param  string $contentType the content type
   * @return boolean
   */
  public function contentTypePermitted($contentType = NULL) {
    $allowedContentTypes = [
      'article',
      'contributor',
      'download',
      'featured_content',
      'look',
      'product',
      'project',
      'promo',
      'recipe',
      'step',
    ];

    return
      $contentType
      && in_array($contentType, $allowedContentTypes)
      && in_array($contentType, array_keys(NodeType::loadMultiple()));
  }

  public function fetchOne($contentType, $uuid = '') {
    if ( ! self::contentTypePermitted($contentType) ) {
      return [];
    }

    $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $uuid]);
    if ( ! $result ) {
      return [];
    }

    $node = current($result);
    if ( ! self::contentTypePermitted($node->getType()) ) {
      return [];
    }

    return $this->processNode($node);
  }

  /**
   * Fetch a list of nodes from a content type, in clean format for REST.
   * @param  string  $contentType the content type
   * @param  integer $page        page number
   * @param  boolean $published   true for published, false for all.
   * @return array of nodes.
   */
  public function fetchAll($contentType, $page = 0, $published = true) {
    if ( ! self::contentTypePermitted($contentType) ) {
      return [];
    }

    $limit = 10;
    $response = [
      'limit' => $limit,
      'page' => $page,
      'published' => $published
    ];

    $response['count'] = intval($this->newQuery($contentType, $published)->count()->execute());

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
  protected function processNode (Node $node, $referenceDepth = 0) {
    $view = [];

    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());

    foreach ( $fieldDefinitions as $name => $fieldDefinition ) {
      if ( ! $fieldDefinition->getType() ) continue;

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      // if ( ! $supported ) {
        // echo $name . ": " . $fieldDefinition->getType() . "\n";
      // }
      $notIgnored = ! in_array($name, self::ignoredFieldNames());
      $skippedReference = $referenceDepth > 2 && $fieldDefinition->getType() === 'entity_reference';

      if ( $supported && $notIgnored && ! $skippedReference ) {
        // no value
        if ( ! $node->$name ) {
          $view[$name] = '';
          continue;
        }

        $view[$name] = $this->processField($node->{$name}, [
            'fieldDefinition' => $fieldDefinition,
            'referenceDepth' => $referenceDepth,
        ]);
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
  protected function getFieldValue(FieldItemListInterface $field) {
    return $field->value;
  }

  /**
   * Get simple integer value.
   * @param  FieldItemListInterface   $field field item list
   * @return int
   */
  protected function getIntFieldValue(FieldItemListInterface $field) {
    return intval($field->value);
  }

  /**
   * Get simple float value.
   * @param  FieldItemListInterface   $field field item list
   * @return float
   */
  protected function getFloatFieldValue(FieldItemListInterface $field) {
    return floatval($field->value);
  }

  /**
   * Get link value.
   * @param  FieldItemListInterface   $field field item list
   * @return string simple string value
   */
  protected function getLinkFieldValue(FieldItemListInterface $field, $options = []) {
    $values = $field->getValue();
    if ( $values ) {
      $links = [];
      $storage = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');
      if ( $storage[$field->getName()]->isMultiple() ) {
        foreach ( $values as $linkData ) {
          $links[] = [
            'url' => $linkData['uri'],
            'title' => $linkData['title'],
          ];
        }
        return $links;
      } else {
        $linkData = current($values);
        return [
          'url' => $linkData['uri'],
          'title' => $linkData['title'],
        ];
      }
    }

    return NULL;
  }

  /**
   * Get simple date value.
   * @param  FieldItemListInterface   $field field item list
   * @return string simple string value
   */
  protected function getDateFieldValue(FieldItemListInterface $field) {
    return \Drupal::service('date.formatter')->format($field->value, 'html_datetime');
  }

  /**
   * Get true/false value from boolean
   * @param  FieldItemListInterface   $field           the field item list
   * @return boolean
   */
  protected function getFieldBoolean(FieldItemListInterface $field) {
    return $field->value === "1";
  }

  /**
   * Get one or more entity reference object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - int referenceDepth to prevent infinite recursion
   * @return array of arrays representing referenced node
   */
  protected function getReferenceFieldValue(FieldItemListInterface $field, $options = []) {
    $referenceType = $options['fieldDefinition']->getSettings()['target_type'];

    switch ( $referenceType ) {
      case 'node':
        return $this->getReferenceNode($field, $options);
        break;
      case 'node_type':
        return $this->getNodeType($field);
        break;
      case 'taxonomy_term':
      default:
        return NULL;
    }
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
   * Get one or more entity reference object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *   - int referenceDepth to prevent infinite recursion
   * @return array of arrays representing referenced node
   */
   protected function getReferenceNode(FieldItemListInterface $field, $options = []) {
     $storage = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');
     $referenceData = $field->getValue();

     $nodes = [];
     if ( $referenceData ) {
       if ( $storage[$field->getName()]->isMultiple() ) {
         foreach ( $referenceData as $index => $target ) {
           $node = Node::load($target['target_id']);
           $nodes[] = $this->processNode($node, $options['referenceDepth'] + 1);
         }
         return $nodes;
       } else {
         $node = Node::load(current($referenceData)['target_id']);
         return $this->processNode($node, $options['referenceDepth'] + 1);
       }
     }

     return $nodes;
   }

   /**
    * Get one or more file object arrays.
    * @param  FieldItemListInterface   $field the field items
    * @param  array options
    *   - includes FieldDefinitionInterface $fieldDefinition field instance info
    *     used to get image resolution constraints.
    * @return array of arrays of file urls.
    */
   protected function getFileFieldValue(FieldItemListInterface $field, $options = []) {
     $storage = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');
     $fileData = $field->getValue();

     if ( $fileData ) {
       if ( $storage[$field->getName()]->isMultiple() ) {
         $files = [];
         foreach ( $fileData as $target ) {
           $files[] = File::load($target['target_id'])->url();
         }
         return $files;
       }

       // single
       return File::load(current($fileData)['target_id'])->url();
     }

     return NULL;
   }

  /**
   * Get one or more image object arrays.
   * @param  FieldItemListInterface   $field the field items
   * @param  array options
   *   - includes FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get image resolution constraints.
   * @return array of arrays of image urls.
   */
  protected function getImageFieldValue(FieldItemListInterface $field, $options = []) {
    $storage = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');
    $imageData = $field->getValue();
    $resolution = $options['fieldDefinition']->getSettings()['max_resolution'];
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

    $aspectRatio = number_format(round($matches[1] / $matches[2], 2), 2);
    if ( ! in_array($aspectRatio, array_keys($resolutions)) ) {
      return [];
    }

    return $resolutions[$aspectRatio];
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
      'text_long' => 'getFieldValue',
      'created' => 'getDateFieldValue',
      'changed' => 'getDateFieldValue',
      'path' => 'getFieldValue',
      'float' => 'getFloatFieldValue',
      'boolean' => 'getFieldBoolean',
      'uuid' => 'getFieldValue',
      'integer' => 'getIntFieldValue',
      'image' => 'getImageFieldValue',
      'file' => 'getFileFieldValue',
      'entity_reference' => 'getReferenceFieldValue',
      'link' => 'getLinkFieldValue',
    ];
  }

  /**
   * Ignored fields used processing nodes.
   * @return array list of ignored field names.
   */
  protected static function ignoredFieldNames() {
    return [
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
      'path',
      'publish_on',
      'unpublish_on',
    ];
  }
}
