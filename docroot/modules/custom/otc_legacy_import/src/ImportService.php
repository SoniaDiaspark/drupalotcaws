<?php

namespace Drupal\otc_legacy_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;
use GuzzleHttp\Client;

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

  public function __construct(
    QueueDatabaseFactory $queueFactory,
    Client $httpClient,
    LoggerChannelFactoryInterface $logFactory,
    EntityFieldManagerInterface $entityFieldManager,
    FileSystem $fs,
    WordPressDatabaseService $wordPressDatabaseService,
    MappingServiceInterface $mappingService,
    ImageUrlResizerService $imageUrlResizerService
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
  }

  public function getLogger() {
    return $this->logger;
  }

  public function queueContributorImportJobs($datestring = '') {
    try {
      $users = $this->wordPressDatabaseService->getUsers($dateString, -1);
      $users = $this->mappingService->get('contributor')->map($users);

      // @TODO convert to job, handle with worker
      foreach ( $users as $user ) {
        $this->create($user, 'contributor');
      }

    } catch (Exception $e) {
      print_r($e);
      echo $e->getMessage() . "\n";
    }

  }

  public function create($document, $type) {
    // silently ignore existing skyword nodes
    if ( ! $document['field_wordpress_id'] || $this->documentExists($document['field_wordpress_id'])) {
      return false;
    }

    $prepared = $this->prepare($document, $type);

    return Node::create($prepared)->save();
  }

  protected function documentExists($wordPressId) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_wordpress_id', $wordPressId);
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

      // File fields
      } elseif ( $this->isImageType($fieldName) ) {
        $return[$fieldName] = $this->prepareImage($fieldName, $data, $type);
      // Product skus
      } elseif ( $fieldName === 'field_products' ) {
        $return[$fieldName] = $this->prepareProducts($data);
      // Contributor Full Name
      // } elseif ( $fieldName === 'field_contributor' ) {
        // $return[$fieldName] = $this->prepareContributor($data);
      } elseif ( $this->isLinkType($fieldName) ) {
        $return[$fieldName] = $data;
      }
    }

    return $return;
  }

  protected function prepareImage($fieldName, $data, $type) {
    $imageSettings = $this->fieldConfig['instance'][$type][$fieldName]->getSettings();

    $dimensions = [];
    list($dimensions['width'], $dimensions['height']) = explode('x', $imageSettings['max_resolution']);
    if ( ! ($dimensions['width'] && $dimensions['height'] && $data ) ) return [];
    $dimensions['width'] = (int) $dimensions['width'];
    $dimensions['height'] = (int) $dimensions['height'];

    $url = $data;
    $baseFileName = basename($url);
    $directoryUri = "public://" . $imageSettings['file_directory'];
    $baseDir = $this->prepareDirectory($directoryUri);
    $target = file_create_filename($baseFileName, $baseDir);

    $this->imageUrlResizerService->reset();
    $this->imageUrlResizerService->setSourceUrl($url);
    $this->imageUrlResizerService->setOutcome($target, $dimensions);
    if ( $this->imageUrlResizerService->execute() ) {
      $file = File::create([
        'uri' => $directoryUri . '/' . basename($target),
        'uid' => \Drupal::currentUser()->uid,
        'status' => FILE_STATUS_PERMANENT,
      ]);
      $file->save();

      return ['target_id' => $file->fid->value];

    }

    return [];
  }

  protected function prepareDirectory($uri) {
    $baseDir = $this->fs->realpath($uri);
    if ( ! $baseDir ) {
      $this->fs->mkdir($uri, NULL, true);
    }
    return $this->fs->realpath($uri);
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
    $complex = [];
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
