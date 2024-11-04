<?php

header("Cache-Control: private, s-maxage=2");

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";
require_once $base_dir . "/tools.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

$conf['remote_exe'] = basename ( __FILE__ );

$site_id = is_numeric($_REQUEST['site_id']) ?$_REQUEST['site_id'] : -1;
$timeout = isset($_REQUEST['timeout']) && is_numeric($_REQUEST['timeout']) and $_REQUEST['timeout'] < 120  ? $_REQUEST['timeout'] : 60;

if ( isset($_REQUEST['arbitrary_headers']) and $_REQUEST['arbitrary_headers'] != "" ) {
  $optional_request_headers = explode("||", htmlentities($_REQUEST['arbitrary_headers']));
} else {
  $optional_request_headers = array();
}

######################################################################
# Default override IP to nothing
######################################################################
$override_ip = "";

if ( isset($_REQUEST['override_ip_or_hostname']) ) {
  $ip_input = trim($_REQUEST['override_ip_or_hostname']);
  if(filter_var($ip_input, FILTER_VALIDATE_IP)) {
	$override_ip = $ip_input;
  } else {
	$override_ip = gethostbyname($ip_input);
	# If resolution fails it just returns hostname back. Reset override_ip back
	if ( $override_ip == $ip_input )
	  $override_ip ="";
  }
}

######################################################################
# Check we got one of the allowable methods. Otherwise default to GET
######################################################################
if ( isset($_REQUEST['method']) && in_array($_REQUEST['method'], $conf['allowed_http_methods']) ) {
  $method = $_REQUEST['method'];
} else {
  $method = "GET";
}

######################################################################
# Set the payload if it exists
######################################################################
if ( isset($_REQUEST['payload']) ) {
  $payload = $_REQUEST['payload'];
} else {
  $payload = "";
}

if ( isset($_REQUEST['url-content-type']) ) {
  $optional_request_headers[] = "Content-Type: " . $_REQUEST['url-content-type'];
}


if ( $_REQUEST['site_id'] == -1 ) {

    $record = get_curl_timings_with_headers($method, trim($_REQUEST['url']), $optional_request_headers, $override_ip, $payload);

    if ( isset($_REQUEST['json']) && $_REQUEST['json'] == 1 ) {
      header('Content-type: application/json');
      print json_encode($record);
      exit(1);
    }

    $results = array();
    $results["-1"] = $record;
    print_url_results($results);    

} else if ( $site_id == -100 ) {

    $mh = curl_multi_init();

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $id => $remote ) {

      $args[] = "json=1";
      $args[] = "site_id=-1";
      $args[] = "url=" . htmlentities($_REQUEST['url']);
      $args[] = "arbitrary_headers=" . htmlentities($_REQUEST['arbitrary_headers']);

      $url = $remote['base_url'] . $conf['remote_exe'] . "?" . join("&", $args);
      $url_parts = parse_url($url);
      $curly[$id] = curl_init();    
      curl_setopt($curly[$id], CURLOPT_HEADER, 1);
      curl_setopt($curly[$id], CURLOPT_TIMEOUT, $timeout);
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
    
    print_url_results($results);
    
} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    
    $url = $conf['remotes'][$site_id]['base_url'] . "get_url.php?json=1&site_id=-1&url=" . htmlentities($_REQUEST['url']);
    $sslOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false));

    $results[$site_id] = json_decode( file_get_contents($url, FALSE, stream_context_create($sslOptions)) , TRUE );
    print_url_results($results);
    
} else {
    die("No valid site_id supplied");
}
?>
<script>
$(function(){

    $(".http_headers").button();
    $("table").tablesorter();

});
</script>
