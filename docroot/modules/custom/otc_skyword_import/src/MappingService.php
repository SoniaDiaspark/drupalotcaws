<?php

namespace Drupal\otc_skyword_import;

use Drupal\Core\Config\ConfigFactory;

class MappingService implements MappingServiceInterface {
  private $mappers;

  public function __construct(ConfigFactory $configFactory) {
    $config = $configFactory->get('otc_skyword_import.config');
    $this->mappers = [
      'article' => new ArticleMapper($config->get('fileUrlPrefix')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function get($type) {
    if ( in_array($type, array_keys($this->mappers) ) ) {
      return $this->mappers[$type];
    }

    return false;
  }
}
