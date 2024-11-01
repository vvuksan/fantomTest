<?php

header("Cache-Control: private, s-maxage=2");

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

if ( ! $conf['pingmtr_enabled'] ) {
    die("Can't run PING/MTR as it has been disabled. Set pingmtr_enabled to true in conf.php");
}

$user['hostname'] = trim($_REQUEST['hostname']);

##################################################################
# Since we are shelling out we need to make sure what we
# are being supplied is an IP or a hostname that actually resolves
# or it's an IP
##################################################################
if(filter_var($user['hostname'], FILTER_VALIDATE_IP)) {
    $user['ip'] = $user['hostname'];
} else {
    $user['ip'] = gethostbyname(trim($user['hostname']));
    # If resolution fails it just returns hostname back
    if ( $user['ip'] == $user['hostname'] )
        die("Address is not an IP and I can't resolve it. Doing nothing");
}

# Cap ping count to 20
if ( isset($_REQUEST['ping_count']) and is_numeric($_REQUEST['ping_count']) and $_REQUEST['ping_count'] > 0 and $_REQUEST['ping_count'] <= 20 ) {
    $ping_count = intval($_REQUEST['ping_count']);
} else {
    $ping_count = 10;
}

$site_id = is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

$conf['remote_exe'] = "get_mtr.php";

///////////////////////////////////////////////////////////////////////////////
// site_id == -1 means run only on this node. This is the only time
// we don't run stuff elsewhere
///////////////////////////////////////////////////////////////////////////////
if ( $_REQUEST['site_id'] == -1 ) {

?>

    <h2>Ping</h2> 
    <div style="background-color: #DCDCDC">
    <pre>
    <?php
    if ( filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
      passthru($conf['ping6_bin'] . " -i 0.2 -c " . $ping_count . " " . $user['ip']);
    } else {
      passthru($conf['ping_bin'] . " -i 0.2 -c " . $ping_count . " " . $user['ip']);
    }
    ?>
    </pre>
    </div>
    
    <h2>MTR</h2>
    <div style="background-color: #FAFAD2">
    <pre>
    <?php
    passthru($conf['mtr_bin'] . " --report-wide -z --report-cycles=1 --report " . $user['hostname']);
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
        <button onClick='$(\"#mtrping_results_" . $index . "\").toggle();'>" .$conf['remotes'][$index]['name']. "</button></div>";

        print "<div id='mtrping_results_" . $index ."'>";

        #print (file_get_contents($conf['remotes'][$index]['base_url'] . "get_mtr.php?site_id=-1" .
        #"&hostname=" . $user['hostname'] ));
        print "<img src=\"img/spinner.gif\"></div>";

        $args[] = 'hostname=' . htmlentities($user['hostname']);
        $args[] = 'ping_count=' . $ping_count;

        print '
        <script>
        $.get("' . $conf['remote_exe'] . '", "site_id=' . $index . '&' . join("&", $args) . '", function(data) {
            $("#mtrping_results_' . $index .'").html(data);
         });
        </script>
        <p></p>';
        
    }

///////////////////////////////////////////////////////////////////////////////
// Otherwise if it's not a local node or all nodes it's a specific node
///////////////////////////////////////////////////////////////////////////////
    
} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    
    print "<div><h3>" .$conf['remotes'][$site_id]['name']. "</h3></div>";
    print "<div class=dns_results>";
    $args[] = 'hostname=' . htmlentities($user['hostname']);
    $args[] = 'ping_count=' . $ping_count;
    $url = $conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?site_id=-1&" . join("&", $args);
    $sslOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false));
    print (file_get_contents($url, FALSE, stream_context_create($sslOptions)));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
