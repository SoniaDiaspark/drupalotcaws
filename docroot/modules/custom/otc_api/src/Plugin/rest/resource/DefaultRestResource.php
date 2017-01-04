<?php

namespace Drupal\otc_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Drupal\user\Entity\User;
// use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\otc_api\NodeHelper;

/**
 * OTC base content types API.
 *
 * @RestResource(
 *   id = "otc_base",
 *   label = @Translation("OTC Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/{node_type}"
 *   }
 * )
 */
class DefaultRestResource extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * An EntityManager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  // protected $entityManager;

  /**
   * StreamWrapperManager instance.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    // EntityTypeManagerInterface $entityManager,
    QueryFactory $queryFactory,
    StreamWrapperManager $streamWrapperManager
    ) {

    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    // $this->entityManager = $entityManager;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('otc_api'),
      $container->get('current_user'),
      // $container->get('entity.manager'),
      $container->get('entity.query'),
      $container->get('stream_wrapper_manager')
    );
  }
  /**
   * Responds to GET requests.
   *
   * Get all nodes of specified bundle, if permitted.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($node_type = NULL) {
    $published = $pub === 'prev';

    return new ResourceResponse($node_type);

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    // if (!$this->currentUser->hasPermission('access content')) {
    //   throw new AccessDeniedHttpException();
    // }

    // $users = $this->entityManager->getStorage('user')->loadMultiple();
    // $return = [
    //   'users' => [
    //     'count' => count($users),
    //     'images' => array_reduce($users, function($images, $user){
    //       if ( ($image = $this->getUserImageUrl($user)) ) {
    //         $images[] = $image;
    //       }
    //
    //       return $images;
    //     }, []),
    //   ]
    // ];

    $return = [];

    return new ResourceResponse($return);
  }


  /**
   * Given a User object, will return a bio image url if present.
   * @param  User   $user the user instance
   * @see \Drupal\user\Entity\User
   * @return string
   */
  private function getUserImageUrl(User $user) {
      // $bioImageId = current($user->field_bio_image->getValue())['target_id'] ?? NULL;
      // if ( $bioImageId ) {
      //   $Image = $this->entityManager->getStorage('file')->load($bioImageId);
      //   if ( ($imageHandler = $this->streamWrapperManager->getViaUri($Image->getFileUri())) ) {
      //     return $imageHandler->getExternalUrl();
      //   }
      // }
      //
      // return '';
  }
}
