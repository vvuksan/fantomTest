<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
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
        $resp_size = floatval($request['response']['bodySize']);
        
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
            <th>Duration</th>
            <th>Size (bytes)</th>
            <th></th>
        </tr>'
    ;
    
    foreach ( $requests as $key => $request ) {
    
        $time_offset = $request["start_time"] - $min_start_time;
        
        $white_space = ($time_offset / $total_time) * 100;
        $progress_bar = ($request["duration"] / $total_time) * 100;
        
        $haroutput .= "\n<tr class='response_" . $request["resp_code"] . "'>
        <td>" . $key
        . "</td><td><a href='" . $request["url"] . "'>" . substr($request["url"],0,50) . '</a>
        <button class="header_button" onClick="$(\'#item_' . $key . '\').toggle(); return false">hdrs</button>
        <div class="http_headers" style="display: none;" id="item_' . $key .'">';
        foreach ( $request['resp_headers'] as $key => $value ) {
            $haroutput .= "<b>" . $key . "</b>: " . $value . "<br />";
        }

        if ( isset($request['resp_headers']['X-Cache']) ) {
	   $hit_or_miss = $request['resp_headers']['X-Cache'];
	   if ( preg_match("/Hit|HIT$/", $request['resp_headers']['X-Cache'] )) {
		$hit_or_miss_css = "HIT";
	   } else {
		$hit_or_miss_css = "MISS";
	   }
 	} else {
 		$hit_or_miss_css = "UNK";
 		$hit_or_miss = "UNK";
 	}
        isset($request['resp_headers']['X-Served-By']) ? $server = str_replace("cache-", "", $request['resp_headers']['X-Served-By']) : $server = "UNK";

        # Check if EdgeCast
        if ( preg_match("/^ECS/", $request['resp_headers']['Server']) ) {
            $server = trim($request['resp_headers']['Server']);
        }
        
        # CloudFront
        if ( preg_match("/CloudFront/", $request['resp_headers']['Via']) ) {
            $server = "CloudFront";
        }

        # NetDNA
        if ( preg_match("/^NetDNA/", $request['resp_headers']['Server']) ) {
            $server = trim($request['resp_headers']['Server']);
        }
        
        # Cloudflare
        if ( isset($request['resp_headers']['CF-RAY']) ) {
            $server = "CF: " . preg_replace('/^(.*)-/', '', $request['resp_headers']['CF-RAY']);
	    $hit_or_miss_css = $request['resp_headers']['CF-Cache-Status'];
	    $hit_or_miss = $request['resp_headers']['CF-Cache-Status'];
        }
        
	# Highwinds
        if ( isset($request['resp_headers']['X-HW']) ) {
            $server = "HW " . preg_replace("/\d+\.(.*),\d+\.(.*)/", "$1, $2", $request['resp_headers']['X-HW']);
	    $hit_or_miss_css = "HIT";
	    $hit_or_miss = "HIT";
        }
        
        $haroutput .= '<td>' . $server . '</td>' .
        '<td class="x-cache-' . $hit_or_miss_css . '">' . $hit_or_miss . '</td>' .
        '<td>' . $request["resp_code"] . '</td>
        <td>' . $request["duration"] . '</td>
        <td>' . $request["size"] . '</td>
        <td class="timeline-data"><span class="bar">' .
        '<span class="fill" style="background: white; width: ' . $white_space .  '%">&nbsp;</span>'.
        '<span class="fill" style="background: #AAB2FF; width: ' . $progress_bar .  '%">&nbsp;</span>'.
        "</span></td></tr>";
    
    }
    
    unset($requests);
    unset($har);
    
    $haroutput .= "</table>";

    return $haroutput;

} // end of function generate_waterfall()

//////////////////////////////////////////////////////////////////////////////
// Use Phantom JS to produce a JSON containing the HTTP archive and the
// Image
//////////////////////////////////////////////////////////////////////////////
function get_har_using_phantomjs($url, $include_image = true) {

    global $conf;
    
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
        
        return array( "success" => 0, "error_message" => "PhantomJS exited abnormally. Perhaps Xvfb is not running. Please check your webserver error log" );
    }

}

?>
