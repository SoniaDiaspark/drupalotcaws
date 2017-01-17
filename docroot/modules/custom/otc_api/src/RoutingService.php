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
      '/api/{contentType}/{uuid}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::uuid',
      ],

      // Route permission reqs
      [
        '_permission'  => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_uuid', new Route(
      // base category route
      '/api/category/{uuid}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::uuidCategory',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_base', new Route(
      // base category route
      '/api/category',

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
      '/api/{contentType}',

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
