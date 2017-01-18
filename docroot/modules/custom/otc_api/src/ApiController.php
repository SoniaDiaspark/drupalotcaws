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
   * @api {get} /api/category Request paginated list of categories.
   * @apiName All
   * @apiGroup Category
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function category(Request $request) {
    $options = [
      'page' => $request->get('page') * 1,
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchAllTerms('category', $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'taxonomy_term'));

    return $response;
  }

  /**
   * Content for a category api call.
   *
   * @api {get} /api/category/:uuid/content Request paginated list of content for a category.
   * @apiName Content
   * @apiGroup Category
   *
   * @apiParam {String} uuid Universally Unique ID for category
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function categoryContent(Request $request, $uuid) {
    $options = [
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchCategoryContent($uuid, $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'node'));

    return $response;
  }

  /**
   * Specific category api call.
   *
   * @api {get} /api/category/:uuid Request a specified category.
   * @apiName Category by UUID
   * @apiGroup Category
   *
   * @apiParam {String} uuid Universally Unique ID for category
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function uuidCategory(Request $request, $uuid) {
    $options = [
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchOneTerm('category', $uuid, $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'taxonomy_term'));

    return $response;
  }

  /**
   * Base content type api call.
   *
   * @api {get} /api/:contentType Request paginated list of content of a specified type.
   * @apiName All
   * @apiGroup Node
   *
   * @apiParam {String} contentType name of content type
   *
   * @param  Request $request the request
   * @param  string $contentType content type
   * @return CacheableJsonResponse
   */
  public function base(Request $request, $contentType) {
    $options = [
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $resultData = $this->restHelper->fetchAll($contentType, $options);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData());

    return $response;
  }

  /**
   * Get specific node by uuid
   *
   * @api {get} /api/:contentType/:uuid Request specific node.
   * @apiName Node by UUID
   * @apiGroup Node
   *
   * @apiParam {String} contentType name of content type
   * @apiParam {String} uuid Universally Unique ID for node
   *
   * @param  Request $request     the request
   * @param  string  $contentType the content type
   * @param  string  $uuid        uuid of the node
   * @return CacheableJsonResponse
   */
  public function uuid(Request $request, $contentType, $uuid) {
    $options = [
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $resultData = $this->restHelper->fetchOne($contentType, $uuid, $options);

    $response = new CacheableJsonResponse($resultData);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($resultData));

    return $response;
  }
}
