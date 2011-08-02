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
    
    $results = get_har($url);
    if ( $results[0] == "Error" ) {
        ?>
        <div class="ui-widget">
        	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	    	<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	        <strong>Alert:</strong> PhantomJS exited abnormally. Perhaps Xvfb is not running. Please check your webserver error log</p>
	    </div>
        </div>        
         <?php
        exit(1);       
    }

    $har_array = json_decode($results[0], true);
    
    // If har_array is null JSON could not be parsed
    if ( $har_array === NULL ) {
        ?>
        <div class="ui-widget">
        	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	    	<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	        <strong>Alert:</strong> Output received from PhantomJS can't be parsed. Not sure why yet.</p>
	    </div>
        </div>
        <?php
        exit(1);
    }
    
    if ( $results[1] ) {
        print "<script>
        var largeimage='" . $results[1] . "';
        </script>";
        echo '<center><a href="#" onClick="showLargeImage(); return false;">
        <img width=150px src="data:image/png;base64,' . $results[1] . '" />
        </a></center>';
    }

    print generate_waterfall($har_array);

} else {
?>
  No URL supplied
<?php
}
?>
