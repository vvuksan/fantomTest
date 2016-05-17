<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

# Identify whether PhantomJS exists version
if ( is_executable($conf['phantomjs_bin']) ) {

    if ( preg_match("/^2/", exec($conf['phantomjs_bin'] . " -v")) ) {
    $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff-v2.js";
    } else {
    $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff.js";
    }

    $waterfall_output = true;

} else {

    $waterfall_output = false;

}

if ( $conf['pingmtr_enabled'] and is_executable($conf['ping_bin']) and is_executable($conf['ping_bin']) ) {
    $pingmtr_enabled = true;
} else {
    $pingmtr_enabled = false;
}

if ( is_executable($conf['nmap_bin']) ) {
    $tlsciphers_enabled = true;
} else {
    $tlsciphers_enabled = false;
}


# Include user-defined function if they exist.
if( file_exists( $base_dir . "/override_functions.php" ) ) {
  include_once $base_dir . "/override_functions.php";
}

// Let's make sure there is http at the front of a URL
function validate_url($url) {
    if ( !preg_match("/^http/", $url) )
        $url = "http://" . $url;
 
    $validated_url = filter_var($url, FILTER_VALIDATE_URL);
    return $validated_url;
    
}

//////////////////////////////////////////////////////////////////////////////
// Generate waterfall chart from HAR (HTTP Archive file)
//////////////////////////////////////////////////////////////////////////////
function generate_waterfall($har) {

    # This variable will keep the start time of the whole request chain.
    $min_start_time = 10000000000;
    
    # When did the page load finish
    $max_end_time = 0;
    
    foreach ( $har['log']['entries'] as $key => $request ) {
        
        $started_time = $request['startedDateTime'];
        $request_duration = $request['time'] / 1000;
        $url = $request['request']['url'];
        $resp_code = intval($request['response']['status']);
        $resp_size = floatval($request['response']['content']['size']);
        
        // Extract the milliseconds since strtotime doesn't seem to retain it
        preg_match("/(.*)T(.*)\.(.*)(Z)/", $started_time, $out);
        $milli = $out[3];
    
        $start_time = floatval(strtotime($started_time) . "." . $milli);
        $end_time = $start_time + $request_duration;
    
        # Trying to find the start time of the first request
        if ( $start_time < $min_start_time )
            $min_start_time = $start_time;
    
        # Find out when the last request ended
        if ( $end_time > $max_end_time )
            $max_end_time = $end_time;

        foreach ( $request['request']['headers'] as $index => $header ) {
            $req_headers[$header['name']] = $header['value'];
        }        
        foreach ( $request['response']['headers'] as $index => $header ) {
            $resp_headers[$header['name']] = $header['value'];
        }
            
        $requests[] = array("url" => $url, "start_time" => $start_time,
            "duration" => $request_duration, "size" => $resp_size, "resp_code" => $resp_code,
            "req_headers" => $req_headers, "resp_headers" => $resp_headers );

        unset($req_headers, $resp_headers);
        
    }

    // If min_start_time is unchanged from original there was an error and
    // HAR file was invalid.
    if ( $min_start_time == 10000000000 ) {
        print "<h1>Error</h1><p><PRE>";
        print_r($har);
        exit(1);
    }
    
    # Total time to fetch the page and all resources
    $total_time = $max_end_time - $min_start_time;

    
    
    $haroutput = '
    <button id="show_all_headers_button" onClick="$(\'.http_headers\').toggle(); return false">Show Headers for all requests</button>
    <style>
    .compressed_yes, .compressed_no, .compressed_none {
      color: white;
      border: none;
      text-decoration: none;
      display: inline-block;
      font-size: 5px;
    }
    .compressed_yes {
      background-color: green;
    }
    .compressed_no {
      font-weight: 900;
      background-color: red;
    }
    .compressed_none {
      background-color: blue;
    }
    </style>
    <table class="harview">
    <tr>
    <td colspan=5 align=center>
    Total time for a fully downloaded page is <span id="total-time">' . sprintf("%.3f", $total_time) . '</span> sec
    </td>
    </tr>
        <tr>
            <th>#</th>
            <th width=50%>URL</th>
            <th>Server</th>
            <th>Hit?</th>
            <th>Resp Code</th>
            <th>Dur</th>
            <th>Size (bytes)</th>
            <th></th>
        </tr>'
    ;
    
    foreach ( $requests as $key => $request ) {
    
        $time_offset = $request["start_time"] - $min_start_time;

        $white_space = round(($time_offset / $total_time) * 100);
        $progress_bar = round(($request["duration"] / $total_time) * 100);

        $haroutput .= "\n<tr class='response_" . $request["resp_code"] . "'>";
        $haroutput .= "<td>" . $key . "</td>";

        # Output the request url but shrink the screen output to 50 characters
        $haroutput .= "<td><a href='" . $request["url"] . "'>" . substr($request["url"],0,50) . "</a>";

        $content_encoding = false;
        $content_type = false;

        # Add button that toggles response headers
        $haroutput .= '
        <button class="header_button" onClick="$(\'#item_' . $key . '\').toggle(); return false">hdrs</button>
        <div class="http_headers" style="display: none;" id="item_' . $key .'">';
        foreach ( $request['resp_headers'] as $key => $value ) {
          if ( strtolower($key) == "content-type" ) {
            $content_type_full = $value;
          }

          if ( strtolower($key) == "content-encoding" ) {
            $content_encoding = $value;
          }

          $haroutput .= "<b>" . htmlentities($key) . "</b>: " . htmlentities($value) . "<br />";
        }

        $haroutput .= "</div>";

        $compressable = false;
        if ( preg_match("/text\/html/i", $content_type_full ) ) {
          $content_type = "HTML";
          $compressable = true;
        } else if ( preg_match("/text\/css/i", $content_type_full ) ){
          $content_type = "CSS";
          $compressable = true;
        } else if ( preg_match("/javascript|text\/js/i", $content_type_full ) ){
          $content_type = "JS";
          $compressable = true;
        } else if ( preg_match("/image\/gif/i", $content_type_full ) ) {
          $content_type = "GIF";
        } else if ( preg_match("/image\/png/i", $content_type_full ) ) {
          $content_type = "PNG";
        } else if ( preg_match("/image\/jpeg/i", $content_type_full ) ) {
          $content_type = "JPG";
        } else if ( preg_match("/json/i", $content_type_full ) ) {
          $content_type = "JSON";
          $compressable = true;
        } else if ( preg_match("/image\/webp/i", $content_type_full ) ) {
          $content_type = "WEBP";
          $compressable = true;
        } else if ( preg_match("/svg/i", $content_type_full ) ) {
          $content_type = "SVG";
          $compressable = true;
        } else if ( preg_match("/font|woff/i", $content_type_full ) ){
          $content_type = "FONT";
          $compressable = true;
        } else if ( preg_match("/text\/plain/i", $content_type_full ) ){
          $content_type = "TXT";
          $compressable = true;
        } else if ( preg_match("/octet/i", $content_type_full ) ){
          $content_type = "BIN";
        } else if ( preg_match("/xml/i", $content_type_full ) ){
          $content_type = "XML";
          $compressable = true;
        } else {
          $content_type = "";
        }

        $addl = "";
        # Check if content type is compressable and only on 2k+ files
        if ( $request["size"] > 2048 && $compressable ) {
          if ( $content_encoding ) {
            $compressed = "yes";
            $addl = "title=\"Compressed\"";
          } else {
            $compressed = "no";
            $addl = "title=\"Not compressed however should be compressable\"";
          }
        } else {
          $compressed = "none";
          $addl = "title=\"Not compressable\"";
        }

        if ( $content_type != "" )
          $haroutput .= " <button $addl class=\"compressed_" . $compressed ."\">". $content_type . "</button>";

        $haroutput .= "</td>";

        # Let's see if we can find any Cache headers and can identify whether request was a HIT or a MISS
        if ( isset($request['resp_headers']['X-Cache']) ) {
          $hit_or_miss = $request['resp_headers']['X-Cache'];
          if ( preg_match("/(TCP_HIT|TCP_MEM_HIT|HIT$)/i", $request['resp_headers']['X-Cache'] )) {
            $hit_or_miss_css = "HIT";
          } else {
            $hit_or_miss_css = "MISS";
          }
        } else {
          $hit_or_miss_css = "UNK";
          $hit_or_miss = "UNK";
        }

        # 
        $server = "";

        # Let's try to identify some CDNs. This is Fastly
        if ( isset($request['resp_headers']['X-Served-By']) && preg_match("/^cache-/", $request['resp_headers']['X-Served-By']) ) {
            $server = str_replace("cache-", "", $request['resp_headers']['X-Served-By']);
        }

        # Check if Server header provided. It's used by NetDNA and Edgecast
          else if ( isset($request['resp_headers']['Server']) && preg_match("/^EC/", $request['resp_headers']['Server'])  ) {
            $server = trim(str_replace("ECS", "Edgecast", $request['resp_headers']['Server']));

        } else if ( isset($request['resp_headers']['Server']) && preg_match("/^NetDNA/", $request['resp_headers']['Server']) ) {
            $server = trim($request['resp_headers']['Server']);
        # CloudFront
        } 
        else if ( isset($request['resp_headers']['Via']) && preg_match("/CloudFront/", $request['resp_headers']['Via']) ) {
            $server = "CloudFront";
        # ChinaCache
        } 
        else if ( isset($request['resp_headers']['Powered-By-ChinaCache']) ) {
            $server = "ChinaCache";
        # Incapsula
        }
        else if ( isset($request['resp_headers']['X-Instart-Request-ID']) ) {
            $server = "Instart";
        }
        else if ( isset($request['resp_headers']['X-CDN']) and $request['resp_headers']['X-CDN'] == "Incapsula" ) {
            $server = "Incapsula";
        }
        # CD Networks
        else if ( isset($request['resp_headers']['X-Px']) ) {
            $server = "CDNetworks";
        }
        # Cloudflare
        else if ( isset($request['resp_headers']['CF-RAY']) ) {
            $server = "CF: " . preg_replace('/^(.*)-/', '', $request['resp_headers']['CF-RAY']);
	    $hit_or_miss_css = $request['resp_headers']['CF-Cache-Status'];
	    $hit_or_miss = $request['resp_headers']['CF-Cache-Status'];
        }
        # Highwinds
        else if ( isset($request['resp_headers']['X-HW']) ) {
            $server = "HW " . preg_replace("/\d+\.(.*),\d+\.(.*)/", "$1, $2", $request['resp_headers']['X-HW']);
	    $hit_or_miss_css = "HIT";
	    $hit_or_miss = "HIT";
        }
        # Match Akamai headers
        else if ( isset($request['resp_headers']['X-Cache']) && preg_match("/(\w+)(\s+).*akamai/i", $request['resp_headers']['X-Cache'], $out) ) {
            $server = "Akamai";
            $hit_or_miss = $out[1];
        # Not exhaustive way to identify Google
        } else if ( preg_match("/google.*\.com\//i", $request["url"]) ) {
            $server = "Google";
        # Not exhaustive way to identify Facebook 
        } else if ( preg_match("/facebook.*\.com\//i", $request["url"]) ) {
            $server = "Facebook";
        } else if ( preg_match("/s3.*amazonaws/i", $request["url"]) ) {
            $server = "AWS S3";
        } else if ( preg_match("/bing\.com\//i", $request["url"]) ) {
            $server = "MS Bing";
        } else if ( isset($request['resp_headers']['X-Varnish']) || isset($request['resp_headers']['Via']) && preg_match("/varnish/i", $request['resp_headers']['Via']) ) {
            $server = "Varnish";
        }

        ##############################################################################################
        # Figure out if a specific CMS is being used
        if ( isset($request['resp_headers']['X-AH-Environment']) ) {
            $server .= " (Acquia)";
        } else if ( isset($request['resp_headers']['X-Drupal-Cache']) ) {
            $server .= " (Drupal)";
        # Magento version 1
        } else if ( isset($request['resp_headers']['Link']) && preg_match("/wp-json/", $request['resp_headers']['Link'])) {
            $server .= " (Wordpress)";
        } else if ( isset($request['resp_headers']['Set-Cookie']) && preg_match("/frontend=/i", $request['resp_headers']['Set-Cookie'] ) ) {
            $server .= " (Magento1)";
        }

        if ( $server == "" )
            $server = "Unknown";

        $haroutput .= '<td>' . $server . '</td>' .
        '<td class="x-cache-' . $hit_or_miss_css . '">' . $hit_or_miss . '</td>' .
        '<td>' . $request["resp_code"] . '</td>
        <td align="right">' . number_format($request["duration"], 3) . '</td>
        <td align="right">' . $request["size"] . '</td>
        <td class="timeline-data"><span class="bar">' .
        '<span class="fill" style="background: white; width: ' . $white_space .  '%">&nbsp;</span>'.
        '<span class="fill" style="background: #AAB2FF; width: ' . $progress_bar .  '%">&nbsp;</span>'.
        "</span></td></tr>";
    
    }
    
    unset($requests);
    unset($har);
    
    $haroutput .= '</table>
    <script>

    $(function(){
      $(".header_button").button();
    });
    ';

    return $haroutput;

} // end of function generate_waterfall()

