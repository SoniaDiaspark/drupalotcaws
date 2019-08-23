<?php 


   
//       $get_url = "https://cl-drupal.orientaltrading.com/mannual_run/skyword_import";
// 
//       $username='swtalam';
//       $password='otc909090';
//       $ch = curl_init();
//       curl_setopt($ch, CURLOPT_URL, $get_url);
//       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//       curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
//       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//       curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//       $data = curl_exec($ch);
//       $data = json_decode($data);
//       
//       
//       print_r($data); 
//    
//       $info2 = curl_getinfo($ch);
//       curl_close($ch);
//       die('done');
       
         
//       
//require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
//
//use GuzzleHttp\Client;
//use GuzzleHttp\Cookie\CookieJar;
//use GuzzleHttp\Exception\RequestException;
//
//$base_url = 'https://cl-drupal.orientaltrading.com';
//
//$jar = new CookieJar();
//
//try {
//
//  $client = new Client([
//    'base_url' => $base_url,
//    'cookies' => true,
//    'allow_redirects' => true,
//    'debug' => true
//  ]);
//
//  $response = $client->post($base_url . '/user/login', [
//    "form_params" => [
//      "name"=> "admin",
//      "pass"=> "admin",
//      'form_id' => 'user_login_form'
//    ],
//    'cookies' => $jar
//  ]);
//
//  $token = $client->get($base_url . '/rest/session/token', [
//    'cookies' => $jar
//  ])->getBody(TRUE);
//
//  $token = $token->__toString();
//
//  $node = array(
//    '_links' => array(
//      'type' => array(
//        'href' => $base_url . '/rest/type/node/article'
//      )
//    ),
//    'title' => array(0 => array('value' => 'New node title - Cookie')),
//  );
//  
//  $node = array();
//  
//  $response = $client->post('https://cl-drupal.orientaltrading.com/mannual_run/skyword_import', [
//    'cookies' => $jar,
//    'headers' => [
//      'Accept' => 'application/json',
//      'Content-type' => 'application/hal+json',
//      'X-CSRF-Token' => $token,
//    ],
//    'json' => $node
//  ]);
//  
//  if ($response->getStatusCode() == 201) {
//    print 'Node creation successful!';
//  } else {
//    print "unsuccessful... keep trying";
//    print_r(get_defined_vars());
//  }
//} catch(RequestException $e) {
//  echo $e->getRequest();
//  echo "\n\n";
//  if ($e->hasResponse()) {
//    echo $e->getResponse();
//  }
//}


$POSTFIELDS = array(
    'username' => 'swtalam',
    'password' => 'otc909090'
);

$username = 'swtalam';
$password = 'otc909090';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://cl-drupal.orientaltrading.com/user/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$data = curl_exec($ch);
$info2 = curl_getinfo($ch);
echo "<pre>";
print_r($data);
echo "</pre>";
curl_close($ch);



       
       
?>