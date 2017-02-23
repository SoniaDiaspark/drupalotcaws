<?php

namespace Drupal\otc_legacy_import;

use \Drupal\Core\Config\ConfigFactory;
use \DateTime;
use \DateTimeZone;
use \PDO;

class WordPressDatabaseService {
  private $dbh;

  public function __construct(
    ConfigFactory $configFactory
  ) {
    $wpconfig = $configFactory->get('otc_legacy_import.config');
    list($host, $port, $dbName, $username, $password) = [
      $wpconfig->get('host'),
      $wpconfig->get('port'),
      $wpconfig->get('dbName'),
      $wpconfig->get('username'),
      $wpconfig->get('password'),
    ];

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8', $host, $port, $dbName);
    $this->dbh = new PDO($dsn, $username, $password);
  }

  public function getPosts($dateString = '', $limit = 10) {
    $statement = $this->dbh->prepare($this->allPostsQuery($dateString, $limit));
    $statement->execute();

    $posts = [];
    while($post = $statement->fetchObject()) {
      $post = $this->attachPostMeta($post);
      $post = $this->attachTaxonomyTerms($post);
      $posts[] = $post;
    }

    return $posts;
  }

  protected function attachPostMeta($post) {
    $metaKeys = [
      '_yoast_wpseo_title',
      '_yoast_wpseo_metadesc',
      'recipe-description',
      'recipe-yield',
      'prep-time',
      'ingredients',
      'directions',
      'time_to_complete__project',
      'seoDescription',
      'skyword_content_id',
      'skyword_content_type',
      'skyword_seo_title',
      'skyword_metadescription',
    ];

    $statement = $this->dbh->prepare(sprintf("SELECT m.meta_key, m.meta_value FROM wp_postmeta m WHERE m.post_id='%s'", $post->ID));
    $statement->execute();
    while($meta = $statement->fetchObject()) {
      if ( true || in_array($meta->meta_key, $metaKeys) ) {
        $post->{str_replace('-','_',$meta->meta_key)} = $meta->meta_value;
      }
    }

    return $post;
  }

  protected function attachTaxonomyTerms($post) {
    $colNames = [
      't.term_id',
      't.name',
      'tt.taxonomy',
    ];

    $columns = implode(',', $colNames);

    $query = "SELECT $columns FROM wp_terms t, wp_term_taxonomy tt, wp_term_relationships tr";
    $query .= " WHERE tr.term_taxonomy_id=tt.term_taxonomy_id and t.term_id=tt.term_id and tr.object_id='%s'";
    $query = sprintf($query, $post->ID);
    $statement = $this->dbh->prepare($query);
    $statement->execute();
    while($term = $statement->fetchObject()) {
      if ( isset($post->{$term->taxonomy}) ) {
        $post->{$term->taxonomy} = [];
      }
      $post->{$term->taxonomy}[] = [
        'name' => $term->name,
        'term_id' => $term->term_id,
      ];
    }

    return $post;
  }

  protected function allPostsQuery($dateString = '', $limit = 10) {
    $colNames = [
      'p.ID',
      'p.post_author',
      'p.post_date',
      'p.post_title',
      'p.post_name',
      'p.post_modified',
      'p.guid',
      'p.post_content',
    ];

    $columns = implode(',', $colNames);

    $query = "SELECT $columns FROM wp_posts p WHERE p.post_status='publish' AND p.post_type='post'";
    if ($dateString) {
      $query .= " AND p.post_date > '$dateString'";
    }
    $query .= " ORDER BY p.post_date ASC";
    if ($limit > 0) {
      $query .= sprintf(" LIMIT %s", max((int) $limit, 1));
    }

    echo $query . "\n";
    return $query;
  }

  public function getUsers($dateString = '', $limit = 10) {
    $statement = $this->dbh->prepare($this->allUsersQuery($dateString, $limit));
    $statement->execute();

    $users = [];
    while($user = $statement->fetchObject()) {
      $user = $this->attachUserMeta($user);
      $users[] = $user;
    }

    $users = $this->attachUserAvatars($users);

    return $users;
  }

  protected function allUsersQuery($dateString = '', $limit = 10) {
    $colNames = [
      'u.ID',
      'u.user_registered',
      'u.user_nicename',
      'u.user_email',
      'u.user_url',
      'u.display_name',
    ];

    $columns = implode(',', $colNames);

    $query = "SELECT $columns FROM wp_users u, wp_usermeta m WHERE m.user_id=u.id AND m.meta_key='wp_capabilities' and m.meta_value NOT LIKE '%administrator%'";
    if ($dateString) {
      $query .= " AND u.user_registered > '$dateString'";
    }
    $query .= " ORDER BY u.user_registered ASC";
    if ($limit > 0) {
      $query .= sprintf(" LIMIT %s", max((int) $limit, 1));
    }

    echo $query . "\n";
    return $query;
  }

  protected function attachUserMeta($user) {
    $metaKeys = [
    ];

    $statement = $this->dbh->prepare(sprintf("SELECT m.meta_key, m.meta_value FROM wp_usermeta m WHERE m.user_id='%s'", $user->ID));
    $statement->execute();
    while($meta = $statement->fetchObject()) {
      if ( true || in_array($meta->meta_key, $metaKeys) ) {
        $key = str_replace('-','_',$meta->meta_key);
        $user->{$key} = $meta->meta_value;
      }
    }

    return $user;
  }

  protected function attachUserAvatars($users = []) {
    $processedUsers = [];
    foreach ($users as $user) {
      if ( isset($user->wp_user_avatar) ) {
        $processedUsers[] = $this->attachUserAvatar($user);
        continue;
      }

      $processedUsers[] = $user;
    }
    return $processedUsers;
  }

  protected function attachUserAvatar($user) {
    $query = sprintf("SELECT p.guid FROM wp_posts p WHERE p.ID='%s' AND post_type='attachment'", $user->wp_user_avatar);
    echo $query . "\n";

    $statement = $this->dbh->prepare($query);
    $statement->execute();
    if ( !($avatar = $statement->fetchObject()) ) return $user;

    $user->avatar = $avatar->guid;
    return $user;
  }
}