//////////////////////////////////////////////////////////////////////////////
// Use Phantom JS to produce a JSON containing the HTTP archive and the
// Image
//////////////////////////////////////////////////////////////////////////////
function get_har_using_phantomjs($original_url, $include_image = true) {

    global $conf;
    
    $url = validate_url($original_url);
    
    if ( $url === FALSE ) {
        print json_encode( array( "error" => "URL is not valid" ) );
        exit(1);
    }

    ///////////////////////////////////////////////////////////////////////////
    // Can't supply suffix for the temp file therefore we'll first create the
    // tempname then rename it with .png extension since that is what PhantomJS
    // expects
    $tmpfname1 = tempnam("/tmp", "phantom");
    $tmpfname = $tmpfname1 . ".png";
    rename($tmpfname1, $tmpfname);    
    
    $command = $conf['phantomjs_exec'] . " '" . $url . "' " . $tmpfname;
    if ( $conf['debug'] == 1 )
      error_log($command);
    exec($command, $output_array, $ret_value);

    // For some reason you may get DEBUG statements in the output e.g.  ** (:32751): DEBUG: NP_Initialize\
    // Let's get rid of them. Look for first occurence of {
    foreach ( $output_array as $key => $line ) {
        if ( preg_match("/^{/", $line) ) {
            break;
        } else
            $output_array[$key] = "";

    }

    # Same thing at the end we may end up with stuff like this
    # Unsafe JavaScript attempt to access frame with URL about:blank from frame
    $found_closing_curly_brace = false;

    foreach ( $output_array as $key => $line ) {
        if ( ! $found_closing_curly_brace && preg_match("/^}/", $line) ) {
            $found_closing_curly_brace = 1;
        } else if ( $found_closing_curly_brace )
            $output_array[$key] = "";
    }

    ////////////////////////////////////////////////////////////////////////////
    // Phantom JS exited normally. It doesn't mean URL properly loaded just
    // that Phantom didn't fail for other reasons ie. can't execute
    ////////////////////////////////////////////////////////////////////////////
    if ( $ret_value == 0 ) {
        $output = join("\n", $output_array);
        $har = json_decode($output, TRUE);

        // If har_array is null JSON could not be parsed
        if ( $har === NULL ) {

           $out = array( "success" => 0, "error_message" => "PhantomJS ran successfully however output couldn't be parsed.");

        } else {

            if ( filesize($tmpfname) != 0 && $include_image )
              $imgbinary = base64_encode(fread(fopen($tmpfname, "r"), filesize($tmpfname)));
            else
              $imgbinary = false;
            unlink($tmpfname);

            $out = array ( "har" => $har, "screenshot" => $imgbinary, "success" => 1 );
            
        }

        // If har_array is null JSON could not be parsed        
        return $out;
        
    } else {
        
        return array( "success" => 0, "error_message" => "PhantomJS exited abnormally. Please check your webserver error log" );
    }

}

