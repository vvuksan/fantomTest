<?php

require_once("./tools.php");

if ( isset($_GET['url'])) {

    $url = validate_url($_GET['url']);
    
    $results = get_har($url);

    $har_array = json_decode($results[0], true);
    
    if ( $results[1] )
        echo '<center><img width=150px src="data:image/png;base64,'.$results[1].'" /></center>';

    print generate_waterfall($har_array);
    

} else {
?>
  No URL supplied
<?php
}
?>
