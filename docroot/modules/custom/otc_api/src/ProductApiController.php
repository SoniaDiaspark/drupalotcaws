<?php
namespace Drupal\otc_api;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class ProductApiController extends ControllerBase {

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
  * @apiDescription product referenced content by sku api call.
  *
  * @api {get} /api/fun365/contributor/:sku/content Request paginated list of content for a sku.
  * @apiName Product Referenced Content
  * @apiGroup Product
  * @apiParam {String} sku1 Unique ID for each product.
  * @apiParam {String} sku2 Unique ID for each product and will be used if we have slash in sku.
  * @apiParam {Number} page GET param  the result page (default 0)
  * @apiParam {Number} limit GET param limit number of results per page (default 10)
  * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
  * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
  * @apiParamExample {url} Request Example:
  *  /api/fun365/product/70/237/content?page=1
  *
  * @apiSuccessExample {json} Paged Contributor Content
  * HTTP/1.1 200 OK
  *  {
  *    "limit": 10,
  *    "page": 0,
  *    "published": true,
  *    "count": 2,
  *    "results": [
  *  	  {
  *    		"type": "project",
  *    		"created": "2017-06-12T10:26:37-0500",
  *    		"path": "diy-patriotic-windsocks",
  *    		"field_896x896_img": {
  *      		"full": "image.jpg"
  *    		},
  *    		"field_display_title": "DIY Patriotic Windsocks"
  *  	  },
  *  	  {
  *    		"type": "article",
  *    		"created": "2017-03-29T17:06:21-0500",
  *    		"path": "oktoberfest-party-beer-tasting",
  *    		"field_896x896_img": {
  *      		"full": "image.jpg"
  *    	  },
  *    		"field_display_title": "Oktoberfest Party: Beer Tasting"
  *  	  },
  *       {
  *      	...
  *       }
  *    ]
  *  }
  *
  * @param  Request $request the request
  * @param  string $sku
  * @return CacheableJsonResponse
  */
  public function productSKU(Request $request, $sku1, $sku2='') {
    $sku = $sku1;
    if($sku2 != '') {
      $sku = $sku1.'/'.$sku2;
    }
    $options = [
      'limit' => ($request->get('limit') ? intval($request->get('limit')) : 10),
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => 1,
      'maxDepth' => ($request->get('depth') ? intval($request->get('depth')) : 2),
    ];

    try {
      $results = $this->restHelper->fetchProductSKUContent($sku, $options);
      $response = new CacheableJsonResponse($results);
      $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'node'));

      return $response;
    } catch (Exception $e) {
      return $this->handleException($e);
    }
  }

  /**
  * @apiDescription product referenced content count by sku api call.
  * @api {get} /api/fun365/contributor/:sku/contentcount Request referenced content count for a sku.
  * @apiName Product Referenced Content Count
  * @apiGroup Product
  * @apiParam {String} sku1 Unique ID for each product.
  * @apiParam {String} sku2 Unique ID for each product and will be used if we have slash in sku.
  * @apiParam {Number} page GET param  the result page (default 0)
  * @apiParam {Number} limit GET param limit number of results per page (default 10)
  * @apiParam {Number} recurse GET param 1 to recurse content (default 0)
  * @apiParam {Number} depth GET param levels deep to limit recursion (default 2)
  * @apiParamExample {url} Request Example:
  *  /api/fun365/product/70/237/contentcount
  *
  * @apiSuccessExample {json} Paged Contributor Content
  * HTTP/1.1 200 OK
  *  {
  *    "count": 4,
  *  }
  *
  * @param  Request $request the request
  * @param string $sku
  * @return CacheableJsonResponse
  */
  public function productCountSKU(Request $request, $sku1, $sku2='') {
    $sku = $sku1;
    if($sku2 != '') {
      $sku = $sku1.'/'.$sku2;
    }
    $options = [
      'limit' => ($request->get('limit') ? intval($request->get('limit')) : 10),
      'published' => $request->get('published') !== '0',
      'page' => $request->get('page') * 1,
      'recurse' => 1,
      'maxDepth' => 2,
      'countSKUContent' => 'yes'
    ];

    try {
      $results = $this->restHelper->fetchProductSKUContent($sku, $options);
      $response = new CacheableJsonResponse($results);
      $response->addCacheableDependency($this->restHelper->cacheMetaData($results, 'node'));

      return $response;
    } catch (Exception $e) {
      return $this->handleException($e);
    }
  }

   /**
   * Handle Exceptions
   * @param  Exception $e the exception
   * @return CacheableJsonResponse
   */
  protected function handleException(Exception $e) {
    if ( $e instanceof Rest404Exception ) {
      return new CacheableJsonResponse(['error' => $e->getMessage()], 404);
    } elseif ($e instanceof Rest403Exception) {
      return new CacheableJsonResponse(['error' => $e->getMessage()], 403);
    }

    return new CacheableJsonResponse(['error' => 'Internal server error.'], 500);
  }

}