#############################################################################################
# Get DNS record
#############################################################################################
function get_dns_record_with_timing($dns_name, $query_type = "A") {

  $start_time = microtime(TRUE);
  switch ( $query_type ) {
    case "A":
      $record_type = DNS_A;
      break;
    case "CNAME":
      $record_type = DNS_CNAME;
      break;
    case "AAAA":
      $record_type = DNS_AAAA;
      break;
    case "MX":
      $record_type = DNS_MX;
      break;
    case "SOA":
      $record_type = DNS_SOA;
      break;
    case "TXT":
      $record_type = DNS_TXT;
      break;
    default:
      $record_type = DNS_A;

  }

  $result = dns_get_record($dns_name, $record_type);
  # Calculate query time
  $query_time = microtime(TRUE) - $start_time;
  
  $resolver_ip_record = dns_get_record("whoami.akamai.net", DNS_A);
  $resolver_ip = isset($resolver_ip_record[0]['ip']) ? $resolver_ip_record[0]['ip'] : "Unknown";
  
  return array( "records" => $result,
    "query_time" => $query_time,
    "resolver_ip" => $resolver_ip );

}


############################################################################################
# Format IP address to print out
############################################################################################
if ( !function_exists("format_ip_address") ) {

    function format_ip_address($ip) {
        return($ip);
    }

}

