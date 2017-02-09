<?php

namespace Drupal\otc_skyword_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\node\Entity\Node;
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
   * Constructor.
   */
  public function __construct(
    QueueDatabaseFactory $queueFactory,
    Client $httpClient,
    LoggerChannelFactoryInterface $logFactory,
    ConfigFactory $configFactory,
    MappingServiceInterface $mappingService
  ) {
    $this->dbQueue = $queueFactory->get('otc_skyword_import');
    $this->logger = $logFactory->get('otc_skyword_import');
    $this->httpClient = $httpClient;
    $config = $configFactory->get('otc_skyword_import.config');
    $this->importUrl = $config->get('url');
    $this->mappingService = $mappingService;
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

      foreach ($this->map($simplexml) as $type => $docs) {
        foreach ($docs as $doc) {
          $this->queueImportJob($type, $doc);
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
  protected function map(SimpleXMLElement $simplexml) {
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

}
