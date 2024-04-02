<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

# Identify whether PhantomJS exists version
if ( !isset($conf['phantomjs_exec']) )  {

  if ( is_executable($conf['phantomjs_bin']) ) {

    if ( preg_match("/^2/", exec($conf['phantomjs_bin'] . " -v")) ) {
    $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " --ignore-ssl-errors=true " . __DIR__ . "/netsniff/netsniff-v2.js";
    } else {
    $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff.js";
    }

    $waterfall_output = true;

  } else {

    $waterfall_output = false;

  }
}


if ( isset($conf['prerender_server_url']) || isset($conf['harrr_server_url']) ||isset($conf['harpoon_server_url'])  ) {
  $waterfall_output = true;  
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
 
  $validated_url = filter_var($url, FILTER_VALIDATE_URL);
    
  # Do another validation by verifying that http/https are specified since URL with junky schemes can be validated
  if ( $validated_url ) {
    $parsed_url = parse_url($validated_url);

    if ( isset($parsed_url['scheme'] ) ) {
      # We only support http and https.
      if ( ! ($parsed_url['scheme'] == "http" || $parsed_url['scheme'] == "https" ) )
        return FALSE;
    } else {
      return FALSE;
    }

  }

  return $validated_url;

}

//////////////////////////////////////////////////////////////////////////////
// Generate waterfall chart from HAR (HTTP Archive file)
//////////////////////////////////////////////////////////////////////////////
function generate_waterfall($har) {

    global $conf;
    # This variable will keep the start time of the whole request chain.
    $min_start_time = 10000000000;
  
    # Read the cache file
    if( isset($conf['cache_file']) && is_readable($conf['cache_file']) && filesize($conf['cache_file']) > 20 ) {
      $cache_age = time() - filemtime($conf['cache_file']);
      if ( $cache_age < $conf['cache_time'] )
        $ip_to_as_cache = json_decode(file_get_contents($conf['cache_file']), TRUE);
    } else {
      $ip_to_as_cache = array();
    }
    
    # When did the page load finish
    $max_end_time = 0;
    
    if ( !isset($har['log']['entries']) ) {
      print "Couldn't retrieve the HTTP Archive";
      exit;
    }

    foreach ( $har['log']['entries'] as $key => $request ) {
        
        $started_time = $request['startedDateTime'];
        $request_duration = $request['time'] / 1000;
        $url = $request['request']['url'];
        $resp_code = intval($request['response']['status']);
        $resp_size = isset($request['response']['content']['size']) ? floatval($request['response']['content']['size']) : 0;
        
        // Extract the milliseconds since strtotime doesn't seem to retain it
        if ( preg_match("/(.*)T(.*)\.(.*)(Z)/", $started_time, $out) ) {
          $milli = $out[3];
        } else {
          $milli = 0;
        }

        $start_time = floatval(strtotime($started_time) . "." . $milli);
        $end_time = $start_time + $request_duration;
    
        # Trying to find the start time of the first request
        if ( $start_time < $min_start_time )
            $min_start_time = $start_time;
    
        # Find out when the last request ended
        if ( $end_time > $max_end_time )
            $max_end_time = $end_time;

        $resp_headers = array();
        $req_headers = array();
        foreach ( $request['request']['headers'] as $index => $header ) {
            $req_headers[$header['name']] = $header['value'];
        }
        foreach ( $request['response']['headers'] as $index => $header ) {
            $header_name = strtolower($header['name']);
            $resp_headers[$header_name] = $header['value'];
        }

        ksort($resp_headers);

        if ( isset($request['serverIPAddress']) ) { 
          $server_ip = $request['serverIPAddress'];
        } else {
          $server_ip = false;
        }

        $requests[] = array(
            "url" => $url,
            "start_time" => $start_time,
            "dns_time" => !isset($request['timings']['dns']) || $request['timings']['dns'] <= 0 ? 0 : $request['timings']['dns'] / 1000,
            "connect_time" => !isset($request['timings']['connect']) || $request['timings']['connect'] <= 0 ? 0 : $request['timings']['connect'] / 1000,
            "ssl_time" => !isset($request['timings']['ssl']) || $request['timings']['ssl'] <= 0 ? 0 : $request['timings']['ssl'] / 1000,
            "wait_time" => !isset($request['timings']['wait']) ? 0 : $request['timings']['wait'] / 1000,
            "download_time" => !isset($request['timings']['receive']) ? 0 : $request['timings']['receive'] / 1000,
            "duration" => $request_duration,
            "size" => $resp_size,
            "resp_code" => intval($resp_code),
            "http_version" => $request['response']['httpVersion'],
            "server_ip" =>  $server_ip,
            "req_headers" => $req_headers,
            "resp_headers" => $resp_headers
            );

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
    
    $content_breakdown = array( "html" => 0,  "images" => 0, "js" => 0, "json" => 0, "css" => 0, "fonts" => 0);
    $websockets_anchors = array();
    
    $haroutput = '<table class="harview">
        <tr>
            <th>#</th>
            <th width=50%>URL</th>
            <th>IP</th>
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
        $dns_time_bar = ceil(($request["dns_time"] / $total_time) * 100);
        $connect_time_bar = ceil(($request["connect_time"] / $total_time) * 100);
        $ssl_time_bar = ceil(($request["ssl_time"] / $total_time) * 100);
        $wait_time_bar = ceil(($request["wait_time"] / $total_time) * 100);
        $download_time_bar = ceil(($request["download_time"] / $total_time) * 100);

        $haroutput .= "\n<tr class='response_" . $request["resp_code"] . "'>";
        if ( $request["resp_code"] == 101 ) {
          $haroutput .= "<td><em id=ws" . $key . ">" . $key . "</em></td>";
          $websockets_anchors[] = "ws" . $key;
        } else {
          $haroutput .= "<td>" . $key . "</td>";
        }

        # Output the request url but shrink the screen output to 50 characters
        $haroutput .= "<td><a href='" . $request["url"] . "'>" . substr($request["url"],0,50) . "</a>";

        $content_encoding = false;
        $content_type = false;

        # Add button that toggles response headers
        $haroutput .= '
        <button class="header_button" onClick="$(\'#item_' . $key . '\').toggle(); return false">more</button>
        <div class="http_headers" style="display: none;" id="item_' . $key .'">';
        $content_type_full = "Unknown";

        $haroutput .= "Browser timing (sec): DNS:" . number_format($request["dns_time"],3) . 
           " CNCT: " . number_format($request["connect_time"], 3) . 
           " TLS: " . number_format($request["ssl_time"], 3) .
           " WAIT: " . number_format($request["wait_time"], 3) . 
           " DNLD: " . number_format($request["download_time"], 3) .
           "<br />";

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

        # If response is HTTP/2 or HTTP/3 put a nice button to identify it
        if ( preg_match("/(h|http)\/?([2-3])/i", $request['http_version'], $out) )
          $haroutput .= " <button title=\"HTTP" . $out[2] . "\" class=\"http" . $out[2] . "\">H" . $out[2] . "</button>";

        $compressable = false;

        if ( preg_match("/text\/html/i", $content_type_full ) ) {
          $content_type = "HTML";
          $compressable = true;
          $content_breakdown["html"] += $request["size"];
        } else if ( preg_match("/text\/css/i", $content_type_full ) ){
          $content_type = "CSS";
          $compressable = true;
          $content_breakdown["css"] += $request["size"];
        } else if ( preg_match("/javascript|text\/js/i", $content_type_full ) ){
          $content_type = "JS";
          $compressable = true;
          $content_breakdown["js"] += $request["size"];
        } else if ( preg_match("/image\/(gif|png|jpeg|avif|webp)/i", $content_type_full, $out ) ) {
          $content_type = strtoupper($out[1]);
          $content_breakdown["images"] += $request["size"];
          unset($out);
        } else if ( preg_match("/json/i", $content_type_full ) ) {
          $content_type = "JSON";
          $content_breakdown["json"] += $request["size"];
          $compressable = true;
        } else if ( preg_match("/svg/i", $content_type_full ) ) {
          $content_type = "SVG";
          $content_breakdown["images"] += $request["size"];
          $compressable = true;
        # WOFF is already compressed https://developers.googleblog.com/2015/02/smaller-fonts-with-woff-20-and-unicode.html
        } else if ( preg_match("/woff/i", $content_type_full ) ){
          $content_type = "FONT";
          $content_breakdown["fonts"] += $request["size"];
        } else if ( preg_match("/font/i", $content_type_full ) ){
          $content_type = "FONT";
          $content_breakdown["fonts"] += $request["size"];
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
          if ( $content_encoding ) {
            $compressed = "yes";
            $addl = "title=\"Compressed\"";
          } else {
            $compressed = "none";
            $addl = "title=\"Not compressable\"";
          }
        }

        if ( $content_type != "" )
          $haroutput .= " <button $addl class=\"compressed_" . $compressed ."\">". $content_type . "</button>";

        ###############################################################################################################
        # Let's check for questionable practices
        $questionable_practice = array();
        # Let's check for questionable practices
        if ( isset($request['resp_headers']['vary']) ) {
          if ( preg_match("/User-Agent/i", $request['resp_headers']['vary'] ) ) {
            $questionable_practice[] = "User-Agent used in Vary";
          }
          if ( preg_match("/Cookie/i", $request['resp_headers']['vary'] ) ) {
            $questionable_practice[] = "Cookie used in Vary";
          }
        }

        if ( sizeof($questionable_practice) > 0 ) {
          $haroutput .= "<img title=\"" . join(",", $questionable_practice) . "\" width=20 src=\"img/attention.svg\">";

        }

        unset($questionable_practice);

        ###############################################################################################################
        # Identify requests that Set Cookies
        if ( isset($request['resp_headers']['set-cookie']) ) {
            $haroutput .= "<img title=\"Contains Set Cookie\" width=18 src=\"img/cookie.png\">";
        }

        $haroutput .= "</td>";

        ################################################################################################################
        ############################################# Server IP ########################################################
        ################################################################################################################
        # Let's determine the AS number and details
        if ($request['server_ip']) {
          $ip_parts = explode(".", $request['server_ip']);
          array_pop($ip_parts);
          $ip_prefix = join(".", $ip_parts);
          if ( !isset($ip_to_as_cache[$ip_prefix]) ) {
            $ip_details = ip_to_as_info($request['server_ip']);
            $ip_to_as_cache[$ip_prefix] = array( "as_number" => $ip_details["as_number"], "as_name" => $ip_details["as_name"]);
          }
          $frontend_ip_provider = "UNK";
          # Instead of showing text for some of the most common AS let's use an image
          if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS16509" || $ip_to_as_cache[$ip_prefix]["as_number"] == "AS14618" ) {
            $img_or_as_name = '<img src="img/aws.svg" class="vendor_img">';
            $frontend_ip_provider = "AWS";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS15169" ) {
            $img_or_as_name = '<img src="img/google.svg" class="vendor_img">';
            $frontend_ip_provider = "Google";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS20940" || $ip_to_as_cache[$ip_prefix]["as_number"] == "AS16625" ) {
            $img_or_as_name = '<img src="img/akamai.svg" class="vendor_img">';
            $frontend_ip_provider = "Akamai";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS54113" ) {
            $img_or_as_name = '<img src="img/fastly.svg" class="vendor_img">';
            $frontend_ip_provider = "Fastly";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS8068" ) {
            $img_or_as_name = '<img src="img/microsoft.svg" class="vendor_img">';
            $frontend_ip_provider = "Microsoft";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS8075" ) {
            $img_or_as_name = '<img src="img/azure.svg" class="vendor_img">';
            $frontend_ip_provider = "Azure";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS32934" ) {
            $img_or_as_name = '<img src="img/facebook.svg" class="vendor_img">';
            $frontend_ip_provider = "Facebook";
          } else if ( in_array($ip_to_as_cache[$ip_prefix]["as_number"], array("AS13335", "AS209242", "AS139242" ) ) ) {
            $img_or_as_name = '<img src="img/cloudflare.svg" class="vendor_img">';
            if ( in_array($ip_to_as_cache[$ip_prefix]["as_number"], array("AS209242", "AS139242" ) ) )
              $img_or_as_name .= " <b>BYOIP</b>";
            $frontend_ip_provider = "Cloudflare";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS396982" ) {
            $img_or_as_name = '<img src="img/gcp.svg" class="vendor_img">';
            $frontend_ip_provider = "GCP";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS15133" ) {
            $img_or_as_name = '<img src="img/edgecast.svg" class="vendor_img">';
            $frontend_ip_provider = "Edgecast";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS135340" || $ip_to_as_cache[$ip_prefix]["as_number"] == "AS133165" || $ip_to_as_cache[$ip_prefix]["as_number"] == "AS14061" ) {
            $img_or_as_name = '<img src="img/digitalocean.png" class="vendor_img">';
            $frontend_ip_provider = "Digital Ocean";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS20446" ) {
            $img_or_as_name = '<img src="img/stackpath.svg" class="vendor_img" title="StackPath">';
            $frontend_ip_provider = "Stackpath";
          } else if ( $ip_to_as_cache[$ip_prefix]["as_number"] == "AS19551" ) {
            $img_or_as_name = '<img src="img/incapsula.png" class="vendor_img">';
            $frontend_ip_provider = "Incapsula";
          } else {
            $img_or_as_name = $ip_to_as_cache[$ip_prefix]["as_name"];
          }
          $haroutput .= "<td>" . $img_or_as_name . " " . $request['server_ip'] . "</td>";
        } else {
          $haroutput .= "<td>&nbsp;</td>";
        }

        ################################################################################################################
        ############################################# Identify CDN #####################################################
        ################################################################################################################
        $server = "";
        $hit_or_miss = "UNK";
        $hit_or_miss_css = "UNK";

        # Let's try to identify some CDNs. This is Fastly
        if ( isset($request['resp_headers']['server']) && preg_match("/^imgix/i", $request['resp_headers']['server']) ) {
            $server = "ImgIX";
        } else if ( isset($request['resp_headers']['x-served-by']) && preg_match("/^cache-/", $request['resp_headers']['x-served-by']) ) {
            $server = "Fastly " . str_replace("cache-", "", $request['resp_headers']['x-served-by']);
        # Check if Server header provided. It's used by NetDNA and Edgecast
        }  else if ( isset($request['resp_headers']['server']) && preg_match("/^EC[A-Z]/", $request['resp_headers']['server'])  ) {
            $server = trim(preg_replace("/^EC[A-Z]/", "Edgecast", $request['resp_headers']['server']));

        } else if ( isset($request['resp_headers']['server']) && preg_match("/^NetDNA/i", $request['resp_headers']['server']) ) {
            $server = trim($request['resp_headers']['server']);
        # CloudFront
        }
        else if ( isset($request['resp_headers']['via']) && preg_match("/CloudFront/i", $request['resp_headers']['via']) ) {
            $server = "CloudFront";
            if ( isset($request['resp_headers']['x-amz-cf-pop']) ) {
              $server .= " " . $request['resp_headers']['x-amz-cf-pop'];
            }
        # ChinaCache
        } 
        else if ( isset($request['resp_headers']['powered-by-chinacache']) ) {
            $server = "ChinaCache";
        # Incapsula
        }
        else if ( isset($request['resp_headers']['x-instart-request-id']) ) {
            $server = "Instart";
        }
        else if ( isset($request['resp_headers']['quant-server']) ) {
            $server = "QuantCDN";

        }
        else if ( isset($request['resp_headers']['section-io-id']) ) {
            $server = "Section.io";
            if ( isset($request['resp_headers']['section-io-cache'])) {
              $hit_or_miss_css = $request['resp_headers']['section-io-cache'];
              $hit_or_miss = $request['resp_headers']['section-io-cache'];
            }            
        }
        else if ( isset($request['resp_headers']['x-cdn']) and $request['resp_headers']['x-cdn'] == "Incapsula" ) {
            $server = "Incapsula";
        }
        else if ( isset($request['resp_headers']['server']) && preg_match("/^Footprint Distributor/i", $request['resp_headers']['server']) ) {
            $server = "Level3";
        }
        else if ( isset($request['resp_headers']['server']) && preg_match("/^Windows-Azure-Blob/i", $request['resp_headers']['server']) ) {
            $server = "Azure Blob Storage";
        }
        else if ( isset($request['resp_headers']['x-yottaa-optimizations']) or isset($request['resp_headers']['x-yottaa-metrics']) ) {
            $server = "Yottaa";
        }
        # CD Networks
        else if ( isset($request['resp_headers']['x-px']) ) {
            if ( preg_match("/.*\.(.*)\.cdngp.net/i", $request['resp_headers']['x-px'], $out )) {
              $edge_location = " " .$out[1];
            } else {
              $edge_location = "";
            }
            $server = "CDNetworks" . $edge_location;
        }
        # Cloudflare
        else if ( isset($request['resp_headers']['cf-ray']) ) {
            $server = "CF: " . preg_replace('/^(.*)-/', '', $request['resp_headers']['cf-ray']);
            if ( isset($request['resp_headers']['cf-cache-status'])) {
              $hit_or_miss_css = $request['resp_headers']['cf-cache-status'];
              $hit_or_miss = $request['resp_headers']['cf-cache-status'];
            }
        }
        # Highwinds
        else if ( isset($request['resp_headers']['x-hw']) ) {
            $server = substr("Stackpath " . preg_replace("/\d+\.(.*),\d+\.(.*)/", "$1, $2", $request['resp_headers']['x-hw']), 0, 16);
        }
        # Match Akamai headers
        else if ( isset($request['resp_headers']['x-cache']) && preg_match("/(\w+) from.*akamai/i", $request['resp_headers']['x-cache'], $out) ) {
            $server = "Akamai";
            $hit_or_miss = $out[1];
        } else if ( isset($request['resp_headers']['server']) && preg_match("/^Akamai/i", $request['resp_headers']['server']) ) {
            $server = $request['resp_headers']['server'];
        } else if ( isset($request['resp_headers']['server']) && preg_match("/^CDNNet/i", $request['resp_headers']['server']) ) {
            $server = "CDN.Net";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/keycdn/i", $request['resp_headers']['server']) ) {
            $server = "KeyCDN";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/vercel/i", $request['resp_headers']['server']) ) {
            $server = "Vercel";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/netlify/i", $request['resp_headers']['server']) ) {
            $server = "Netlify";
        } else if ( (isset($request['resp_headers']['server']) && preg_match("/envoy/i", $request['resp_headers']['server'])) || isset($request['resp_headers']['x-envoy-upstream-service-time']) ) {
            $server = "Istio Envoy";
        # Not exhaustive way to identify Google
        } else if ( preg_match("/(youtube|gstatic|doubleclick|google).*\.(com|net)\//i", $request["url"]) ) {
            $server = "Google";
        # Not exhaustive way to identify Facebook 
        } else if ( preg_match("/(facebook|fbcdn).*\.(com|net)\//i", $request["url"]) ) {
            $server = "Facebook";
        } else if ( preg_match("/s3.*amazonaws/i", $request["url"]) ) {
            $server = "AWS S3";
        } else if ( isset($request['resp_headers']['set-cookie']) && preg_match("/AWS(A|E)LB/i", $request['resp_headers']['set-cookie'], $out ) ) {
            $server = "AWS " . $out[1] . "LB";
        } else if ( preg_match("/bing\.com\//i", $request["url"]) ) {
            $server = "MS Bing";
        } else if ( preg_match("/(yahoo|ytimg)\.com\//i", $request["url"]) ) {
            $server = "Yahoo";
        } else if ( isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "UploadServer" &&  isset($request['resp_headers']['x-goog-storage-class']) ) {
            $server = "Google Storage";
        } else if ( isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "Azion IMS" ) {
            $server = "AzionCDN";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/^NWS/i" , $request['resp_headers']['server'] ) ) {
            $server = "Tencent";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/leasewebcdn/i" , $request['resp_headers']['server'] ) ) {
            $server = "LeaseWeb CDN";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/bunnycdn/i" , $request['resp_headers']['server'] ) ) {
            $server = "BunnyCDN";
        } else if ( isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "DOSarrest" ) {
            $server = "DOSarrest";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/keycdn/i", $request['resp_headers']['server']) ) {
           if ( isset($request['resp_headers']['x-edge-location']) ) {
              $edge_location = " " .$request['resp_headers']['x-edge-location'];
            } else {
              $edge_location = "";
            }
        } else if ( isset($request['resp_headers']['x-via']) && preg_match("/1\.1 (.*) \(Cdn Cache Server/i", $request['resp_headers']['x-via'], $out ) ) {
            $server = "Quantil " .  $out[1];
        } else if ( isset($request['resp_headers']['via']) && preg_match("/1\.1 (.*)squid/i", $request['resp_headers']['via'], $out ) ) {
            $server = "Squid";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/myracloud/i", $request['resp_headers']['server'] )) {
            $server = "MyraCloud";
        } else if ( isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "EdgePrismSSL" ) {
            if ( isset($request['resp_headers']['x-server-name']) )
              $cache_node = $request['resp_headers']['x-server-name'];
            else
              $cache_node = "";
            $server = "Limelight " . $cache_node;
        } else if ( isset($request['resp_headers']['x-distil-cs'])  ) {
            $server = "Distil";
            $hit_or_miss = $request['resp_headers']['x-distil-cs'];
        } else if ( isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "CDN77-Turbo" ) {
            $edge_location = isset($request['resp_headers']['x-edge-location']) ? " " . htmlentities($request['resp_headers']['x-edge-location']) : "";
            $server = "CDN77" . $edge_location;
        } else if ( isset($request['resp_headers']['x-li-pop']) ) {
            $server = "LinkedIn";
        } else if ( preg_match("/^wss:\/\//", $request["url"]) ) {
            $server = "WebSockets";
        }

        $cms = array();
        ##############################################################################################
        # Figure out if a specific CMS or backend storage is being used
        if ( isset($request['resp_headers']['x-ah-environment']) ) {
            $cms[] = "Acquia";
        } else if ( isset($request['resp_headers']['x-vtex-cache-status']) ) {
            $cms[] = "VTEX";
        } else if ( isset($request['resp_headers']['x-drupal-cache']) ) {
            $cms[] = "Drupal";
        } else if ( isset($request['resp_headers']['x-amz-apigw-id']) ) {
            $cms[] = "AWS API GW";
        } else if ( isset($request['resp_headers']['x-shopid']) || preg_match("/shopify/", $request["url"]) ) {
            $cms[] = "Shopify";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/Contentful/i", $request['resp_headers']['server'] ) ) {
            $cms[] = "Contentful";
        } else if ( isset($request['resp_headers']['x-varnish']) || isset($request['resp_headers']['via']) && preg_match("/varnish/i", $request['resp_headers']['via']) ) {
          if ( !preg_match("/fastly/i", $server) ) 
            $cms[] = "Varnish";
        # Magento version 1
        } else if ( isset($request['resp_headers']['wp-super-cache']) || isset($request['resp_headers']['x-pingback']) || preg_match("/\/wp-content\//i", $request["url"] )
                   || (isset($request['resp_headers']['link']) && preg_match("/wp-json|wp\.me/i", $request['resp_headers']['link']))) {
            $cms[] = "Wordpress";
        } else if ( isset($request['resp_headers']['set-cookie']) && preg_match("/frontend=/i", $request['resp_headers']['set-cookie'] ) ) {
            $cms[] = "Magento1";
        } else if ( isset($request['resp_headers']['server']) && preg_match("/squarespace/i", $request['resp_headers']['server'] ) ) {
            $cms[] = "Squarespace";
        } else if ( preg_match("/\/wcsstore\//i", $request["url"] ) || (isset($request['resp_headers']['server']) && preg_match("/websphere/i", $request['resp_headers']['server'] ) ) ) {
            $cms[] = "WebSphere";
        } else if ( isset($request['resp_headers']['set-cookie']) && preg_match("/Demandware/i", $request['resp_headers']['set-cookie'] ) ) {
            $cms[] = "Demandware";
        # Let's see if the request was in some form or shape backed by S3 ie. it was served by a CDN but storage was actually
        # S3. Append only if server was determined not to be AWS S3 since we don't need double output
        } else if ( ( isset($request['resp_headers']['x-amz-id-2']) || isset($request['resp_headers']['x-amz-request-id']) || (isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "AmazonS3")  ) && $server != "AWS S3" ) {
            $cms[] = "S3";
        } else if ( ( isset($request['resp_headers']['akamai-grn'])  && $frontend_ip_provider != "Akamai" ) ) {
            $cms[] = "AKAM bcknd";
        # Same with Google Cloud Storage (GCS)
        } else if ( isset($request['resp_headers']['server']) && preg_match("/cloudinary/i", $request['resp_headers']['server']) ) {
            $cms[] = "Cloudinary";
        } else if ( isset($request['resp_headers']['x-goog-generation']) && $server != "Google Storage" ) {
            $cms[] = "GCS";
        } else if ( isset($request['resp_headers']['x-ms-blob-type']) ) {
            $cms[] = "Azure BlobStorage";
        } else if ( (isset($request['resp_headers']['server']) && $request['resp_headers']['server'] == "Cowboy") || (isset($request['resp_headers']['via']) && preg_match("/vegur/i", $request['resp_headers']['via']) )) {
            $cms[] = "Heroku";
        # Yottaa may be using other CDNs for their static delivery
        } else if ( isset($request['resp_headers']['x-yottaa-optimizations']) and $server != "Yottaa" ) {
            $cms[] = "Yottaa";
        }

        if ( isset($request['resp_headers']['set-cookie']) && preg_match("/BIGipServer|MRHSession/i", $request['resp_headers']['set-cookie'] ) ) {
            $cms[] = "F5 BIGIP";
        } else if ( isset($request['resp_headers']['set-cookie']) && preg_match("/NSC_Qspe/i", $request['resp_headers']['set-cookie'] ) ) {
            $cms[] = "NetScaler";
        }
        
        # Bot Challenge or WAF
        if ( isset($request['resp_headers']['cf-mitigated'])  ) {
            $cms[] = "Bot Challenge";
        } else if ( preg_match("/_Incapsula_Resource/", $request['url'] )  ) {
            $cms[] = "Bot Challenge";
        } else if (  isset($request['resp_headers']['x-amzn-waf-challenge-id'] ) || isset($request['resp_headers']['x-amzn-waf-action'])  ) {
            $cms[] = "AWS WAF";
        } else if ( preg_match("/sdk\.awswaf\.com/i", $request['url'] ) ) {
          $cms[] = "AWS WAF";
        }

        if ( preg_match("/px\-(cdn|translator|cloud|client)|perimeterx/", $request['url'] ) ) {
            $cms[] = "Human/PX";
        }

        if ( preg_match("/hyva/i", $request['url'] ) ) {
          $cms[] = "Hyva";
        }

        if ( preg_match("/datadome\.co/", $request['url'] ) || ( isset($request['resp_headers']['set-cookie']) && preg_match("/datadome=/i", $request['resp_headers']['set-cookie'] ) ) ) {
            $cms[] = "Datadome";
        }

        if ( isset($request['resp_headers']['set-cookie']) && preg_match("/bm_sv=/i", $request['resp_headers']['set-cookie'] ) ) {
          $cms[] = "Akamai Botman";
      }


        if ( isset($request['resp_headers']['x-yext-site'])  ) {
            $cms[] = "Yext";
        }

        if ( isset($request['resp_headers']['via']) && preg_match("/google$/i", $request['resp_headers']['via']) ) {
            $cms[] = "Google Cloud";
        }

        if ( preg_match("/\/_next\//i", $request["url"] ) ) {
            $cms[] = "Next.js";
        }

        if ( isset($request['resp_headers']['x-dw-request-base-id']) || preg_match("/on\/demandware/i", $request['url']) ) {
            $cms[] = "Salesforce Commerce";
        }

        if ( isset($request['resp_headers']['x-akamai-transformed']) ||
           ( isset($request['resp_headers']['server-timing']) && preg_match("/^ak_/i", $request['resp_headers']['server-timing']) ) ) {
            $cms[] = "Akamai FEO";
        }



        if ( isset($request['resp_headers']['x-cache']) && preg_match("/function|LambdaGeneratedResponse/i", $request['resp_headers']['x-cache'] ) ) {
            $cms[] = "Lambda@Edge";
            $request['resp_headers']['x-cache'] = "Lambda";
        }

        # It's a tracking pixel or it's an ad
        if ( ( isset($request['resp_headers']['content-type']) && preg_match("/image\/gif/i", $request['resp_headers']['content-type']) )
          && intval($request['resp_headers']['content-length']) < 50 )  {
            $cms[] = "Tracking pixel";
        } else if ( preg_match("/googleads|pagead/", $request['url'] ) ) {
            $cms[] = "Ads";
        # Or is it a font
        } else if ( preg_match("/fonts\.googleapis\.com/", $request['url'] ) ) {
            $cms[] = "Fonts";
        } else if ( preg_match("/www\.googletagmanager\.com/", $request['url'] ) ) {
            $cms[] = "Tag Manager";
        } else if ( preg_match("/(gstatic|google)\.com\/recaptcha/", $request['url'] ) ) {
            $cms[] = "Recaptcha";
        }


        if ( count($cms) > 0 ) {
            $server .= " (" . join(",", $cms) . ")";
        }
        unset($cms);

        if ( $server == "" )
            $server = "Unknown";

        # Let's see if we can find any Cache headers and can identify whether request was a HIT or a MISS
        if ( isset($request['resp_headers']['x-cache']) && $hit_or_miss == "UNK" ) {
          # We already figured out for Akamai whether's it's a hit or miss so don't do anything
          if ( $server == "Akamai")
            continue;
          $hit_or_miss = strtoupper(preg_replace("/ from Cloudfront/i", "", $request['resp_headers']['x-cache']));

        }

        if ( $hit_or_miss != "UNK" ) {
          if ( preg_match("/(TCP_HIT|TCP_MEM_HIT|HIT$)/i", $hit_or_miss )) {
              $hit_or_miss_css = "HIT";
          } else {
              $hit_or_miss_css = "MISS";
          }
        }

        $haroutput .= '<td>' . htmlentities($server) . '</td>' .
        '<td class="x-cache-' . $hit_or_miss_css . '">' . htmlentities($hit_or_miss) . '</td>' .
        '<td>' . htmlentities($request["resp_code"]) . '</td>
        <td align="right">' . number_format($request["duration"], 3) . '</td>
        <td align="right"><i>' . number_format($request["size"]) . '</i></td>
        <td class="timeline-data"><span class="bar">' .
        '<span class="fill" style="background: white; width: ' . $white_space .  '%">&nbsp;</span>';

        if ( $dns_time_bar > 0 )
          $haroutput .= '<span title="DNS wait time" class="fill" style="background: #FFCC00; width: ' . $dns_time_bar .  '%">&nbsp;</span>';
        if ( $connect_time_bar > 0 )
          $haroutput .= '<span title="TCP connect time" class="fill" style="background: #FF3366; width: ' . $connect_time_bar .  '%">&nbsp;</span>';
        if ( $ssl_time_bar > 0 )
          $haroutput .= '<span title="TLS establishment time" class="fill" style="background: #663399; width: ' . $ssl_time_bar .  '%">&nbsp;</span>';
        if ( $wait_time_bar > 0 )
          $haroutput .= '<span title="Wait time (TTFB)" class="fill" style="background: #1FE11F; width: ' . $wait_time_bar .  '%">&nbsp;</span>';
        if ( $download_time_bar > 0 )
          $haroutput .= '<span title="Download time" class="fill" style="background: #1977DD; width: ' . $download_time_bar .  '%">&nbsp;</span>';

        $haroutput .= "</span></td></tr>";

    }

    unset($requests);
    unset($har);

    $haroutput .= '</table>
    <script>

    $(function(){
      $(".header_button").button();
    });
    </script>
    ';

    $header = '<button class="header_button" id="show_all_headers_button" onClick="$(\'.http_headers\').toggle(); return false">Show Headers for all requests</button>
    Page download time is <span id="total-time"><em>' . sprintf("%.3f", $total_time) . '</em></span>s. Content breakdown is ';
    foreach ( $content_breakdown as $key => $value ) {
      $header .= strtoupper($key) . "=" . number_format(intval($value/1000)) . "kB ";
    }

    # Add links to click to Websocket anchors
    if ( count($websockets_anchors) > 0 ) {
      $header .= "<em class=\"websockets\">Websockets</em> ";
      foreach ( $websockets_anchors as $key => $value ) {
        $plus1 = $key + 1;
        $header .= " <a href=\"#" . $value . "\">" . $plus1 . "</a>";
      }
    }

    # If we should cache the IP to AS info persist it to disk
    if ( isset($conf["cache_file"]) ) {
      file_put_contents($conf["cache_file"], json_encode($ip_to_as_cache));
    }

    return $header . $haroutput;

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
function get_dns_record($dns_name, $query_type = "A", $include_timing = false, $include_resolver = false) {

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
  
  $response = array();
  
  $response["records"] = $result;

  # Find out what is the IP address of my DNS resolve. We can obtain that by whoami.fastly.net
  if ( $include_resolver ) {
    $resolver_ip_record = dns_get_record("whoami.fastly.net", DNS_A);
    $response["resolver_ip"] = isset($resolver_ip_record[0]['ip']) ? $resolver_ip_record[0]['ip'] : "Unknown";
  }

  if ( $include_timing ) {
    $response["query_time"] = $query_time;
  }

  return $response;

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
            $record_output = format_ip_address($record['ipv6']);
            break;
          case "CNAME":
            $record_output = htmlentities($record['target']);
            break;
          case "TXT":
            $record_output = htmlentities($record['txt']);
            break;
          case "MX":
            $record_output = $record['pri'] . " " . htmlentities($record['target']);
            break;
          case "SOA":
            $record_output = htmlentities($record['mname']) . " <i>" . htmlentities($record['rname'])
              . "</i> Serial: " . htmlentities($record['serial']) . " Rfrsh: " . htmlentities($record['refresh'])
              . " Retry/NegTTL: " . htmlentities($record['retry']) . " Expire: " . htmlentities($record['expire'])
              . " MinTTL: " . htmlentities($record['minimum-ttl']);
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
# IP to AS info
#############################################################################################
function ip_to_as_info($ip) {

  # Reverse the IP address and drop the last octet
  $ip_parts = explode(".", $ip);
  array_pop($ip_parts);
  $reversed_ip = array_reverse($ip_parts);
  $dns_response = get_dns_record(join(".", $reversed_ip) . ".origin.asn.cymru.com", "TXT");
  $response = array();
  if ( preg_match("/^(\d+)/", $dns_response["records"][0]["txt"], $out ) ) {
    $response["as_number"] = "AS" . $out[1];
  } else {
    $response["as_number"] = "ASUNK";
  }
  unset($dns_response);
  if ( !$response["as_number"] != "ASUNK" ) {
    $dns_response = get_dns_record($response["as_number"] . ".asn.cymru.com", "TXT");
    if ( isset($dns_response["records"][0]["txt"]) && preg_match("/^\d+ \| \w+ \| \w+ \| [0-9\-]{10} \| (.*)/", $dns_response["records"][0]["txt"], $out ) ) {
      $response["as_name"] = $out[1];
    } else {
      $response["as_name"] = "ASUNK";
    }
  }

  return($response);
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
	  "headers_string" => htmlentities($header),
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

      if ( preg_match("/.*X-Served-By: (.*)\n/i", $record['headers_string'], $out) ) {
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

    $gzip = preg_match("/Content-Encoding: (gzip|br)/i", $record['headers_string']) ? "Yes" : "No";

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
