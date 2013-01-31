<?php

require_once("./conf.php");

if ( ! $conf['pingmtr_enabled'] ) {
    die("Can't run PING/MTR as it has been disabled. Set pingmtr_enabled to true in conf.php");
}

# Is it an IP 
if(filter_var($_REQUEST['hostname'], FILTER_VALIDATE_IP)) {
    $user['ip'] = $_REQUEST['hostname'];
} else {
    $user['ip'] = gethostbyname($_REQUEST['hostname']);
    if ( $user['ip'] == $_REQUEST['hostname'] )
        die("Address is not an IP and I can't resolve it. Doing nothing");
}

?>

Ping 
<div style="background-color: #DCDCDC">
<pre>
<?php
passthru($conf['ping_bin'] . " -c 4 " . $_REQUEST['hostname']); 
?>
</pre>
</div>

<h2>MTR</h2>
<div style="background-color: #FAFAD2">
<pre>
<?php
passthru($conf['mtr_bin'] . " --report-wide --report-cycles=1 --report " . $_REQUEST['hostname']);
?>
</pre>
</div>