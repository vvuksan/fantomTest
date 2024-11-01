<?php

header("Cache-Control: private, s-maxage=2");

#############################################################################
# Use NMAP to discover what TLS ciphers remote server supports
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
if(filter_var($host_name, FILTER_VALIDATE_IP) or filter_var($host_name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
    $user['ip'] = $host_name;
} else {
    $user['ip'] = gethostbyname($_REQUEST['hostname']);
    if ( $user['ip'] == $_REQUEST['hostname'] )
        die("Address is not an IP and I can't resolve it. Doing nothing");
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

  # First make sure nmap is available
  if ( !is_executable($conf['nmap_bin']) ) {
    die("NMAP is not executable. Current path is to to " . $conf['nmap_bin'] . " please set \$conf['nmap_bin'] in conf.php to proper path");
  }

?>
    <h2>Ciphers</h2>

    Running <p> <div style="background-color: #EEEEEE">
    <?php

    if ( filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
      $is_ipv6 = "-6";
    } else {
      $is_ipv6 = "";
    }
    $cmd = $conf['nmap_bin'] . " --script=ssl-enum-ciphers " . $is_ipv6 . " -p " . $port . " " . $user['ip'];
    print " " . $cmd;
    ?>
    </div>
    <div style="background-color: #DCDCDC">
    <pre>
    <?php
    passthru(escapeshellcmd($cmd));
    ?>
    </pre>
    </div>

<?php


///////////////////////////////////////////////////////////////////////////////
// site_id == -100 means run on all remotes. So loop through individual 
// remotes and make AJAX calls
///////////////////////////////////////////////////////////////////////////////
} else if ( $site_id == -100 ) {

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $index => $remote ) {
        
        print "<div id='remote_" . $index . "'>
        <button onClick='$(\"#ciphers_results_" . $index . "\").toggle();'>" .$conf['remotes'][$index]['name']. "</button></div>";
        
        print "<div id='ciphers_results_" . $index ."'>";
        
        print "<img src=\"img/spinner.gif\"></div>";
        
        print '
        <script>
        $.get("' . $conf['remote_exe'] . '", "site_id=' . $index . '&hostname=' . htmlentities($_REQUEST['hostname']) . '", function(data) {
            $("#ciphers_results_' . $index .'").html(data);
         });
        </script>
        <p></p>';
        
    }

} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    $sslOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false));
    print "<div><h3>" .$conf['remotes'][$site_id]['name']. "</h3></div>";
    print "<div class=dns_results>";
    print (file_get_contents($conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?site_id=-1" .
        "&hostname=" . $_REQUEST['hostname'] . "&port=" . $port, FALSE, stream_context_create($sslOptions) ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
