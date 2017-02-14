<?php

namespace Drupal\otc_skyword_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;
use GuzzleHttp\Client;
use ZendXml\Security;
use SimpleXMLElement;

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
  * base url for import
  * @var string
  */
  private $importUrl;


  /**
   * @var Drupal\otc_skyword_import\MappingServiceInterface
   */
  private $mappingService;

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
   * Constructor.
   */
  public function __construct(
    QueueDatabaseFactory $queueFactory,
    Client $httpClient,
    LoggerChannelFactoryInterface $logFactory,
    ConfigFactory $configFactory,
    MappingServiceInterface $mappingService,
    EntityFieldManagerInterface $entityFieldManager,
    FileSystem $fs
  ) {
    $this->dbQueue = $queueFactory->get('otc_skyword_import');
    $this->logger = $logFactory->get('otc_skyword_import');
    $this->httpClient = $httpClient;
    $config = $configFactory->get('otc_skyword_import.config');
    $this->importUrl = $config->get('url');
    $this->mappingService = $mappingService;

    $this->fieldConfig['storage'] = $entityFieldManager->getFieldStorageDefinitions('node');
    $this->fieldConfig['instance'] = [
      'article' => $entityFieldManager->getFieldDefinitions('node', 'article'),
    ];

    $this->fs = $fs;
  }

  /**
   * Queue Import Jobs with drupal compatible data.
   */
  public function queueImportJobs() {
    try {
      $res = $this->httpClient->request('GET', $this->importUrl);
      $xml = $res->getBody();
      $simplexml = Security::scan((string) $xml);

      if ( $res->getStatusCode() !== 200 || ! ( $simplexml instanceof SimpleXMLElement) ) {
        throw new \Exception($res->getBody());
      }

      foreach ($this->mapImports($simplexml) as $type => $docs) {
        foreach ($docs as $doc) {

          // @TODO implement this
          // $this->queueImportJob($type, $doc);

          // @TODO do this in worker
          $this->create($doc, $type);
        }
      }

    } catch (\Exception $e) {
      $this->logger->error('Error loading feed from skyword: @message', [
        '@message' => $e->getMessage()
      ]);
    }
  }

  protected function queueImportJob($type, $document) {
    // @TODO queue document for import
    echo "type: $type\n";
    print_r($document);
  }

  /**
   * Map a skyword feed xml document to importable data.
   * @param  SimpleXMLElement $simplexml the skyword document
   * @return array importable data
   */
  protected function mapImports(SimpleXMLElement $simplexml) {
    $docs = [];
    foreach ($simplexml as $type => $document) {
      switch ($type) {
        case 'article':
        case 'article-list':
          $docs['article'][] = $this->mappingService->get('article')->map($document);
        break;
        default:
      }
    }

    return $docs;
  }

  public function create($document, $type) {
    if ( ! $document['field_skyword_id'] || $this->documentExists($document['field_skyword_id'])) {
      echo "No skyword id, or document with id ({$document['field_skyword_id']}) exists\n";
      return false;
    }

    return Node::create($this->prepare($document, $type))->save();
  }

  protected function documentExists($skywordId) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_skyword_id', $skywordId);
    $documents = $query->execute();

    return ! empty($documents);
  }

  protected function prepare($document, $type) {
    $return = [
      'type' => $type,
    ];

    foreach ( $document as $fieldName => $data ) {
      if ( ! $data ) continue;

      $simple =
        $this->isValidField($fieldName, $type)
        && $this->isSimpleFieldType($fieldName)
        && ! $this->isComplexValue($fieldName, $type);

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
      } elseif ( $this->isFileType($fieldName) ) {
        $return[$fieldName] = $this->prepareFiles($fieldName, $data, $type);
      }
    }

    return $return;
  }

  protected function prepareFiles($fieldName, $data, $type) {
    $fieldSettings = $this->fieldConfig['instance'][$type][$fieldName]->getSettings();
    $uri = 'public://' . $fieldSettings['file_directory'];
    $baseDir = $this->prepareDirectory($uri);
    if ( ! $baseDir ) return false;

    foreach ($data as $fileData) {
      $target = file_create_filename($fileData['name'], $baseDir);
      $baseName = basename($target);

      $files = [];
      if ( $this->downloadFile($fileData['url'], $target) ) {
        $file = File::create([
          'uri' => $uri . '/' . $baseName,
          'uid' => \Drupal::currentUser()->uid,
          'status' => FILE_STATUS_PERMANENT,
        ]);
        $file->save();

        $files[] = ['target_id' => $file->fid->value];
      }
    }

    return $files;
  }

  protected function downloadFile($url, $target) {
    try {
      $response = $this->httpClient->request('GET', $url, ['sink' => $target]);
      return $response->getStatusCode() === 200;
    } catch (Exception $e) {
      $this->logger->error('Error getting file @url from skyword: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);

      return false;
    }
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
    ]);
  }

  protected function isFileType($fieldName) {
    return in_array($this->fieldConfig['storage'][$fieldName]->getType(), [
      'file',
      'image',
    ]);
  }

  protected function isComplexValue($fieldName, $type) {
    $complex = [
      'article' => [
        'field_contributor',
        'field_products',
        'field_items_needed',
      ],
    ];

    return in_array($fieldName, $complex[$type]);
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
