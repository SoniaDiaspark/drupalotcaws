<?php

namespace Drupal\otc_skyword_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
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
      'step' => $entityFieldManager->getFieldDefinitions('node', 'step'),
      'article' => $entityFieldManager->getFieldDefinitions('node', 'article'),
      'recipe' => $entityFieldManager->getFieldDefinitions('node', 'recipe'),
      'project' => $entityFieldManager->getFieldDefinitions('node', 'project'),
    ];

    $this->fs = $fs;
  }

  public function getLogger() {
    return $this->logger;
  }
  /**
   * Queue Import Jobs with drupal compatible data.
   */
  public function queueImportJobs() {
    try {
      $res = $this->httpClient->request('GET', $this->importUrl);
      $xml = $res->getBody();

      // sanitize $xml to strip out untidy bits
      $xml = (string) $xml;
      $xml = preg_replace('/\<\>/', '', $xml); // empty tag
      $xml = preg_replace('/\<script.*\<\/script\>/', '', $xml); // script tags

      $simplexml = Security::scan($xml);

      if ( $res->getStatusCode() !== 200 || ! ( $simplexml instanceof SimpleXMLElement) ) {
        throw new \Exception($res->getBody());
      }

      foreach ($this->mapImports($simplexml) as $type => $docs) {
        foreach ($docs as $doc) {
          print_r($doc);
          // $this->queueImportJob($type, $doc);
          $this->create($doc, $type);
        }
      }

    } catch (\Exception $e) {
      $this->logger->error('Error loading feed from skyword: @message', [
        '@message' => $e->getMessage()
      ]);

      $this->sendFailureMessages('parse', t('Error loading feed from skyword: @message', [
        '@message' => $e->getMessage()
      ]));

    }
  }

  public function sendFailureMessages($key, $message) {
    $users = \Drupal::entityQuery('user')
      ->condition('roles', 'administrator')
      ->execute();

    foreach ($users as $uid) {
      $user = User::load($uid);
      \Drupal::service('plugin.manager.mail')->mail('otc_skyword_import', $key, $user->mail->value, 'en', ['message' => $message]);
    }
  }

  protected function queueImportJob($type, $document) {
    $this->dbQueue->createItem([
      'type' => $type,
      'document' => $document,
    ]);
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
          // $docs['article'][] = $this->mappingService->get('article')->map($document);
          break;
        case 'Project':
        case 'Project-Lite':
          // $docs['project'][] = $this->mappingService->get('project')->map($document);
          break;
        case 'fun365recipe':
          $docs['recipe'][] = $this->mappingService->get('recipe')->map($document);
          break;
        default:
      }
    }

    return $docs;
  }

  public function create($document, $type) {
    // silently ignore existing skyword nodes
    if ( ! $document['field_skyword_id'] || $this->documentExists($document['field_skyword_id'])) {
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
      } elseif ( $this->isFileType($fieldName) ) {
        $return[$fieldName] = $this->prepareFiles($fieldName, $data, $type);
      // entity reference field field_step
      } elseif ( $fieldName === 'field_step' ) {
        $return[$fieldName] = $this->prepareStep($data);
      // Product skus
      } elseif ( $fieldName === 'field_products' || $fieldName === 'field_product_own' ) {
        $return[$fieldName] = $this->prepareProducts($data);
      // Contributor Full Name
      } elseif ( $fieldName === 'field_contributor' ) {
        $return[$fieldName] = $this->prepareContributor($data);
      } elseif ( $this->isLinkType($fieldName) ) {
        $return[$fieldName] = $data;
      }
    }

    return $return;
  }

  protected function prepareMultiLineText($data, $delimiter = "\n") {
    $return = [];
    foreach (explode($delimiter, $data) as $line) {
      if (trim($line)) {
        $return[] = ['value' => trim($line)];
      }
    }

    return $return;
  }

  protected function prepareFiles($fieldName, $data, $type) {
    $fieldSettings = $this->fieldConfig['instance'][$type][$fieldName]->getSettings();
    $uri = 'public://' . $fieldSettings['file_directory'];
    $baseDir = $this->prepareDirectory($uri);
    if ( ! $baseDir ) return false;

    $files = [];
    foreach ($data as $fileData) {
      $target = file_create_filename($fileData['name'], $baseDir);
      $baseName = basename($target);

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

  protected function prepareDirectory($uri) {
    $baseDir = $this->fs->realpath($uri);
    if ( ! $baseDir ) {
      $this->fs->mkdir($uri, NULL, true);
    }
    return $this->fs->realpath($uri);
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

  protected function prepareContributor($data) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_skyword_id', trim($data));
    $query->condition('type', 'contributor');

    foreach ( $query->execute() as $nid ) {
      return ['target_id' => $nid];
    }

    return [];
  }

  protected function prepareProducts($data) {
    $skus = array_map(function($sku){
      return trim($sku);
    }, explode(',', $data));

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

  protected function isFileType($fieldName) {
    return in_array($this->fieldConfig['storage'][$fieldName]->getType(), [
      'file',
      'image',
    ]);
  }

  protected function isComplexValue($fieldName) {
    $complex = [
      'field_contributor',
      'field_products',
      'field_product_own',
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
