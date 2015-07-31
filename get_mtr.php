<?php

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

##################################################################
# Since we are shelling out we need to make sure what we
# are being supplied is an IP or a hostname that actually resolves
# or it's an IP
##################################################################
if(filter_var($_REQUEST['hostname'], FILTER_VALIDATE_IP)) {
    $user['ip'] = $_REQUEST['hostname'];
} else {
    $user['ip'] = gethostbyname($_REQUEST['hostname']);
    # If resolution fails it just returns hostname back
    if ( $user['ip'] == $_REQUEST['hostname'] )
        die("Address is not an IP and I can't resolve it. Doing nothing");
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
    if ( filter_var($_REQUEST['hostname'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
      passthru($conf['ping6_bin'] . " -c 4 " . $user['ip']);
    } else {
      passthru($conf['ping_bin'] . " -c 4 " . $user['ip']);
    }
    ?>
    </pre>
    </div>
    
    <h2>MTR</h2>
    <div style="background-color: #FAFAD2">
    <pre>
    <?php
    passthru($conf['mtr_bin'] . " --report-wide -z --report-cycles=1 --report " . $_REQUEST['hostname']);
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
        
        print "<div id='remote_" . ${index} . "'>
        <button onClick='$(\"#mtrping_results_" . ${index} . "\").toggle();'>" .$conf['remotes'][$index]['name']. "</button></div>";
        
        print "<div id='mtrping_results_" . ${index} ."'>";
        
        #print (file_get_contents($conf['remotes'][$index]['base_url'] . "get_mtr.php?site_id=-1" .
        #"&hostname=" . $_REQUEST['hostname'] ));
        print "<img src=\"img/spinner.gif\"></div>";
        
        print '
        <script>
        $.get("' . $conf['remote_exe'] . '", "site_id=' . $index . '&hostname=' . htmlentities($_REQUEST['hostname']) . '", function(data) {
            $("#mtrping_results_' . ${index} .'").html(data);
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
    print (file_get_contents($conf['remotes'][$site_id]['base_url'] . $conf['remote_exe'] . "?site_id=-1" .
    "&hostname=" . $_REQUEST['hostname'] ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}

?>
