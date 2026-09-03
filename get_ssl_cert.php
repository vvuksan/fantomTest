<?php

header("cache-control: private, s-maxage=2");

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

if ( isset($conf['cors_headers_acao']) ) {
  header($conf['cors_headers_acao']);
}

$host_name = isset($_REQUEST['hostname']) ? trim($_REQUEST['hostname']) : "";
if ( $host_name == "" ) {
  die("No host name supplied");
}

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

$site_id = isset($_REQUEST['site_id']) && is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

# Need name of this script so we can execute the same on remote nodes
$conf['remote_exe'] = basename ( __FILE__ );

///////////////////////////////////////////////////////////////////////////////
// site_id == -1 means run only on this node. This is the only time
// we don't run stuff elsewhere
///////////////////////////////////////////////////////////////////////////////
if ( $site_id == -1 ) {

  require_once("./tools_ssl.php");

  $results = check_certificate_chain( $user['ip'], $port, $sni_name );

  if ( !$results["connected"] || count($results["certs"]) == 0 ) {
    print '<div class="tls-report"><div class="tls-verdict bad"><span class="tls-verdict-icon">&#10005;</span><div><b>Could not retrieve a certificate from ' .
      htmlentities($host_name) . ':' . intval($port) . '</b><span>' . htmlentities($results["message"]) . '</span></div></div></div>';
  } else {
    # The name the certificate is expected to cover: the SNI name if given, otherwise the host name (unless it is an IP)
    $expected_name = $sni_name != "" ? $sni_name : $host_name;
    $checks = analyze_certificate($results, $expected_name, $it_s_ip && $sni_name == "");
    print render_certificate_report($results, $checks, $host_name, $port, $sni_name);
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
        $.get("' . $conf['remote_exe'] . '", "site_id=' . $index . '&hostname=' . urlencode($host_name) . '&port=' . intval($port) . '&sni_name=' . urlencode($sni_name) . '", function(data) {
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
        "&hostname=" . urlencode($host_name) . "&port=" . intval($port) . "&sni_name=" . urlencode($sni_name), FALSE, stream_context_create($sslOptions) ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
