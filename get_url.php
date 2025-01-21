<?php

header("cache-control: private, s-maxage=2");

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";
require_once $base_dir . "/tools.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

if ( isset($conf['cors_headers_acao']) ) {
  header($conf['cors_headers_acao']);
}

$request = array();

$conf['remote_exe'] = basename ( __FILE__ );

# Was this supplied x-www-url-encoded
if ( preg_match("/^application\/json/", $_SERVER["HTTP_CONTENT_TYPE"]) || count($_REQUEST) == 0 ) {
  $my_req = json_decode(file_get_contents('php://input'), TRUE);
} else {
  $my_req = $_REQUEST;
}

if ( count($my_req) > 0 ) {

  $request['site_id'] = isset($my_req['site_id']) && is_numeric($my_req['site_id']) ? $my_req['site_id'] : -1;
  $site_id = $request['site_id'];
  if ( isset($my_req['timeout']) && is_numeric($my_req['timeout']) and $my_req['timeout'] < 120 ) {
    $request['timeout'] = $my_req['timeout'];
  } else {
    $request['timeout'] = 60;
  }

  if ( isset($my_req['protocol']) && $my_req['protocol'] == "http1.1" ) {
    $request['protocol'] = "http1.1";
  } else {
    $request['protocol'] = "http2";
  }

  if ( isset($my_req['arbitrary_headers']) and $my_req['arbitrary_headers'] != "" ) {
    # We need to make sure once we explode around || there are no spaces since that
    # causes curl to barf
    $temp_array = explode("||", htmlentities($my_req['arbitrary_headers']));
    foreach ( $temp_array as $header ) {
      $request['request_headers'][] = trim($header);
    }
  } else if ( isset($my_req['request_headers']) ) {
    $request['request_headers'] = $my_req['request_headers'];
  } else {
    $request['request_headers'] = array();
  }

  ######################################################################
  # Default override IP to nothing
  ######################################################################
  $override_ip = "";

  # Let's verify that the override IP is actually a resolvable DNS name or an IP
  if ( isset($my_req['override_ip']) ) {
    $ip_input = trim($my_req['override_ip']);
    if(filter_var($ip_input, FILTER_VALIDATE_IP)) {
      $override_ip = $ip_input;
    } else {
      $override_ip = gethostbyname($ip_input);
      # If resolution fails it just returns hostname back. Reset override_ip back
      if ( $override_ip == $ip_input )
        $override_ip ="";
      }
  }

  if ( $override_ip != "" ) {
    $request['override_ip'] = $override_ip;
  }

  ######################################################################
  # Default override IP to nothing
  ######################################################################
  if ( $conf['allow_proxy_for_url_check'] && isset($my_req['http_proxy']) ) {
    $request['http_proxy'] = $my_req['http_proxy'];
  }

  ######################################################################
  # Check we got one of the allowable methods. Otherwise default to GET
  ######################################################################
  if ( isset($my_req['method']) && in_array($my_req['method'], $conf['allowed_http_methods']) ) {
    $request['method'] = $my_req['method'];
  } else {
    $request['method'] = "GET";
  }

  ######################################################################
  # Set the payload if it exists
  ######################################################################
  if ( isset($my_req['payload']) ) {
    $request['payload'] = $my_req['payload'];
  } else {
    $request['payload'] = "";
  }

  if ( isset($my_req['url-content-type']) ) {
    $request['request_headers'][] = "Content-Type: " . $my_req['url-content-type'];
  }

  $request['url'] = trim($my_req['url']);
}

if ( $request['site_id'] == -1 ) {

    $record = get_curl_timings_with_headers($request);

    if ( isset($my_req['json']) && $my_req['json'] == 1 ) {
      header('Content-type: application/json');
      print json_encode($record);
      exit(1);
    }

    $results = array();
    $results["-1"] = $record;
    print_url_results($results, $request);

} else if ( $request['site_id'] == -100 ) {

    $mh = curl_multi_init();

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $id => $remote ) {

      $args[] = "json=1";
      $args[] = "site_id=-1";
      $args[] = "url=" . htmlentities($my_req['url']);
      $args[] = "arbitrary_headers=" . htmlentities($my_req['arbitrary_headers']);

      $url = $remote['base_url'] . $conf['remote_exe'] . "?" . join("&", $args);
      $url_parts = parse_url($url);
      $curly[$id] = curl_init();    
      curl_setopt($curly[$id], CURLOPT_HEADER, 1);
      curl_setopt($curly[$id], CURLOPT_TIMEOUT, $request['timeout']);
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
    
    $url = $conf['remotes'][$site_id]['base_url'] . "get_url.php?json=1&site_id=-1&url=" . htmlentities($my_req['url']);
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
});
</script>
