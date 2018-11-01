<?php

namespace Drupal\otc_mega_menu\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuParentFormSelector;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Url;

/**
 * Create mega menu json response
 */
class OtcMenusList extends ControllerBase {

  /**
   * A list of menu items.
   *
   * @var array
   */
  protected $menuItems = [];

  /**
   * The maximum depth we want to return the tree.
   *
   * @var int
   */
  protected $maxDepth = 0;

  /**
   * The minimum depth we want to return the tree from.
   *
   * @var int
   */
  protected $minDepth = 1;

  /**
   * Constructs a menu.
   */
  public function content() {
      
    $request = \Drupal::request(); 
    $brand = $request->get('brand');
    $level = $request->get('level');
    $parent = $request->get('parent');  
    $menu_name = $parent ? $parent : '';
    $parent = str_replace(" and "," & ",$parent);    
    $parent_output_title = strtolower($menu_name);
    $parent_output_title = str_replace(" ","-","$parent_output_title");    
    $parent_output_title_exp = explode("?",$parent_output_title);
    $parent_output_title_exp = (!empty($parent_output_title_exp[0])) ? $parent_output_title_exp[0] : $parent_output_title;
    
    if(!empty($parent_output_title_exp)){
        $getParentIds = $this->getParentIds($brand,$parent_output_title_exp);
    }
        
    // Setup variables.
    $this->setup();
   
    // Create the parameters.
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();

    if (!empty($this->maxDepth)) {
        $maxdepth = $this->maxDepth + 1;
        $parameters->setMaxDepth($maxdepth);
    }else{
        $parameters->setMaxDepth(1);   
    }
    
    $parameters->setMinDepth($this->minDepth);   
  
    if(!empty($getParentIds)){
     $parameters->setRoot($getParentIds);
    } 

    $menu_tree = \Drupal::menuTree();
    $tree = $menu_tree->load($brand, $parameters);

    if (empty($tree)) {
      return new JsonResponse([]);
    }

    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],     
        
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
   
    // Finally, build a renderable array from the transformed tree.
    $menu = $menu_tree->build($tree);
    
    $this->getMenuItems($menu['#items'], $this->menuItems);

    $output = array_values($this->menuItems); 

    $response = new CacheableJsonResponse($output);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($output));
    return $response;
  }

  /**
   * Constructs a sub menu.
   */
  public function getMenuItems(array $tree, array &$items = []) {
      
      
      
    foreach ($tree as $item_value) {
          
      /* @var $org_link \Drupal\Core\Menu\MenuLinkDefault */
      $org_link = $item_value['original_link'];
      $options = $org_link->getOptions();

      // Set name to uuid or base id.
      $item_name = $org_link->getDerivativeId();
      if (empty($item_name)) {
        $item_name = $org_link->getBaseId();
      }

      /* @var $url \Drupal\Core\Url */
      $url = $item_value['url'];

      $external = FALSE;
      $uuid = '';
      if ($url->isExternal()) {
        $uri = $url->getUri();
        $external = TRUE;
        $absolute = $uri;
      }
      else {
           $absolute = $uri = '';
      }


      $alias = \Drupal::service('path.alias_manager')->getAliasByPath("/$uri");

    $menuid = $org_link->getDerivativeId();
        $entity = \Drupal::service('entity.repository')
      ->loadEntityByUuid('menu_link_content', $menuid);
    $field_menu_image_url = $entity->field_menu_image_url->value;  
    
    $menu_title = strtolower($org_link->getTitle());
    $menu_title = str_replace(" ","-",$menu_title); 
    
      $items[$item_name] = [
        'key'      => $item_name,
        'title'    => $org_link->getTitle(),
        'path'    => strtolower($menu_title), 
        'uri'      => $uri,
        //'alias'    => ltrim($alias, '/'),
        'external' => $external,
        //'absolute' => $absolute,
        'menu_image' => $field_menu_image_url,
        'weight'   => $org_link->getWeight(),
        'expanded' => $org_link->isExpanded(),
        'enabled'  => $org_link->isEnabled(),
        'uuid'     => $uuid,
        'options'     => $options,
      ];

      if (!empty($item_value['below'])) {
        $items[$item_name]['below'] = [];
        $this->getMenuItems($item_value['below'], $items[$item_name]['below']);
      }
    }
  }

  /**
   * This function is used to generate some variables we need to use.
   *
   * These variables are available in the url.
   */
  private function setup() {
    // Get the current request.
    $request = \Drupal::request();

    // Get and set the max depth if available.
    $max = $request->get('depth') ? $request->get('depth') : $request->get('level');
    if (!empty($max)) {
      $this->maxDepth = $max;
    }
  }
  
  /**
   * Return child menu of perticular parent.
   * @param $menu_name: Name of the menu
   * @param $parent_name: Parent menu item name
   */
  public function getParentIds($menu_name,$parent_name){   
      
    $menu_name = strtolower($menu_name);
    $parent_output_title = strtolower($parent_name);
    $parent_output_title = str_replace(" ","-","$parent_output_title");    
    $parent_output_title_exp = explode("?",$parent_output_title);
    $parent_output_title_exp = (!empty($parent_output_title_exp[0])) ? $parent_output_title_exp[0] : $parent_output_title;     
    $parent_menu_name = str_replace("-"," ","$parent_output_title_exp"); 
    $parent_menu_name = str_replace(" and "," & ","$parent_menu_name"); 
    
    $query = \Drupal::database()->select('menu_link_content', 'mlc');
    $query->fields('mlc', ['uuid']);
    $query->fields('mld', ['title']);
    $query->join('menu_link_content_data', 'mld', 'mld.id = mlc.id');
    $query->condition('mld.parent','', 'IS NULL');
    $query->condition('mld.bundle',$menu_name);
    $query->condition('mld.menu_name',$menu_name);
    $query->condition('mld.title', "%" . $query->escapeLike($parent_menu_name) . "%", 'LIKE');
    $z_results = $query->execute()->fetchAll();
    

    $field_id_value = array();
    if (!empty($z_results)) {
      foreach ($z_results as $z_results_value) {
          if($z_results_value->title !="" && $z_results_value->uuid !="" ){
            $parent_output_title = strtolower($z_results_value->title);
            $parent_output_title = str_replace(" ","-","$parent_output_title");
            $parent_output_title = str_replace("-&-","-and-","$parent_output_title");       
            if($parent_output_title_exp == $parent_output_title){
                $parent_output_key_details = 'menu_link_content:'.$z_results_value->uuid;
            }
        }
      }
    }
    return (isset($parent_output_key_details)) ? $parent_output_key_details : "";    
  }

}
