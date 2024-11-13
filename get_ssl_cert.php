<?php

header("Cache-Control: private, s-maxage=2");

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

$host_name = trim($_REQUEST['hostname']);

# Is it an IP 
if ( filter_var($host_name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
  $user['ip'] = "[" . $host_name . "]";
  $it_s_ip = true;
} else if(filter_var($host_name, FILTER_VALIDATE_IP)) {
  $user['ip'] = $host_name;
  $it_s_ip = true;
} else {
  $user['ip'] = gethostbyname($host_name);
  # If we get the same thing that we started with name is not resolvable
  if ( $user['ip'] == $host_name ) {
    die("Address is not an IP and I can't resolve it. Doing nothing");
  } else {
    $it_s_ip = false;
    $user['ip'] = trim($_REQUEST['hostname']);
  }
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
    <pre>echo "HEAD / HTTP/1.1" |  openssl s_client -showcerts <?php if ( $sni_name != "" ) print "-servername " . htmlentities($sni_name); ?> -connect <?php print htmlentities($_REQUEST['hostname']) . ":" . $port; ?> | openssl x509  -noout  -text</pre>
</div>
     or gnutls-cli command:
<div style="background-color: #DCDCDC">
    <pre>echo -n | gnutls-cli --print-cert -p <?php print $port; ?> <?php print htmlentities($_REQUEST['hostname']); ?> | openssl x509  -noout  -text</pre>
</div>

<?php

  require_once("./tools_ssl.php");

  if ( $_REQUEST['sni_name'] ) {
   $ssloptions["SNI_enabled"] = true;
   # Need to figure out why this doesn't work
   $ssloptions["verify_peer"] = false;
   $ssloptions["SNI_server_name"] = $_REQUEST['sni_name'];
  } else {
   $ssloptions["SNI_enabled"] = false;
  }

  if ( $_REQUEST['sni_name'] ) {

    $results = check_certificate_chain( $user['ip'], $port, $_REQUEST['sni_name']);

  } else {

    $results = check_certificate_chain( $user['ip'], $port, "");

  }

  if ( $results["success"] ) {
    print "<div id=\"ssl_cert_results\">";
  } else {
    print "<div id=\"ssl_cert_results\" class=\"ssl-cert-invalid\">";
    print "<h2><font color=red>This certificate is invalid</font></h2>";
    print "Possible reasons (not exhaustive):<br><div id=\"ssl-cert-invalid\">";
    print htmlentities($results["message"]) . "</div>";

  }

  foreach($results['certs'] as $cert) {
      print "<table border=1 class=\"tablesorter\">
        <thead><th>Key</th><th>Details</th></thead><tbody>";

      foreach ( $cert as $key => $parts ) {
        # Gonna skip purposes for now
        if ( $key == "purposes" )
          continue;

        # Convert UNIX dates into human readable
        if ( preg_match("/^VALID(.*)_T$/i", $key) )
          $parts = date('r', $parts);
        print "<tr><th>" . htmlentities(strtoupper($key)) . "</th>";
        if ( is_array($parts) ) {
          print "<td><table border=1 class=\"tablesorter\">";
          foreach ( $parts as $subkey => $value ) {
            print "<tr><th>" . htmlentities(strtoupper($subkey)) . "</th><td>" . htmlentities($value) ."</td></tr>";
          }
          print "</table></td></tr>";
        } else {
          print "<td colspan=2>" . htmlentities($parts) . "</td></tr>";
        }
      }
      
      print "</table>";
      # Only care about our SSL certs that have SAN entries in them
  }

  print "</div>";


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
    $sslOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false));
    
    print "<div><h3>" .$conf['remotes'][$site_id]['name']. "</h3></div>";
    print "<div class=dns_results>";
    print (file_get_contents($conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?site_id=-1" .
        "&hostname=" . $_REQUEST['hostname'] . "&port=" . $port . "&sni_name=" . $sni_name, FALSE, stream_context_create($sslOptions) ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
