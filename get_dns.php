<?php

/* Request "ANY" record for php.net,
   and create $authns and $addtl arrays
   containing list of name servers and
   any additional records which go with
   them */
$result = dns_get_record($_REQUEST['hostname'], DNS_A);
#, DNS_ALL, $authns, $addtl);
#print "<PRE>";
#print_r($result);

if ( count($result) > 0 ) {
    print "<table border=1><tr><th>Hostname</th><th>TTL</th><th>Type</th><th>IP</th></tr>";
    foreach( $result as $index => $record ) {
        print "<tr><td>" . $record['host'] . "</td><td>" .
            $record['ttl'] . "</td><td>" .
            $record['type'] . "</td><td>" .
            $record['ip'] . "</td></tr>";
    }
    print "</table>";
} else {
    
}

?>
