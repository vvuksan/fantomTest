<?php

function get_ca_certs($ca_certs_file = "/etc/ssl/certs/ca-certificates.crt") {

  $ca_certs = array();

  # Get individual certs 
  $certs = explode("-----BEGIN CERTIFICATE-----", file_get_contents($ca_certs_file));

  foreach ( $certs as $cert ) {

    $parsed_cert = (openssl_x509_parse("-----BEGIN CERTIFICATE-----\n" . $cert));
    if ( isset($parsed_cert["subject"]["CN"]) ) {
        $cn_value = $parsed_cert["subject"]["CN"];
    } else {
        $cn_value = "unknown subject cn";
    }
    if ( isset($parsed_cert["issuer"]["CN"]) ) {
        $issuer_value = $parsed_cert["issuer"]["CN"];
    } else {
        $issuer_value = "unknown issuer";
    }
    
    if ( $cn_value == $issuer_value ) { 
        $ca_certs[$cn_value] = array ( 
            "ca_cert" => 1 
        );
    }
  }
  
  return $ca_certs;
  
}

#################################################################################################
#
#################################################################################################
function check_certificate_chain($hostname, $port, $sni_hostname, $debug = 0) {

  // Turn off all error reporting
  error_reporting(0);

  # Get list of all certificates on the local machine
  $ca_certs = get_ca_certs();
  
  # First we are gonna test whether certificate is good.
  $ssloptions = array(
      "capture_peer_cert_chain" => false, 
      "allow_self_signed" => false,
      "verify_peer_name" => true,
      "verify_peer" => true,
      );
  
  $ctx = stream_context_create( array("ssl" => $ssloptions) );

  # Let's establish a SSL connection
  $fp = stream_socket_client("ssl://$hostname:$port", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) {
    $success = 0;
  } else {
    fclose($fp);
    $success = 1;

  }

  $ssloptions = array(
      "capture_peer_cert_chain" => true, 
      "allow_self_signed"=> true,
      "verify_peer_name" => false,
      "verify_peer"=> false
      );
  
  # Are we doing SNI requests
  if ( $sni_hostname != "" ) {
   $ssloptions["SNI_enabled"] = true;
   # Need to figure out why this doesn't work
   $ssloptions["SNI_server_name"] = $_REQUEST['sni_name'];
  } else {
   $ssloptions["SNI_enabled"] = false;
  }
  
  # Set SSL stream context
  $ctx = stream_context_create( array("ssl" => $ssloptions) );

  # Let's establish a SSL connection
  $fp = stream_socket_client("ssl://{$hostname}:{$port}", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $ctx);
  # Grab the context parameters like certificate chain etc.

  $captured_certs = array();
  
  $cont = stream_context_get_params($fp);
  if (!$fp) {
    echo "$errstr ($errno)<br />\n";
  } else {

    # Let's go through captured certificates
    foreach($cont["options"]["ssl"]["peer_certificate_chain"] as $cert) {
      $parsed_cert = openssl_x509_parse($cert);
      $host_cert = isset($parsed_cert["extensions"]["basicConstraints"]) && $parsed_cert["extensions"]["basicConstraints"] == "CA:FALSE" ? 1 : 0;
      if ( $host_cert ) {
        $issuer_cn = $parsed_cert["issuer"]["CN"];
      }

      # Let's derive full ISSUER name
      $issuer_name = "";      
      if ( isset($parsed_cert["issuer"]) ) {
        foreach ( $parsed_cert["issuer"] as $key => $value ) {
            $issuer_name .= "/" . $key . "=" . $value;
        }
      }
      
      $parsed_cert["ISSUER_NAME"] = $issuer_name;
      
      ksort($parsed_cert);
      $captured_certs[] = $parsed_cert;
      
      $subject_cn = $parsed_cert["subject"]["CN"];
      $certificates[$subject_cn] = array( 
        $parsed_cert["subject"]["CN"],
        "issuer_cn" => $parsed_cert["issuer"]["CN"],
        "host_cert" => $host_cert
        );

    }
  }
  
  $end = 1;
  
  # Keep how many times we have gone through the chain to avoid an infinite loop
  # in case of an unforseen issue
  $count = 0;
  
  ##################################################################################
  # Let's walk down the certificate chain
  ##################################################################################
  if ( ! $success ) {
    while ( $end and $count < 6 ) {

      $count++;

      if ( isset($certificates[$issuer_cn] )) {
        if ( $debug ) print "Found " . $issuer_cn . " on the chain. Checking next\n";
          $issuer_cn = $certificates[$issuer_cn]["issuer_cn"];
        } else if ( isset($ca_certs[$issuer_cn])) {
          if ( $debug ) print "Found " . $issuer_cn . " on in the CA store. We are good\n";
          $success = 1;
          $end = 0;
        } else {
          $success = 0;
          $end = 0;
        }
      }
  }

  fclose($fp);

  return(array(
    "certs" => $captured_certs,
    "success" => $success,
    "message" => $success ? "" : "Issuer \"" . $issuer_cn . "\" not found in intermediates or CA store"
    )
  );
  
} // end of function
