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
   * Base content type api call.
   *
   * @param  Request $request the request
   * @param  string $contentType content type
   * @return CacheableJsonResponse
   */
  public function base(Request $request, $contentType) {
    $published = $request->get('published') !== '0';

    $page = $request->get('page') * 1;
    $resultData = $this->restHelper->fetchAll($contentType, $page, $published);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData());

    return $response;
  }

  public function uuid(Request $request, $contentType, $uuid) {
    $resultData = $this->restHelper->fetchOne($contentType, $uuid);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($resultData));

    return $response;
  }
}
