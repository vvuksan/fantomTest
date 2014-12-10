<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

$site_id = is_numeric($_REQUEST['site_id']) ? $_REQUEST['site_id'] : -1;

if ( !isset($_REQUEST['hostname'])) {
    die("Need to supply hostname");
}

if ( $_REQUEST['site_id'] == -1 ) {

    $start_time = microtime(TRUE);
    $result = dns_get_record($_REQUEST['hostname'], DNS_A);
    $query_time_in_ms = round((microtime(TRUE) - $start_time) * 1000);
    
    $resolver_ip_record = dns_get_record("whoami.akamai.net", DNS_A);
    $resolver_ip = isset($resolver_ip_record[0]['ip']) ? $resolver_ip_record[0]['ip'] : "Unknown";
    
    if ( preg_match("/^74.125/", $resolver_ip ) ) {
      $dns_provider = " - Google DNS";
    } else {
      $dns_provider = "";
    }
    
    if ( count($result) > 0 ) {
        print "<table border=1 class=tablesorter>
	  <thead><tr><th>Hostname</th><th>Resolver IP</th><th>Query Time (ms)</th><th>TTL</th><th>Type</th><th>IP</th></tr></thead><tbody>";
        foreach( $result as $index => $record ) {
            print "<tr><td>" . $record['host'] . "</td>
            <td>" . $resolver_ip . " " . $dns_provider .  "</td>
            <td>" . $query_time_in_ms . "</td>            
            <td>" . $record['ttl'] . "</td>
            <td>" . $record['type'] . "</td>
            <td>" . $record['ip'] . "</td></tr>";
        }
        print "</tbody></table>";
    } else {
        
    }

} else if ( $site_id == -100 ) {

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $index => $remote ) {

        print "<div id='remote_" . ${index} . "'>
        <button onClick='$(\"#dns_results_" . ${index} . "\").toggle();'>" .$conf['remotes'][$index]['name']. "</button></div>";
        
        print "<div id='dns_results_" . ${index} ."'>";
        
        #print (file_get_contents($conf['remotes'][$index]['base_url'] . "get_mtr.php?site_id=-1" .
        #"&hostname=" . $_REQUEST['hostname'] ));
        print "<img src=\"img/spinner.gif\"></div>";
        
        print '
        <script>
        $.get("get_dns.php", "site_id=' . $index . '&hostname=' . htmlentities($_REQUEST['hostname']) . '", function(data) {
            $("#dns_results_' . ${index} .'").html(data);
         });
        </script>
        <p></p>';
        print "</div>";
    }

} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    
    print "<div><h3>" .$conf['remotes'][$site_id]['name']. "</h3></div>";
    print "<div class=dns_results>";
    print (file_get_contents($conf['remotes'][$site_id]['base_url'] . "get_dns.php?site_id=-1" .
    "&hostname=" . $_REQUEST['hostname'] ));
    print "</div>";
    
    
} else {
    die("No valid site_id supplied");
}


?>
