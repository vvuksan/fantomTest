<?php

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

$site_id = is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

$conf['remote_exe'] = "get_ssl.php";

///////////////////////////////////////////////////////////////////////////////
// site_id == -1 means run only on this node. This is the only time
// we don't run stuff elsewhere
///////////////////////////////////////////////////////////////////////////////
if ( $_REQUEST['site_id'] == -1 ) {

?>

    <h2>Ciphers</h2> 
    <div style="background-color: #DCDCDC">
    <pre>
    <?php
    passthru("cd " . __DIR__ . "/ssl; " . $conf['nmap_bin'] . " --script ssl-enum-ciphers.nse -p 443 " . $_REQUEST['hostname']); 
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
        
        #print (file_get_contents($conf['remotes'][$index]['base_url'] . "get_ssl.php?site_id=-1" .
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
