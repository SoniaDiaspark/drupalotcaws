<?php

namespace Drupal\otc_api;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RoutingService.
 *
 * @package Drupal\otc_api
 */
class RoutingService {

  /**
   * Route collection.
   * @return RouteCollection
   */
  public function routes() {
    $routeCollection = new RouteCollection();

    $routeCollection->add('otc_api.uuid', new Route(
      // uuid lookup route
      '/api/fun365/{contentType}/{uuid}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::uuid',
      ],

      // Route permission reqs
      [
        '_permission'  => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_content', new Route(
      // base category route
      '/api/fun365/category/{uuid}/content',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::categoryContent',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_uuid', new Route(
      // base category route
      '/api/fun365/category/{uuid}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::uuidCategory',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.tag_content', new Route(
      // base tag route
      '/api/fun365/tag/{uuid}/content',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::tagContent',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.tag_uuid', new Route(
      // base tag route
      '/api/fun365/tag/{uuid}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::uuidTag',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.tag_base', new Route(
      // base tag route
      '/api/fun365/tag',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::tag',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_base', new Route(
      // base category route
      '/api/fun365/category',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::category',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.base', new Route(
      // base content type route
      '/api/fun365/{contentType}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::base',
      ],

      // Route permission reqs
      [
        '_permission'  => 'access content',
      ]
    ));

    return $routeCollection;
  }

}
