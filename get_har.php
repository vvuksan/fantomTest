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
    
    // Runs command locally
    $results = get_har_using_phantomjs($url);

    print json_encode($results);  
}

?>