<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";
require_once $base_dir . "/tools.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

#header('Content-type: application/json');
header('Content-type: text/plain');


if ( isset($_GET['url'])) {

    $url = validate_url($_GET['url']);
    
    if ( $url === FALSE ) {
        print json_encode( array( "error" => "URL is not valid" ) );
        exit(1);
    }
    
    isset($_REQUEST['include_image']) && $_REQUEST['include_image'] == 1 ?  $include_image = true : $include_image = false;

    isset($_REQUEST['harviewer']) && $_REQUEST['harviewer'] == 1 ?  $harviewer = true : $harviewer = false;
    
    if ( isset($conf['prerender_server_url']) ) {

      $query_args = array (
        "url" => $url,
        "followRedirects" => true,
        "renderType" => "har"
      );
      $results = file_get_contents($conf['prerender_server_url'] . "?" . http_build_query($query_args));
    } else {
      $results = get_har_using_phantomjs($url, $include_image, $harviewer );
    }

    if ( $harviewer )
      print "onInputData(";
    
    print json_encode($results);  
    
    if ( $harviewer )
      print ");";

    
}

?>
