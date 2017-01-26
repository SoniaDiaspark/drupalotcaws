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

    $routeCollection->add('otc_api.lookup', new Route(
      // uuid/path lookup route
      '/api/fun365/{contentType}/{id}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::lookup',
      ],

      // Route permission reqs
      [
        '_permission'  => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_content', new Route(
      // base category route
      '/api/fun365/category/{id}/content',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::categoryContent',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.category_lookup', new Route(
      // base category route
      '/api/fun365/category/{id}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::lookupCategory',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.tag_content', new Route(
      // base tag route
      '/api/fun365/tag/{id}/content',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::tagContent',
      ],

      // Route permission reqs
      [
        '_permission' => 'access content',
      ]
    ));

    $routeCollection->add('otc_api.tag_lookup', new Route(
      // base tag route
      '/api/fun365/tag/{id}',

      // Route configuration parameters
      [
        '_controller' => '\Drupal\otc_api\ApiController::lookupTag',
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