#############################################################################################
# Get DNS record
#############################################################################################
function print_dns_results($results) {
  
  global $conf;

  if ( count($results) > 0 ) {

    # Find max time
    $max_time = 0;

    foreach ( $results as $site_id => $result ) {
      if ( $result['query_time'] > $max_time )
        $max_time = $result['query_time'];
    }
    ?>

  <table border=1 class=tablesorter>
    <thead><tr>
      <th>Site Name</th>
      <th>Hostname</th>
      <th>Resolver IP</th>
      <th>TTL</th>
      <th>Type</th>
      <th>Record</th>
      <th>Query Time (ms)</th>
      </tr>
    </thead>
    <tbody>
    <style>
    .dns_bar {
      background: #00FF7F
    }
    </style>
 <?php
    foreach ( $results as $site_id => $result ) {

      if ( $site_id == "-1")
        $site_name = "Local";
      else
        $site_name = $conf['remotes'][$site_id]['name'];

      if ( ! isset($result['records']) or count($result['records']) == 0 ) {
        print "<tr>
          <td>" . $site_name . "</td>
          <td>No Results</td>
          <td>NA</td>
          <td>NA</td>
          <td>NA</td>
          <td>No Results</td>
          <td align=right>0</td></tr>";
        continue;
      }

      $query_time_in_ms = round($result['query_time'] * 1000);
      $resolver_ip = preg_match("/^74.125/", $result['resolver_ip']) ? $result['resolver_ip'] . " - Google DNS" : $result['resolver_ip'];

      foreach( $result['records'] as $index => $record ) {
        switch ( $record['type'] ) {
          case "A":
            $record_output = format_ip_address($record['ip']);
            break;
          case "AAAA":
            $record_output = $record['ipv6'];
            break;
          case "CNAME":
            $record_output = $record['target'];
            break;
          case "TXT":
            $record_output = $record['txt'];
            break;
          case "MX":
            $record_output = $record['pri'] . " " . $record['target'];
            break;
          case "SOA":
            $record_output = $record['mname'] . " <i>" . $record['rname']
              . "</i> Serial: " . $record['serial'] . " Rfrsh: " . $record['refresh']
              . " Retry/NegTTL: " . $record['retry'] . " Expire: " . $record['expire']
              . " MinTTL: " . $record['minimum-ttl'];
            break;
          default:
            $record_output = "No data. Maybe query type is unknown";
        }


        print "<tr>
          <td>" . $site_name . "</td>
          <td>" . $record['host'] . "</td>
          <td>" . $resolver_ip . "</td>
          <td align=right>" . $record['ttl'] . "</td>
          <td>" . $record['type'] . "</td>
          <td>" . $record_output . "</td>
          ";
        print "<td><span class=\"curl_bar\">";
        print '<span class="fill dns_bar" style="width: ' . floor(100 * $result['query_time']/$max_time) .  '%">' .$query_time_in_ms .'</span>';
        print "</span></td>";
        print "</tr>";
      }
    }
?>
    </tbody>
  </table>
  <script>
    $("table").tablesorter();
  </script>
<?php

  }
  
}

