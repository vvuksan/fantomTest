<?php

require_once("./tools.php");

#header('Content-type: application/json');
header('Content-type: text/plain');


if ( isset($_GET['url'])) {

    $url = validate_url($_GET['url']);
    
    if ( $url === FALSE ) {
        print json_encode( array( "error" => "URL is not valid" ) );
        exit(1);
    }
    
    isset($_REQUEST['include_image']) && $_REQUEST['include_image'] == 1 ?  $include_image = true : $include_image = false;
    
    // Runs command locally
    $results = get_har_using_phantomjs($url, $include_image);

    print json_encode($results);  
}

?>