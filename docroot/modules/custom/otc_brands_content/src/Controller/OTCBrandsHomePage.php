<?php

namespace Drupal\otc_brands_content\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\otc_api\RestHelper;
/**
 * Return json response for home page based on ID.
 */
class OTCBrandsHomePage implements ContainerInjectionInterface {
  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The rest helper service
   *
   * @var Drupal\otc_api\RestHelper
   */
  protected $restHelper;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Creates an OTCBrandsHomePage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\otc_api\RestHelper $restHelper
   *   The rest helper service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager, RestHelper $restHelper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->restHelper = $restHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('path.alias_manager'),
      $container->get('otc_api.rest_helper')
    );
  }

  /**
   * Return json response for a given node ID.
   *
   * @param string $brand
   *   The brand name in the path.
   * @param string $device
   *   The device name in the path.
   * @param string $page
   *   The page name in the path.
   *
   * @return json
   *   A json response of all the section based on node ID.
   */
  public function homePageJson($brand, $device, $page) {
    $path = '/'.$brand.'/'.$device.'/'.$page;
    $output = array();
    $nid = $this->aliasManager->getPathByAlias($path);
    // Check if node is exists or not.
    if ($nid == $path) {
    	$output['error'] = 'Not found';
    } else {
	    if(preg_match('/node\/(\d+)/', $nid, $matches)) {
	      // Load node object by node ID from db.
	      $node_storage = $this->entityTypeManager->getStorage('node');
	      $node = $node_storage->load($matches[1]);
	  	  $field_section = $node->get('field_section')->getValue();

  		  foreach ($field_section as $key => $value) {
  		    $paragraphID = $value['target_id'];
  		    // Load paragraph object by ID
  		    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
  		    $p = $paragraph_storage->load($paragraphID);
  		    $text = $p->field_section_description->getValue();
  		    $output['section-'.$key] = $text[0]['value'];
  		  }
		  }
    }

    $response = new CacheableJsonResponse($output);
    $response->addCacheableDependency($this->restHelper->cacheMetaData($output, 'node'));
    return $response;
  }

}
