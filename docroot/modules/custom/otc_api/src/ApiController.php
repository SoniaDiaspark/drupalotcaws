<?php

namespace Drupal\otc_api;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiController extends ControllerBase {

  /**
   * @var Drupal\otc_api\RestHelper
   */
  protected $restHelper;

  /**
   * Constructor.
   */
  public function __construct(RestHelper $restHelper) {
    $this->restHelper = $restHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
      return new static(
        $container->get('otc_api.rest_helper')
      );
  }

  /**
   * Base category api call.
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function category(Request $request) {
    $options = [
      'page' => $request->get('page') * 1,
      'recurse' => true,
      'recurseLimit' => 2,
    ];

    $results = $this->restHelper->fetchAllTerms('category', $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'taxonomy_term'));

    return $response;
  }

  /**
   * Base content type api call.
   *
   * @param  Request $request the request
   * @param  string $contentType content type
   * @return CacheableJsonResponse
   */
  public function base(Request $request, $contentType) {
    $options = [
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => false
    ];

    $resultData = $this->restHelper->fetchAll($contentType, $options);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData());

    return $response;
  }

  /**
   * Get specific node by uuid
   * @param  Request $request     the request
   * @param  string  $contentType the content type
   * @param  string  $uuid        uuid of the node
   * @return CacheableJsonResponse
   */
  public function uuid(Request $request, $contentType, $uuid) {
    $resultData = $this->restHelper->fetchOne($contentType, $uuid, ['recurse' => false]);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($resultData));

    return $response;
  }
}
