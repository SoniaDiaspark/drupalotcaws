<?php

namespace Drupal\otc_legacy_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;
use GuzzleHttp\Client;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

class ImportService {
  /**
   * @var Drupal\Core\Queue\DatabaseQueue
   */
  private $dbQueue;

  /**
   * @var GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Storage field config and field instance configuration
   * @var array
   */
  private $fieldConfig;

  /**
   * @var Drupal\Core\File\FileSystem
   */
  private $fs;

  /**
   * @var Drupal\otc_legacy_import\WordPressDatabaseService
   */
  private $wordPressDatabaseService;

  /**
   * @var Drupal\otc_legacy_import\MappingServiceInterface
   */
  private $mappingService;

  /**
   * @var Drupal\otc_legacy_import\ImageUrlResizerService
   */
  private $imageUrlResizerService;

  /**
   * @var Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  private $streamWrapperManager;

  public function __construct(
    QueueDatabaseFactory $queueFactory,
    Client $httpClient,
    LoggerChannelFactoryInterface $logFactory,
    EntityFieldManagerInterface $entityFieldManager,
    FileSystem $fs,
    WordPressDatabaseService $wordPressDatabaseService,
    MappingServiceInterface $mappingService,
    ImageUrlResizerService $imageUrlResizerService,
    StreamWrapperManager $streamWrapperManager
  ) {

    $this->dbQueue = $queueFactory->get('otc_legacy_import');
    $this->logger = $logFactory->get('otc_legacy_import');
    $this->httpClient = $httpClient;
    $this->mappingService = $mappingService;

    $this->fieldConfig['storage'] = $entityFieldManager->getFieldStorageDefinitions('node');
    $this->fieldConfig['instance'] = [
      'contributor' => $entityFieldManager->getFieldDefinitions('node', 'contributor'),
      'article' => $entityFieldManager->getFieldDefinitions('node', 'article'),
      'recipe' => $entityFieldManager->getFieldDefinitions('node', 'recipe'),
      'project' => $entityFieldManager->getFieldDefinitions('node', 'project'),
    ];

    $this->fs = $fs;
    $this->wordPressDatabaseService = $wordPressDatabaseService;
    $this->mappingService = $mappingService;
    $this->imageUrlResizerService = $imageUrlResizerService;
    $this->streamWrapperManager = $streamWrapperManager;
  }

  public function getLogger() {
    return $this->logger;
  }

  public function queueImportJobs($datestring = '') {
    try {
      $postsByType = $this->wordPressDatabaseService->getPosts($dateString, -1);
      foreach ($postsByType as $type => $docs) {
        $docs = $this->mappingService->get($type)->map($docs);
        foreach ( $docs as $doc ) {
          $this->dbQueue->createItem([
            'type' => $type,
            'document' => $doc,
          ]);
        }
      }

    } catch (Exception $e) {
      $this->getLogger()->error("Error queueing legacy import. Message: @message", [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  public function queueContributorImportJobs($datestring = '') {
    try {
      $users = $this->wordPressDatabaseService->getUsers($dateString, -1);
      $users = $this->mappingService->get('contributor')->map($users);

      foreach ( $users as $user ) {
        $this->dbQueue->createItem([
          'type' => 'contributor',
          'document' => $user,
        ]);
      }

    } catch (Exception $e) {
      $this->getLogger()->error("Error queueing contributor import. Message: @message", [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  public function create($document, $type) {
    // silently ignore existing skyword nodes
    if ( ! $document['field_wordpress_id'] || $this->documentExists($document['field_wordpress_id'], $type)) {
      return false;
    }

    echo "Creating $type with WordPress id {$document['field_wordpress_id']}\n";
    $prepared = $this->prepare($document, $type);

    return Node::create($prepared)->save();
  }

  protected function documentExists($wordPressId, $type) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_wordpress_id', $wordPressId);
    $query->condition('type', $type);
    $documents = $query->execute();

    return ! empty($documents);
  }

  protected function prepare($document, $type) {
    $return = [
      'type' => $document['type'],
      'title' => $document['title'],
    ];

    foreach ( $document as $fieldName => $data ) {
      if ( ! $data ) continue;

      $simple =
        $this->isValidField($fieldName, $type)
        && $this->isSimpleFieldType($fieldName)
        && ! $this->isComplexValue($fieldName);

      // Simple text fields with no processing
      if ( $simple ) {
        $return[$fieldName] = [];
        if ( $this->isMultiValueField($fieldName) ) {
          foreach((array) $data as $item) {
            $return[$fieldName][] = ['value' => $item];
          }
        } else {
          $return[$fieldName]['value'] = $data;
        }
      } elseif ( $fieldName === 'field_step' ) {
        $return[$fieldName] = $this->prepareStep($data);
      } elseif ( $fieldName === 'images' ) {

      // File fields
      } elseif ( $this->isImageType($fieldName) ) {
        $return[$fieldName] = $this->prepareImage($fieldName, $data, $type);
      // Product skus
      } elseif ( $fieldName === 'field_products' ) {
        $return[$fieldName] = $this->prepareProducts($data);
      // Contributor Full Name
      } elseif ( $fieldName === 'field_contributor' ) {
        $return[$fieldName] = $this->prepareContributor($data);
      } elseif ( $this->isLinkType($fieldName) ) {
        $return[$fieldName] = $data;
      } elseif ( $fieldName === 'field_legacy_content' ) {
        $return['field_legacy_content'] = $this->replaceImages($document);
      }
    }

    return $return;
  }

  protected function prepareStep($steps) {
    $return = [];

    foreach ( $steps as $stepData) {
      if ( empty($stepData) ) continue;

      $step = Node::create($this->prepare($stepData, 'step'));
      $step->save();
      $return[] = ['target_id' => $step->nid->value];
    }

    return $return;
  }

  protected function prepareImage($fieldName, $data, $type) {
    if ( ! is_object($this->fieldConfig['instance'][$type][$fieldName]) ) {
      return [];
    }

    $imageSettings = $this->fieldConfig['instance'][$type][$fieldName]->getSettings();

    $dimensions = [];
    list($dimensions['width'], $dimensions['height']) = explode('x', $imageSettings['max_resolution']);
    if ( ! ($dimensions['width'] && $dimensions['height'] && $data ) ) return [];
    $dimensions['width'] = (int) $dimensions['width'];
    $dimensions['height'] = (int) $dimensions['height'];

    $sourceUrl = $data;
    $baseFileName = basename($sourceUrl);
    $directoryUri = "public://" . $imageSettings['file_directory'];
    $baseDir = $this->prepareDirectory($directoryUri);
    $targetFilePath = file_create_filename($baseFileName, $baseDir);

    if ( $this->imageUrlResizerService->resize($sourceUrl, $targetFilePath, $dimensions) ) {
      $file = File::create([
        'uri' => $directoryUri . '/' . basename($targetFilePath),
        'uid' => \Drupal::currentUser()->uid,
        'status' => FILE_STATUS_PERMANENT,
      ]);
      $file->save();

      return ['target_id' => $file->fid->value];

    }

    return [];
  }

  protected function replaceImages($document) {
    $legacyContent = $document['field_legacy_content'];
    $return = [
      'value' => &$legacyContent,
    ];

    $queuedImageDownloads = [];
    foreach ($document['images'] as &$image) {
      $sourceUrl = $image['sourceUrl'];
      $destinationUri = $image['destinationUri'];
      $baseFileName = basename($destinationUri);
      $directoryUri =  dirname($destinationUri);

      $baseDir = $this->prepareDirectory($directoryUri);
      $targetFilePath = file_create_filename($baseFileName, $baseDir);
      $targetUrl = $this->streamWrapperManager->getViaUri($directoryUri . '/' . basename($targetFilePath))->getExternalUrl();

      $legacyContent = str_replace($sourceUrl, $targetUrl, $legacyContent);
      $queuedImageDownloads[] = ['sourceUrl' => $sourceUrl, 'targetFilePath' => $targetFilePath];
    }

    foreach ( $queuedImageDownloads as $download ) {
      $this->dbQueue->createItem([
        'type' => 'image',
        'document' => $download,
      ]);
    }

    return $return;
  }

  public function downloadImage($sourceUrl, $targetFilePath) {
    echo "Downloading file.\n Source $sourceUrl\nTarget: $targetFilePath\n";
    $directory = dirname($targetFilePath);
    if ( ! file_exists($directory) ) {
      echo "Creating directory $directory\n";
      mkdir(dirname($targetFilePath), 0777, true);
    }
    $this->httpClient->request('GET', $sourceUrl, ['sink' => $targetFilePath, 'connect_timeout' => 100]);
  }

  protected function prepareDirectory($uri) {
    $baseDir = $this->fs->realpath($uri);
    if ( ! $baseDir ) {
      $this->fs->mkdir($uri, NULL, true);
    }
    return $this->fs->realpath($uri);
  }

  protected function prepareProducts($skus) {
    $return = [];
    foreach ( $this->lookupProductsBySkus($skus) as $nid ) {
      $return[] = ['target_id' => $nid];
    }

    return $return;
  }

  protected function lookupProductsBySkus($skus = []) {
    if ( empty($skus) ) return [];

    $query = \Drupal::entityQuery('node');
    $or  = $query->orConditionGroup();
    foreach( $skus as $sku ) {
      if ( $sku ) {
        $or->condition('field_sku', $sku);
      }
    }
    $query->condition($or);
    $products = $query->execute();

    return $products;
  }

  protected function prepareContributor($id) {
    $return = [];
    foreach ( $this->lookupContributorByWPId($id) as $nid ) {
      $return[] = ['target_id' => $nid];
    }

    return $return;
  }

  protected function lookupContributorByWPId($id) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_wordpress_id', $id);
    $query->condition('type', 'contributor');
    $contributor = $query->execute();

    return $contributor;
  }


  protected function isSimpleFieldType($fieldName) {
    return in_array($this->fieldConfig['storage'][$fieldName]->getType(), [
      'text',
      'text_long',
      'string',
      'string_long',
      'integer',
      'float',
      'boolean',
    ]);
  }

  protected function isLinkType($fieldName) {
    return 'link' === $this->fieldConfig['storage'][$fieldName]->getType();
  }

  protected function isImageType($fieldName) {
    return in_array($this->fieldConfig['storage'][$fieldName]->getType(), [
      'image',
    ]);
  }

  protected function isComplexValue($fieldName) {
    $complex = [
      'field_legacy_content',
    ];
    return in_array($fieldName, $complex);
  }

  protected function isValidField($fieldName, $type) {
    return isset($this->fieldConfig['instance'][$type][$fieldName]);
  }

  protected function isMultiValueField($fieldName) {
    return method_exists($this->fieldConfig['storage'][$fieldName], 'isMultiple')
      && $this->fieldConfig['storage'][$fieldName]->isMultiple()
      || $this->fieldConfig['storage'][$fieldName]->getCardinality() > 1;
  }
}
