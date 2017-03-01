<?php

namespace Drupal\otc_legacy_import;

use \GuzzleHttp\ClientInterface;
use \Drupal\Core\File\FileSystemInterface;
use \GuzzleHttp\Psr7\Request;
use \Psr\Http\Message\ResponseInterface;
use \Drupal\Core\ImageToolkit\ImageToolkitManager;
use \Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface;

class ImageUrlResizerService {
  /**
   * @var \Drupal\Core\ImageToolkit\ImageToolkitManager
   */
  private $toolkitManager;

  /**
   * @var \Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface
   */
  private $toolkitOperationsManager;

  /**
   * @var \Drupal\Core\ImageToolkit\ImageToolkitInterface
   */
  private $toolkit;

  /**
  * @var \Drupal\Core\ImageToolkit\ImageToolkitOperationInterface
  */
  private $operation;

  /**
   * full path to destination
   * @var string
   */
  private $destination;

  /**
   * width and height parameters for resize
   * @var array
   */
  private $targetSize;

  /**
   * the source url of the original image
   * @var string
   */
  private $sourceUrl;

  /**
   * @var \GuzzleHttp\ClientInterface;
   */
  private $httpClient;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fs;

  public function __construct(
    ImageToolkitManager $imageToolkitManager,
    ImageToolkitOperationManagerInterface $imageToolkitOperationManager,
    ClientInterface $httpClient,
    FileSystemInterface $fileSystem
  ) {
    $this->toolkitManager = $imageToolkitManager;
    $this->toolkitOperationsManager = $imageToolkitOperationManager;
    $this->httpClient = $httpClient;
    $this->fs = $fileSystem;
  }

  /**
   * Execute the resize operation
   *
   * @param string $url the image source url
   * @param string $destinationFilePath full path to the destination file
   * @param array $targetSize [width, height]
   *
   * @return boolean
   * @throws Drupal\otc_legacy_import\ImageResizerException
   */
  public function resize($sourceUrl, $destinationFilePath, $targetSize = ['width' => 200, 'height' => 200] ) {
    $this->sourceUrl = $sourceUrl;
    $this->destination = $destinationFilePath;
    $this->targetSize = $targetSize;

    if ( ! ($this->sourceUrl && $this->destination && $this->targetSize) ) {
      throw new ImageResizerException("Missing url, destination, or target size.");
    }

    // the toolkit (GD implementation)
    $this->toolkit = $this->toolkitManager->getDefaultToolkit();

    // this scale and crop operation that uses the toolkit
    $this->operation = $this->toolkitOperationsManager->getToolkitOperation($this->toolkit, 'scale_and_crop');

    $request = new Request('GET', $this->sourceUrl);
    return $this->httpClient->sendAsync($request)->then(array($this, 'resizeCallback'))->wait();
  }

  /**
   * Resize callback method, public so that httpClient has access.
   * Not to be called directly.
   *
   * @param  ResponseInterface $response the http response
   * @return boolean success
   */
  public function resizeCallback(ResponseInterface $response) {
    $tempName = $this->fs->realpath(drupal_tempnam('temporary://', 'gd_'));
    $saved = file_put_contents($tempName, $response->getBody());
    $this->toolkit->setSource($tempName);
    $this->toolkit->parseFile();
    $this->toolkit->getResource();

    // scale and crop to this dimension
    $this->operation->apply($this->targetSize);
    $status = $this->toolkit->save($this->destination);
    unlink($tempName);

    return $status;
  }
}
