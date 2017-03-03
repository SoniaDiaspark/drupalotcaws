<?php

namespace Drupal\otc_product_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;

/**
 * Class ProductImportService.
 *
 * @package Drupal\otc_product_import
 */
class ProductImportService implements ProductImportServiceInterface {

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * @var Drupal\Core\Queue\DatabaseQueue
   */
  private $dbQueue;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var string
   */
  private $filename;

  /**
   * @var resource
   */
  private $sourceFileHandle;

  /**
   * Constructor.
   */
  public function __construct(QueryFactory $queryFactory, LoggerChannelFactoryInterface $logFactory, QueueDatabaseFactory $queueFactory) {
    $this->queryFactory = $queryFactory;
    $this->logger = $logFactory->get('otc_product_import');
    $this->dbQueue = $queueFactory->get('otc_product_import');

    $this->filename = drupal_realpath(\Drupal::config('otc_product_import.config')->get('products'));
  }

  public function getLogger() {
    return $this->logger;
  }

  /**
   * Get a node entity query object to check products against.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The node query object
   */
  private function query() {
    return $this->queryFactory->get('node');
  }

  /**
   * Open the source file.
   *
   * @return boolean
   */
  private function open() {
    $this->sourceFileHandle = fopen($this->filename, "r");
    if ( ! $this->sourceFileHandle || fgets($this->sourceFileHandle) === false ) {
      $this->logger->error("Unable to open or read from @filename", ['@filename' => $this->filename]);
      return false;
    }

    return true;
  }

  /**
   * Queue the import items
   */
  public function batchImport() {
    if ( ! $this->open() ) return;

    $lines = [];
    while( ($line = fgets($this->sourceFileHandle)) !== false ) {
      $data = [];
      list(
        $data['field_sku'],
        $data['title'],
        $data['field_quantity_description'],
        $data['field_price'],
        $data['field_sale_price'],
        $data['field_image_url_product_thumb_1x'],
        $data['field_image_url_product_thumb_2x'],
        $data['field_image_url_product_tile_1x'],
        $data['field_image_url_product_tile_2x']
      ) = explode('|', $line);

      $this->dbQueue->createItem([$data]);
    }

    fclose($this->sourceFileHandle);
  }

  /**
   * Check to see if a product node exists for this specified sku
   * @param  string $sku
   * @return array
   *   list of matching product, by nid
   */
  public function product_exists($sku) {
    return $this->query()->condition('field_sku', $sku, '=')->execute();
  }

  /**
   * Create a new product node.
   * @param  array $data the line of data from the import filename
   *
   * @return \Drupal\node\Entity\Node
   *   the new product node
   */
  public function create($data) {
    $this->getLogger()->notice("Creating product @sku", ["@sku" => $data['field_sku']]);
    return Node::create($this->prepare($data))->save();
  }

  /**
   * Update a product node, if it has changed
   * @param  array $nid array containing the node id
   * @param  array $data the product data
   *
   * @return mixed boolean false or \Drupal\node\Entity\Node
   *   the updated product node, if any change was necessary
   */
  public function update($nid, $data) {
    $node = false;
    $data['field_checksum'] = $this->checksum($data);

    if ( $this->is_updated($data) ) {
      $this->getLogger()->notice("Updating product sku @sku with node id @nid", [
        '@sku' => $data['field_sku'],
        '@nid' => $nid,
      ]);

      $node = Node::load($nid);

      foreach( $data as $key => $value ) {
        if ( $key === 'title' ) {
          $node->title = $value;
          $key = 'field_display_title';
        }

        $node->{$key}->value = $value;
      }

      $node->save();
    } else {
      $this->getLogger()->notice('Skipping product sku @sku with node id @nid. No change to checksum @checksum.', [
        '@sku' => $data['field_sku'],
        '@nid' => $nid,
        '@checksum' => $data['field_checksum'],
      ]);
    }

    return $node;
  }

  /**
   * Check to see if update to product node is necessary
   * @param  array  $data the candidate product data
   * @return boolean
   */
  protected function is_updated($data) {
    return ! count($this->query()->condition('field_checksum', $this->checksum($data), '=')->execute());
  }

  protected function checksum($data) {
    return md5(sprintf('%s%s%s%s%s%s%s%s%s',
      $data['field_sku'],
      $data['title'],
      $data['field_quantity_description'],
      $data['field_price'],
      $data['field_sale_price'],
      $data['field_image_url_product_thumb_1x'],
      $data['field_image_url_product_thumb_2x'],
      $data['field_image_url_product_tile_1x'],
      $data['field_image_url_product_tile_2x']
      )
    );
  }

  /**
   * Prepare the product data for node creation
   * @param  array $values the product data array
   * @return array
   *   the product creation array
   */
  private function prepare($values = []) {
    $values['field_checksum'] = $this->checksum($values);

    $return = [
      'type' => 'product',
    ];

    foreach ($values as $key => $value) {
      if ( $key === 'title' ) {
        $return[$key] = $value;
        $key = 'field_display_title';
      }
      $return[$key] = [
        'value' => $value
      ];
    }

    return $return;
  }
}
