<?php

#############################################################################
# Use NMAP to discover what SSL ciphers remote server supports
#############################################################################
$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

# Is it an IP 
if(filter_var($_REQUEST['hostname'], FILTER_VALIDATE_IP)) {
    $user['ip'] = $_REQUEST['hostname'];
} else {
    $user['ip'] = gethostbyname($_REQUEST['hostname']);
    if ( $user['ip'] == $_REQUEST['hostname'] )
        die("Address is not an IP and I can't resolve it. Doing nothing");
}

if( isset($_REQUEST['sni_name']) && preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $_REQUEST['sni_name']) ) {
  $sni_name = $_REQUEST['sni_name'];
} else {
  $sni_name = ""; 
}

if ( !isset($_REQUEST['port']) ) {
  $port = 443;
} else {
  $port = is_numeric($_REQUEST['port']) && $_REQUEST['port'] > 1 && $_REQUEST['port'] < 65536 ? $_REQUEST['port'] : 443;
}

$site_id = is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

# Need name of this script so we can execute the same on remote nodes
$conf['remote_exe'] = basename ( __FILE__ );

///////////////////////////////////////////////////////////////////////////////
// site_id == -1 means run only on this node. This is the only time
// we don't run stuff elsewhere
///////////////////////////////////////////////////////////////////////////////
if ( $_REQUEST['site_id'] == -1 ) {

?>
     Verify yourself with OpenSSL command:
<div style="background-color: #DCDCDC">
    <pre>echo "HEAD / HTTP/1.1" |  openssl s_client -showcerts <?php if ( $sni_name != "" ) print "-servername " . $sni_name; ?> -connect <?php print $user['ip'] . ":" . $port; ?> | openssl x509  -noout  -text</pre>    
</div>    
<?php

  $ssloptions = array(
    "capture_peer_cert_chain" => true,
    "allow_self_signed"=>false,
    "verify_peer"=>true,
    "cafile" => "/etc/ssl/certs/ca-certificates.crt"
    );

  if ( $_REQUEST['sni_name'] ) {
   $ssloptions["SNI_enabled"] = true;
   # Need to figure out why this doesn't work
   $ssloptions["verify_peer"] = false;
   $ssloptions["SNI_server_name"] = $_REQUEST['sni_name'];
  } else {
   $ssloptions["SNI_enabled"] = false;
  }
  
  # Set SSL stream context
  $ctx = stream_context_create( array("ssl" => $ssloptions) );
  
  $fp = stream_socket_client("ssl://" . $user['ip'] . ":" . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
  # Grab the
  if ( $fp ) {
    $cont = stream_context_get_params($fp);
  } else {
    print "There was an error " . $errstr;
  }
  
  fclose($fp);
  
  foreach($cont["options"]["ssl"]["peer_certificate_chain"] as $cert) {
      $parsed_cert = openssl_x509_parse($cert);
      print "<table border=1 class=\"tablesorter\">
        <thead><th>Key</th><th>Details</th></thead><tbody>";
      
      foreach ( $parsed_cert as $key => $parts ) {
        # Gonna skip purposes for now
        if ( $key == "purposes" )
          continue;
        
        # Convert UNIX dates into human readable
        if ( preg_match("/^VALID(.*)_T$/i", $key) )
          $parts = date('r', $parts);
        print "<tr><td>" . strtoupper($key) . "</td>";
        if ( is_array($parts) ) {
          print "<td><table border=1 class=\"tablesorter\">";
          foreach ( $parts as $subkey => $value ) {
            print "<tr><td>" . strtoupper($subkey) . "</td><td>" . $value ."</td></tr>";
          }
          print "</table></td></tr>";
        } else {
          print "<td colspan=2>" . $parts . "</td></tr>";
        }
      }
      
      print "</table>";
      # Only care about our SSL certs that have SAN entries in them
      unset($parsed_cert);
  }


///////////////////////////////////////////////////////////////////////////////
// site_id == -100 means run on all remotes. So loop through individual 
// remotes and make AJAX calls
///////////////////////////////////////////////////////////////////////////////
} else if ( $site_id == -100 ) {

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $index => $remote ) {

        print "<div id='remote_" . ${index} . "'>
        <button onClick='$(\"#cert_results_" . ${index} . "\").toggle();'>" .$conf['remotes'][$index]['name']. "</button></div>";

        print "<div id='cert_results_" . ${index} ."'>";
        print "<img src=\"img/spinner.gif\"></div>";

        print '
        <script>
        $.get("' . $conf['remote_exe'] . '", "site_id=' . $index . '&hostname=' . htmlentities($_REQUEST['hostname']) . '", function(data) {
            $("#cert_results_' . ${index} .'").html(data);
         });
        </script>
        <p></p>';

    }

} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    
    print "<div><h3>" .$conf['remotes'][$site_id]['name']. "</h3></div>";
    print "<div class=dns_results>";
    print (file_get_contents($conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?site_id=-1" .
        "&hostname=" . $_REQUEST['hostname'] . "&port=" . $port . "&sni_name=" . $sni_name ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
