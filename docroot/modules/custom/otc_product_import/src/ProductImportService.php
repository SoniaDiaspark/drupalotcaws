<?php

namespace Drupal\otc_product_import;

use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;

use Drupal\Core\Url;
use Drupal\Core\FileTransfer\FileTransferException;
use SimpleXMLElement;
use DOMDocument;

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
  public function batchImport( $forceUpdate = false ) {
      
      
      
//$ftp_host = 'transfer.orientaltrading.com';
//$ftp_username = 'DRUPAL';
//$ftp_password = '@(=~Iu5b';
//
////      echo  file_get_contents('ssh2.sftp://DRUPAL:@(=~Iu5b@transfer.orientaltrading.com:22/test.txt');
////      die('done');
//
//$connection = ssh2_connect('transfer.orientaltrading.com', 22);
//ssh2_auth_password($connection, 'DRUPAL', '@(=~Iu5b');
//$sftp = ssh2_sftp($connection);
//// See: https://bugs.php.net/bug.php?id=73597
//$stream = fopen("ssh2.sftp://" . intval($sftp) . "/test.txt", 'r');
//
//echo $stream;die('222222222');
      
      
    if ( ! $this->open() ) return;

    while( ($line = fgets($this->sourceFileHandle)) !== false ) {
      $data = [];

      // id|brand|name|quantity_description|item_type|prefix|price|sale_price|thumb_image|thumb_image_2_x|tile_image|tile_image_2_x
      list(
        $data['field_sku'],
        $data['field_brand'],
        $data['title'],
        $data['field_quantity_description'],
        $data['field_item_type'],
        $data['field_prefix'],
        $data['field_price'],
        $data['field_sale_price'],
        $data['field_image_url_product_thumb_1x'],
        $data['field_image_url_product_thumb_2x'],
        $data['field_image_url_product_tile_1x'],
        $data['field_image_url_product_tile_2x'],
        $data['product_url'],
      $data['field_product_closeout_status'],
      $data['field_product_in_stock_status']
      ) = explode('|', $line);

      $data['force'] = $forceUpdate;

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
      unset($data['force']);

      $this->getLogger()->notice("Updating product sku @sku with node id @nid", [
        '@sku' => $data['field_sku'],
        '@nid' => $nid,
      ]);

      $node = Node::load($nid);

      foreach ( $this->prepare($data) as $key => $value ) {
        $node->{$key} = $value;
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
    if ( isset($data['force']) && $data['force'] ) {
      return true;
    }

    return ! count($this->query()->condition('field_checksum', $this->checksum($data), '=')->execute());
  }

  protected function checksum($data) {
    return md5(sprintf('%s%s%s%s%s%s%s%s%s%s%s%s',
      $data['field_sku'],
      $data['field_brand'],
      $data['title'],
      $data['field_quantity_description'],
      $data['field_item_type'],
      $data['field_prefix'],
      $data['field_price'],
      $data['field_sale_price'],
      $data['field_image_url_product_thumb_1x'],
      $data['field_image_url_product_thumb_2x'],
      $data['field_image_url_product_tile_1x'],
      $data['field_image_url_product_tile_2x'],
      $data['product_url'],
      $data['field_product_closeout_status'],
      $data['field_product_in_stock_status']
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

      if ( $key === 'field_brand' ) {
        $return[$key] = $this->lookupBrand($value);
      }
    }

    return $return;
  }

  protected function lookupBrand($name) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('name', $name);

    foreach ( $query->execute() as $tid ) {
      return ['target_id' => $tid];
    }
  }
}


function get_rawlist_dir($ftp_rawlist) {
    $dirNames = array();
    $fileNames = array();
    foreach ($ftp_rawlist as $v) {
        $info = array();
        $vinfo = preg_split("/[\s]+/", $v, 9);
        if ($vinfo[0] !== "total") {
            $info['chmod'] = isset($vinfo[0]) ? $vinfo[0] : "";
            $info['num'] = isset($vinfo[1]) ? $vinfo[1] : "";
            $info['owner'] = isset($vinfo[2]) ? $vinfo[2] : "";
            $info['group'] = isset($vinfo[3]) ? $vinfo[3] : "";
            $info['size'] = isset($vinfo[4]) ? $vinfo[4] : "";
            $info['month'] = isset($vinfo[5]) ? $vinfo[5] : "";
            $info['day'] = isset($vinfo[6]) ? $vinfo[6] : "";
            $info['time'] = isset($vinfo[7]) ? $vinfo[7] : "";
            $info['name'] = isset($vinfo[8]) ? $vinfo[8] : "";
            $rawlist[$info['name']] = $info;
        }
    }
    $dir = array();
    $file = array();
    foreach ($rawlist as $k => $v) {

        if (isset($v['chmod'][0])) {
            if ($v['chmod'][0] == "d") {
                $dir[$k] = $v;
            } elseif ($v['chmod'][0] == "-") {
                $file[$k] = $v;
            }
        }
    }
    foreach ($dir as $dirname => $dirinfo) {
        //echo "[ $dirname ] " . $dirinfo['chmod'] . " | " . $dirinfo['owner'] . " | " . $dirinfo['group'] . " | " . $dirinfo['month'] . " " . $dirinfo['day'] . " " . $dirinfo['time'] . "<br>";

        if ($dirname != '.' && $dirname != '..')
            $dirNames[] = $dirname;
    }
    foreach ($file as $filename => $fileinfo) {
        //echo "$filename " . $fileinfo['chmod'] . " | " . $fileinfo['owner'] . " | " . $fileinfo['group'] . " | " . $fileinfo['size'] . " Byte | " . $fileinfo['month'] . " " . $fileinfo['day'] . " " . $fileinfo['time'] . "<br>";
        $fileNames[] = $filename;
    }
    return $dirNames;
}
