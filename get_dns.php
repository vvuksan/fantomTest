<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";
require_once $base_dir . "/tools.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

$site_id = is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

if ( !isset($_REQUEST['hostname'])) {
    die("Need to supply hostname");
}


$conf['remote_exe'] = basename ( __FILE__ );

###############################################################################
# Test needs to be executed locally
if ( !isset($_REQUEST['site_id']) || $_REQUEST['site_id'] == -1 ) {

  $results = get_dns_record_with_timing($_REQUEST['hostname']);
  
  # Return JSON response
  if ( isset($_REQUEST['json']) && $_REQUEST['json'] == 1 ) {    
    header('Content-type: application/json');
    print json_encode($results);
    exit(1);
  } else {
    $myresults["-1"] = $results;
    print_dns_results($myresults);
  }

} else if ( $site_id == -100 ) {

  $mh = curl_multi_init();

  // Get results from all remotes         
  foreach ( $conf['remotes'] as $id => $remote ) {

    $url = $remote['base_url'] . $conf['remote_exe'] . "?json=1&site_id=-1&hostname=" . htmlentities($_REQUEST['hostname']);
    $url_parts = parse_url($url);
    $curly[$id] = curl_init();    
    curl_setopt($curly[$id], CURLOPT_HEADER, 1);
    curl_setopt($curly[$id], CURLOPT_TIMEOUT, 4);
    curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);
    switch ( $url_parts['scheme'] ) {
	case "http":
	  curl_setopt($curly[$id], CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
	  curl_setopt($curly[$id], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP);
	  break;
	case "https":
	  curl_setopt($curly[$id], CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
	  curl_setopt($curly[$id], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
	  break;
	default:
	  die("<h3>Invalid protocol supplied. You need either http:// or https://</h3>");
    } 
    
    curl_setopt($curly[$id], CURLOPT_ENCODING , "gzip"); 
    curl_setopt($curly[$id], CURLOPT_URL, $url);
    # Disable SSL peer verify ie. don't check remote side SSL certificates
    if ( ! $conf['ssl_peer_verify'] ) {
      curl_setopt($curly[$id], CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($curly[$id], CURLOPT_SSL_VERIFYHOST, FALSE); 
      curl_setopt($curly[$id], CURLOPT_VERBOSE , TRUE);
    }
    curl_multi_add_handle($mh, $curly[$id]);
  }
  
  // execute the handles
  $running = null;
  do {
    curl_multi_exec($mh, $running);
  } while($running > 0);

  $results = array();
  
  foreach($curly as $id => $c) {
    
    if(curl_errno($c)) {
      print "<h3>" . curl_error($c) . "</h3>";
    }
    
    $response = curl_multi_getcontent($c);
    
    if ( $response != "" ) {
      list($header, $content) = explode("\r\n\r\n", $response);
      $results[$id] = json_decode($content, TRUE);
    }
    
  }
  
  #print "<PRE>"; print_r($results);
  print_dns_results($results);

} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {

  $content = file_get_contents($conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?json=1&site_id=-1" .
    "&hostname=" . htmlentities($_REQUEST['hostname'] ));

  $results[$site_id] = json_decode($content, TRUE);
  
  print_dns_results($results);

} else {
    die("No valid site_id supplied");
}


?>