#############################################################################################
# Get Curl timings
#############################################################################################
function get_curl_timings_with_headers($original_url, $request_headers = array()) {

    $url = validate_url($original_url);
    
    if ( $url === FALSE ) {
        print json_encode( array( "error" => "URL is not valid" ) );
        exit(1);
    }
    
    $url_parts = parse_url($url);

    $curly = curl_init();    
    curl_setopt($curly, CURLOPT_HEADER, 1);
    curl_setopt($curly, CURLOPT_TIMEOUT, 4);
    curl_setopt($curly, CURLOPT_RETURNTRANSFER, 1);
    switch ( $url_parts['scheme'] ) {
	case "http":
    	  curl_setopt($curly, CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
          curl_setopt($curly, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP);
          break;
        case "https":
          curl_setopt($curly, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
          curl_setopt($curly, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
	  break;
        default:
          die("<h3>Invalid protocol supplied. You need either http:// or https://</h3>");
    } 
    
    curl_setopt($curly,CURLOPT_ENCODING , "gzip"); 
    curl_setopt($curly, CURLOPT_HTTPHEADER, $request_headers );
    curl_setopt($curly, CURLOPT_URL, $url);
    
    curl_exec($curly);
    
    $response = curl_multi_getcontent($curly);
    
    if(curl_errno($curly)) {
        $results = array("return_code" => 400, "response_size" => 0, "content_type" => "none", "error_message" =>  curl_error($curly) );
    } else {
      list($header, $content) = explode("\r\n\r\n", $response);
      
      $info = curl_getinfo($curly);
      $results = array(
	  "return_code" => $info['http_code'],
	  "error_message" => "",
	  "content_type" => $info['content_type'],
	  "response_size" => $info['size_download'],
	  "header_size" => $info['header_size'],
	  "headers_string" => $header,
	  "md5" => md5($content),
          "dns_lookuptime" => $info['namelookup_time'],
	  "connect_time" => $info['connect_time'] - $info['namelookup_time'],
	  "pretransfer_time" => $info['pretransfer_time'] - $info['connect_time'],
	  "starttransfer_time" => $info['starttransfer_time'] - $info['pretransfer_time'],
          "transfer_time" =>  $info['total_time'] - $info['starttransfer_time'],
	  "total_time" => $info['total_time'],
          "primary_ip" => isset($info['primary_ip']) ? $info['primary_ip']: "Not avail"
	  );
    }
    
    curl_close($curly);
    return $results;
  
}

#############################################################################################
# Print cURL results
#############################################################################################
function print_url_results($records) {
  
  global $conf;
  
  print "<div class=\"time_legend\">";
  print '<span class="fill dns_time" style="width: 10%">DNS time</span>';
  print '<span class="fill conn_time" style="width: 10%">Conn Time</span>';
  print '<span class="fill request_time" style="width: 10%">Request Sent</span>';
  print '<span class="fill time_to_first_byte" style="width: 10%">TTFB wait</span>';
  print '<span class="fill transfer_time" style="width: 10%">Transfer Time</span>';
  print "</div><br />";
  
  print "<table border=1 class=tablesorter>
  <thead>
  <tr><th>Remote</th><th>Resolved IP</th>";

  if ( $conf['cdn_detection'] ) {
    print "  <th>X-Served-By</th>
      <th>Cache Hit?</th>";
  }
  
  print "    <th>Gzip</th>
      <th>HTTP code</th>
      <th>Resp size</th>
      <th>Hdr size</th>
      <th>DNS time</th>
      <th>Connect Time</th><th>Request Sent</th>
      <th>Time to First Byte</th><th>Tx Time</th><th>Total Time</th></tr>
  </thead><tbody>";
  
  foreach ( $records as $id => $record ) {
    # Try to identify CDNs    
    if ( $conf['cdn_detection'] ) {

      if ( preg_match("/.*X-Served-By: (.*)\n/", $record['headers_string'], $out) ) {
        $xservedby = $out[1];        
      } else {
        $xservedby = "NA";
      }
  
      if ( preg_match("/.*X-Cache: (.*)\n/", $record['headers_string'], $out) ) {
        $cache_hit = trim($out[1]);        
      } else {
        $cache_hit = "NA";
      }

    }

    if ( $id == -1 ) {
      $site_name = "Local";
    } else {
      $site_name = $conf['remotes'][$id]['name'];

    }
    print "<tr><td rowspan=2>" . $site_name;

    print "<div id='remote_" . $id .  "' >".
   "<button class=\"http_headers\" onClick='$(\"#url_results_" . $id .  "\").toggle(); return false;'>Headers</button></div>";

    print "<div id='url_results_" . $id .  "' style=\"display: none;\">";
    print "<pre>" . htmlentities($record['headers_string']) ;
    print "</pre></div>";

    print "</td>";

    $gzip = preg_match("/Content-Encoding: gzip/i", $record['headers_string']) ? "Yes" : "No";

    $cache_hit_styling = preg_match("/HIT$/", $cache_hit ) ? "x-cache-HIT" : "x-cache-MISS";

    print "<td rowspan=2>" . $record['primary_ip'] . "</td>";
    if ( $conf['cdn_detection'] ) {
      print "<td rowspan=2 class=cache_servers>" . $xservedby . "</td>" .
        "<td rowspan=2 class='" . $cache_hit_styling . "'>" . $cache_hit . "</td>";
    }
    print "<td rowspan=2 class='" . strtolower($gzip) . "-gzip'>" . $gzip . "</td>" .
        "<td rowspan=2>" . $record['return_code'] . "</td>" .
        "<td class=number>" . $record['response_size'] . "</td>" .
        "<td class=number>" . $record['header_size'] . "</td>" .
        "<td class=number>" . number_format($record['dns_lookuptime'],3) . "</td>" .
        "<td class=number>" . number_format($record['connect_time'],3) . "</td>" .
        "<td class=number>" . number_format($record['pretransfer_time'], 3) . "</td>" .
        "<td class=number>" . number_format($record['starttransfer_time'], 3) . "</td>" .
        "<td class=number>" . number_format($record['transfer_time'], 3) . "</td>" .
        "<td class=number>" . number_format($record['total_time'], 3) . "</td>".
        "</tr>";

    # Make the bar graph of response
    print "<tr><td colspan=8>";
    print "<span class=\"curl_bar\">";
    print '<span class="fill dns_time" style="width: ' . floor(100 * $record['dns_lookuptime']/$record['total_time']) .  '%">&nbsp;</span>';
    print '<span class="fill conn_time" style="width: ' . floor(100 * $record['connect_time']/$record['total_time']) .  '%">&nbsp;</span>';
    print '<span class="fill request_time" style="width: ' . floor(100 * $record['pretransfer_time']/$record['total_time']) .  '%">&nbsp;</span>';
    print '<span class="fill time_to_first_byte" style="width: ' . floor(100 * $record['starttransfer_time']/$record['total_time']) .  '%">&nbsp;</span>';
    print '<span class="fill transfer_time" style="width: ' . floor(100 * $record['transfer_time']/$record['total_time']) .  '%">&nbsp;</span>';
    print "</span>";

    print "</td></tr>";
    
  } // foreach ( $records as $record ) { 

  print "</tbody></table>";

}

?>
