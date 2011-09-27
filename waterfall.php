<script>
function showLargeImage() {
  $("#large_screenshot").dialog( "open" );
  $("#large_screenshot").html('<img src="data:image/png;base64,' + largeimage + '" />');
}
</script>
<?php

require_once("./tools.php");

if ( isset($_GET['url'])) {

    $url = validate_url($_GET['url']);
    
    if ( $url === FALSE ) {
        ?>
        <div class="ui-widget">
        	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	    	<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	        <strong>Alert:</strong> URL is invalid. Please check for any invalid characters, spaces, etc.</p>
	    </div>
        </div>        
         <?php
        exit(1);
    }
    
    // Check for site ID where to execute actual query. If Site ID is -1 it means local
    if ( isset($_GET['site_id']) &&  $_GET['site_id'] != -1 ) {
        $site_id = $_GET['site_id'];
        # Make sure Remote URL doesn't have any trailing slashes
        $base_url = rtrim($conf['remotes'][$site_id]['base_url'], '/');
        $json = file_get_contents($base_url . "/get_har.php?url=" . $url);
        $results = json_decode($json, TRUE);
    } else {
        $results = get_har_using_phantomjs($url);        
    }
    
    // Check whether phantomjs succeeded
    if ( isset( $results['success']) and $results['success'] == 0 ) {
        ?>
        <div class="ui-widget">
        	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	    	<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	        <strong>Alert:</strong> <?php print $results['error_message']; ?></p>
	    </div>
        </div>        
         <?php
        exit(1);       
    }

    // Include a screenshot if it exists
    if ( isset( $results['screenshot']) ) {
        print "<script>
        var largeimage='" . $results['screenshot'] . "';
        </script>";
        echo '<center><a href="#" onClick="showLargeImage(); return false;">
        <img width=150px src="data:image/png;base64,' . $results['screenshot'] . '" />
        </a></center>';
    }

    print generate_waterfall($results['har']);

} else {
?>
  No URL supplied
<?php
}
?>
