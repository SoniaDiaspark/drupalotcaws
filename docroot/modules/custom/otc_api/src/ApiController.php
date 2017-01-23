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
   * @apiSuccessExample {json} Paged Categories
   * HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "count": 2,
   *    "results": [
   *      {
   *        "parent": "",
   *        "uuid": "da593707-2796-4f03-b75f-59a1515917b4",
   *        "name": "Parent Category",
   *        "description": null,
   *        "weight": 0,
   *        "changed": "2016-12-07T13:09:00-0600",
   *        "field_3200x1391_img": {
   *          "full": "http://example.com/path/to/image",
   *          ...
   *        },
   *        "field_828x828_img": null,
   *        "field_contributor": {
   *          "uuid": "abc123456-1234-a1b2-a2b3c4e517c4",
   *          "type": "contributor"
   *        },
   *        "field_cta_link": {
   *          "url": "http://example.com",
   *          "title": "Example Title"
   *        },
   *        "field_description": "&lt;p&gt;Category Description&lt;/p&gt;",
   *        "field_display_title": "Parent Category",
   *        "field_enable_fan_reel": false,
   *        "field_featured_content": [
   *          {
   *            "uuid": "fe593707-1234-4e03-c7da-59a1515917c4",
   *            "type": "featured_content"
   *          }
   *        ],
   *        ...
   *      },
   *      {
   *        "parent": "da593707-2796-4f03-b75f-59a1515917b4",
   *        "uuid": "f31b8dd9-9aae-42c5-903d-410064005b12",
   *        "name": "Child Category",
   *        "description": null,
   *        "weight": 0,
   *        "changed": "2016-12-07T13:09:10-0600",
   *        "field_3200x1391_img": null,
   *        ...
   *      }
   *    ]
   *  }
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
   * @apiSuccessExample {json} Paged Category Content
   * HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "published": true,
   *    "count": 2,
   *    "results": [
   *      {
   *        "uuid": "ccf0cb1a-64e4-456c-8048-971dd0d86ee8",
   *        "type": "project"
   *      },
   *      {
   *      ...
   *      }
   *    ]
   *  }
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
   * @apiDescription Base tag api call.
   *
   * @api {get} /api/fun365/tag Request paginated list of tags.
   * @apiName All
   * @apiGroup Tag
   * @apiParam {Number} page GET param  the result page (default 0)
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   * @apiParamExample {url} Request Example:
   *  /api/fun365/tag?page=1&recurse=1&depth=2
   *
   * @apiSuccessExample {json} Paged Tags
   * HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "count": 2,
   *    "results": [
   *      {
   *        "parent": "",
   *        "uuid": "ae9dc78c-074a-48d8-a6bd-e7a7924f155f",
   *        "name": "Christmas",
   *        "description": null,
   *        "weight": 0,
   *        "changed": "2016-12-12T13:31:05-0600"
   *      },
   *      {
   *        ...
   *      }
   *    ]
   *  }
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function tag(Request $request) {
    $options = [
      'page' => $request->get('page') * 1,
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchAllTerms('tag', $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'taxonomy_term'));

    return $response;
  }


  /**
   * @apiDescription Specific tag api call.
   *
   * @api {get} /api/fun365/tag/:uuid Request a specified tag.
   * @apiName Tag by UUID
   * @apiGroup Tag
   *
   * @apiParam {String} uuid Universally Unique ID for tag
   * @apiParamExample {url} Request Example:
   *  /api/fun365/tag/ae9dc78c-074a-48d8-a6bd-e7a7924f155f
   *
   * @apiSuccessExample {json} A Tag
   * HTTP/1.1 200 OK
   *  {
   *   "parent": "",
   *   "uuid": "ae9dc78c-074a-48d8-a6bd-e7a7924f155f",
   *   "name": "Christmas",
   *   "description": null,
   *   "weight": 0,
   *   "changed": "2016-12-12T13:31:05-0600"
   *  }
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function uuidTag(Request $request, $uuid) {
    $options = [
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchOneTerm('tag', $uuid, $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'taxonomy_term'));

    return $response;
  }

  /**
   * @apiDescription Content for a tag api call.
   *
   * @api {get} /api/fun365/tag/:uuid/content Request paginated list of content for a tag.
   * @apiName Content
   * @apiGroup Tag
   *
   * @apiParam {String} uuid Universally Unique ID for tag
   * @apiParam {Number} page GET param  the result page (default 0)
   * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
   * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
   * @apiParamExample {url} Request Example:
   *  /api/fun365/tag/ae9dc78c-074a-48d8-a6bd-e7a7924f155f/content?page=1&recurse=1&depth=2
   *
   * @apiSuccessExample {json} Tagged Content
   * HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "published": true,
   *    "count": 2,
   *    "results": [
   *      {
   *        "uuid": "ccf0cb1a-64e4-456c-8048-971dd0d86ee8",
   *        "type": "project"
   *      },
   *      {
   *      ...
   *      }
   *    ]
   *  }
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function tagContent(Request $request, $uuid) {
    $options = [
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchTagContent($uuid, $options);
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'node'));

    return $response;
  }

  /**
   * @apiDescription Specific tag api call.
   *
   * @api {get} /api/fun365/tag/:uuid Request a specified tag.
   * @apiName Category by UUID
   * @apiGroup Category
   *
   * @apiParam {String} uuid Universally Unique ID for tag
   * @apiParamExample {url} Request Example:
   *  /api/fun365/tag/da593707-2796-4f03-b75f-59a1515917b4
   *
   * @apiSuccessExample [json] Paged Categories
   *  HTTP/1.1 200 OK
   *  {
   *    "limit": 10,
   *    "page": 0,
   *    "count": 2,
   *    "results": [
   *      {
   *        "parent": "da593707-2796-4f03-b75f-59a1515917b4",
   *        "uuid": "f31b8dd9-9aae-42c5-903d-410064005b12",
   *        "name": "Child Category",
   *        "description": null,
   *        "weight": 0,
   *        "changed": "2016-12-07T13:09:10-0600",
   *        "field_3200x1391_img": {
   *          "full": "http://example.com/path/to/image",
   *          ...
   *        },
   *        ...
   *      },
   *      {
   *        "parent": "",
   *        "uuid": "da593707-2796-4f03-b75f-59a1515917b4",
   *        "name": "Parent Category",
   *        ...
   *      },
   *    ]
   *  }
   *
   * @param  Request $request the request
   * @return CacheableJsonResponse
   */
  public function uuidCategory(Request $request, $uuid) {
    $options = [
      'recurse' => (false || $request->get('recurse')),
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    $results = $this->restHelper->fetchOneTerm('tag', $uuid, $options);
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
   * @apiSuccessExample {json} Specified Content
   * HTTP/1.1 200 OK
   *  {
   *    "uuid": "ccf0cb1a-64e4-456c-8048-971dd0d86ee8",
   *    "type": "project",
   *    "status": true,
   *    "created": "2017-01-23T13:26:37-0600",
   *    "changed": "2017-01-23T13:26:37-0600",
   *    "field_828x828_img": {
   *      "full": "http://otc.prod.acquia-sites.com/sites/default/files/projects/hero/mobile/2x/828x828_0.png",
   *      "400x400_img": "http://otc.prod.acquia-sites.com/sites/default/files/styles/400x400_img/public/projects/hero/mobile/2x/828x828_0.png?itok=-C5ACjBN",
   *      "414x414_img": "http://otc.prod.acquia-sites.com/sites/default/files/styles/414x414_img/public/projects/hero/mobile/2x/828x828_0.png?itok=gdjmy1SZ",
   *      "448x448_img": "http://otc.prod.acquia-sites.com/sites/default/files/styles/448x448_img/public/projects/hero/mobile/2x/828x828_0.png?itok=OayjPFvG"
   *    },
   *    "field_896x896_img": {
   *      "full": "http://otc.prod.acquia-sites.com/sites/default/files/projects/tile/desktop/2x/896x896_0.png",
   *      ...
   *    },
   *    "field_900x677_img": null,
   *    "field_category": [
   *      {
   *        "uuid": "da593707-2796-4f03-b75f-59a1515917b4",
   *        "type": "category"
   *      },
   *      ...
   *    ],
   *    "field_contributor": {
   *      "uuid": "abc93123-1234-4f03-a15f-abc1515917b4",
   *      "type": "contributor"
   *    },
   *    "field_description": "&lt;p&gt;Test Project description&lt;/p&gt;\r\n",
   *    "field_display_title": "Test Project",
   *    "field_download_file": "http://otc.prod.acquia-sites.com/sites/default/files/downloads/pdf/OTC%20Content%20Management%20Integration%20Overview%20%28Updated%2012-2-2016%29.pdf",
   *    "field_items_needed": [
   *      "Item 1",
   *      "Item 2"
   *    ],
   *    "field_meta_description": "&lt;p&gt;Test Project description&lt;/p&gt;\r\n",
   *    "field_meta_title": "Test Project",
   *    "field_products": [
   *      {
   *        "uuid": "a55ca0f4-ede6-469a-b763-093f6b36d7ac",
   *        "type": "product"
   *      },
   *      ...
   *    ],
   *    "field_project_lite": false,
   *    "field_skill": "easy",
   *    "field_skyword_related_article": {
   *    "url": "http://example.com",
   *    "title": "Skyword Related Article \"Read More\" Link Text"
   *    },
   *    "field_skyword_related_look": {
   *    "url": "http://example.com",
   *    "title": "Skyword Related Look Link Text"
   *    },
   *    "field_step": [
   *      {
   *        "uuid": "5197a8e1-74ca-4071-8e8f-8068580c1194",
   *        "type": "step"
   *      },
   *      ...
   *    ],
   *    "field_suppress_fanreel": false,
   *    ...
   *  }
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
