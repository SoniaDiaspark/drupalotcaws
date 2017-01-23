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
   * @apiDescription Base category api call.
   *
   * @api {get} /api/fun365/category Request paginated list of categories.
   * @apiName All
   * @apiGroup Category
   * @apiParam {Number} page GET param  the result page (default 0)
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   * @apiParamExample {url} Request Example:
   *  /api/fun365/category?page=1&recurse=1&depth=2
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
   * @apiDescription Content for a category api call.
   *
   * @api {get} /api/fun365/category/:uuid/content Request paginated list of content for a category.
   * @apiName Content
   * @apiGroup Category
   *
   * @apiParam {String} uuid Universally Unique ID for category
   * @apiParam {Number} page GET param  the result page (default 0)
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   * @apiParamExample {url} Request Example:
   *  /api/fun365/category/da593707-2796-4f03-b75f-59a1515917b4/content?page=1&recurse=1&depth=2
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
   * @apiDescription Specific category api call.
   *
   * @api {get} /api/fun365/category/:uuid Request a specified category.
   * @apiName Category by UUID
   * @apiGroup Category
   *
   * @apiParam {String} uuid Universally Unique ID for category
   * @apiParamExample {url} Request Example:
   *  /api/fun365/category/da593707-2796-4f03-b75f-59a1515917b4
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
   * @apiDescription Base content type api call. Requests can be for any permitted
   *   content type in the CMS. See \Drupal\otc_api\RestHelper::contentTypePermitted()
   *
   * @api {get} /api/fun365/:contentType Request paginated list of content of a specified type.
   * @apiName All
   * @apiGroup Node
   *
   * @apiParam {String} contentType name of content type
   * @apiParam {Number} page GET param  the result page (default 0)
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   * @apiParam {Number} published GET param 1 for published only, 0 for all (default 1)
   * @apiParamExample {url} Content Type Request:
   *
   * // All projects, page 0, recurse to 2 levels:
   *  /api/fun365/project?page=0&recurse=1&depth=2&published=0
   *
   * // All projects, page 1, don't recurse, published only:
   *  /api/fun365/project?page=0&recurse=0&published=1
   *
   * // All articles, page 0, recurse to 2 levels:
   *  /api/fun365/article?page=0&recurse=1&depth=2&published=0
   *
   * // All articles, page 1, don't recurse, published only:
   *  /api/fun365/article?page=0&recurse=0&published=1
   *
   * // All contributors, page 0, recurse to 2 levels:
   *  /api/fun365/contributor?page=0&recurse=1&depth=2&published=0
   *
   * // All contributors, page 1, don't recurse, published only:
   *  /api/fun365/contributor?page=0&recurse=0&published=1
   *
   * // All recipes, page 0, recurse to 2 levels:
   *  /api/fun365/recipe?page=0&recurse=1&depth=2&published=0
   *
   * // All recipes, page 1, don't recurse, published only:
   *  /api/fun365/recipe?page=0&recurse=0&published=1
   *
   * // All looks, page 0, recurse to 2 levels:
   *  /api/fun365/look?page=0&recurse=1&depth=2&published=0
   *
   * // All looks, page 1, don't recurse, published only:
   *  /api/fun365/look?page=0&recurse=0&published=1
   *
   * // All Featured Content, page 0, recurse to 2 levels:
   *  /api/fun365/featured_content?page=0&recurse=1&depth=2&published=0
   *
   * // All Featured Content, page 1, don't recurse, published only:
   *  /api/fun365/featured_content?page=0&recurse=0&published=1
   *
   * @apiSuccessExample [json] Paged Response
   * /api/fun365/project?published=0
   *  HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "count": 1,
   *    "results": [
   *      {
   *        "uuid": "ccf0cb1a-64e4-456c-8048-971dd0d86ee8",
   *        "field_828x828_img": : {
   *          "full": "http://otc.dev.dd:8083/sites/otc.dev.dd/files/projects/hero/mobile/2x/828x828_0.png",
   *          "400x400_img": "http://otc.dev.dd:8083/sites/otc.dev.dd/files/styles/400x400_img/public/projects/hero/mobile/2x/828x828_0.png?itok=PMZEVPLF",
   *          "414x414_img": "http://otc.dev.dd:8083/sites/otc.dev.dd/files/styles/414x414_img/public/projects/hero/mobile/2x/828x828_0.png?itok=DeLYX6Na",
   *          "448x448_img": "http://otc.dev.dd:8083/sites/otc.dev.dd/files/styles/448x448_img/public/projects/hero/mobile/2x/828x828_0.png?itok=snkjXFII"
   *        },
   *        "field_category": [
   *          "uuid": "da593707-2796-4f03-b75f-59a1515917b4",
   *          "type": "category"
   *        ]
   *        "field_time_min": 1,
   *        "field_time_max": 10,
   *        "field_project_lite": false
   *        ...
   *      }
   *    ]
   *  }
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
   * @apiDescription Get specific node by uuid. Requests can be for any permitted
   *   content type in the CMS. See \Drupal\otc_api\RestHelper::contentTypePermitted().
   *   The entity specified by the uuid much match the content specified type.
   *
   * @api {get} /api/fun365/:contentType/:uuid Request specific node.
   * @apiName Node by UUID
   * @apiGroup Node
   *
   * @apiParam {String} contentType name of content type
   * @apiParam {String} uuid Universally Unique ID for node
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   *
   * @apiParamExample {url} Content Type by Uuid Request:
   *
   * // Specified project, no recursion:
   *  /api/fun365/project/ccf0cb1a-64e4-456c-8048-971dd0d86ee8
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
