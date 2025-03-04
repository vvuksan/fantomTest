<?php
$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

include_once("./tools.php");

?>
<!DOCTYPE html>
<html>
<head>
<title>FantomTest Tools</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link type="text/css" href="<?php print $conf['jqueryui_css_path']; ?>" rel="stylesheet" />
<link type="text/css" href="css/fantomtest.css" rel="stylesheet" />
<script type="text/javascript" src="<?php print $conf['jquery_js_path']; ?>"></script>
<script type="text/javascript" src="<?php print $conf['jqueryui_js_path']; ?>"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/purecss@3.0.0/build/pure-min.css" integrity="sha384-X38yfunGUhNzHpBaEBsWLO+A0HDYOQi8ufWDkZ0k9e0eXz/tH3II7uKZ9msv++Ls" crossorigin="anonymous">
<script>

<?php
# Enable
if ( $waterfall_output ) {
?>

function buildTimeTicks(totalTime) {
    console.log("Total time " + totalTime);
    
    var defaultMarks = [10, 100, 200, 250, 500, 1000, 2000, 5000, 10000],
        target = totalTime / 8,
        mark = target;

    for (var i = 0; i < defaultMarks.length; i++) {
        if (defaultMarks[i] > target) {
            mark = defaultMarks[i];
            break;
        }
    }

    var timelineWidth = $('table tr td.timeline-data').width(),
        timelineHeight = $('table').height(),
        pixelRatio = 1;

    if (window.devicePixelRatio) {
        pixelRatio = window.devicePixelRatio;
    }

    var marks = $('<div><canvas></canvas></div>'),
        canvas = $('canvas', marks),
        ctx = canvas.get(0).getContext('2d'),
        results = $('#results');

    canvas.css({ width: timelineWidth + 'px', height: timelineHeight + 'px' });
    canvas.attr('width', timelineWidth * pixelRatio);
    canvas.attr('height', timelineHeight * pixelRatio);

    results.css('position', 'relative');
    marks.css({
        position: 'absolute',
        'top': $('table tr:first-child').height() + 'px',
        right: '3px',
    });
    results.append(marks); 

    var tickWidth = pixelRatio * (timelineWidth * mark / totalTime),
        x = tickWidth,
        time = mark;


    ctx.fillStyle = '#333';
    ctx.font = '14pt helvetica';
    ctx.beginPath();
    // Add Zero line
    ctx.fillText("0", 0, 30);
    ctx.moveTo(0, 40);
    ctx.lineTo(0, timelineHeight * pixelRatio);
    while (x < timelineWidth * pixelRatio) {
        if (time < 1000) {
            ctx.fillText(time + "ms", x+1, 30);
            ctx.fillText(time + "ms", x+1, pixelRatio*(timelineHeight));
        }
        else {
            ctx.fillText((time / 1000).toFixed(2) + "s", x+1, 30);
            ctx.fillText((time / 1000).toFixed(2) + "s", x+1, pixelRatio*timelineHeight);
        }
        ctx.moveTo(x, 40);
        ctx.lineTo(x, timelineHeight * pixelRatio);
        x += tickWidth;
        time += mark;
    }
    ctx.strokeStyle = '#333';
    ctx.stroke();
}

function getTimings() {
    window.location.hash = $("#checked_url").val();
    $("#results").html('<img src="img/spinner.gif">');
    var form = $("#query_form").closest("form");
    var formData = new FormData(form[0]);
    $.ajax({
      url: "waterfall.php",
      method: "POST",
      processData: false,
      contentType: false,
      data: formData,
      success: function (data) {
        $("#results").html(data);
        var totalTime = parseInt(parseFloat($('#total-time').text()) * 1000);
        buildTimeTicks(totalTime);
      },
      error: function (e) {
          //error
      }
    });

}

<?php

}

?>

function getDns() {
    $("#dns_results").html('<img src="img/spinner.gif">');
    $.get('get_dns.php', $("#dns_form").serialize(), function(data) {
	$("#dns_results").html(data);
     });
}
function getURL() {
    $("#url_results").html('<img src="img/spinner.gif">');
    $.post('get_url.php', $("#url_form").serialize(), function(data) {
	  $("#url_results").html(data);
    });
}

function getURLPowerUser() {
    $("#url_results").html('<img src="img/spinner.gif">');
    $.ajax({
      url: "get_url.php",
      method: "POST",
	  data: $("#url_poweruser").val(),
      dataType: "html",
      contentType: "application/json",
      success: function (data) {
        $("#url_results").html(data);
      },
      error: function (e) {
          //error
      }
    });
}

function checkHTTPMethod() {
	let selected_value = $("#http-method").find(":selected").val();
	if ( selected_value == "GET" || selected_value == "HEAD" ) {
	  $("#url-payload").prop('disabled', true);
	  $("#url-content-type").prop('disabled', true);
	} else {
	  $("#url-payload").prop('disabled', false);
	  $("#url-content-type").prop('disabled', false);
	}
}

<?php
if ( $pingmtr_enabled ) {
?>
function getPingMtr() {
    $("#pingmtr_results").html('<img src="img/spinner.gif">');
    $.get('get_mtr.php', $("#pingmtr_form").serialize(), function(data) {
	$("#pingmtr_results").html(data);
     });
}
<?php
}
?>

<?php
if ( $pingmtr_enabled ) {
?>
function getTLSCiphers() {
    $("#tls_ciphers_results").html('<img src="img/spinner.gif">');
    $.get('get_ssl_ciphers.php', $("#tls_ciphers_form").serialize(), function(data) {
	$("#tls_ciphers_results").html(data);
     });
}
<?php
}
?>


function getTLSCert() {
    $("#tls_cert_results").html('<img src="img/spinner.gif">');
    $.get('get_ssl_cert.php', $("#tls_cert_form").serialize(), function(data) {
	$("#tls_cert_results").html(data);
     });
}
</script>
</head>
<body>
<?php
if ( is_readable("./banner.php") ) {
   print "<div id='banner'>";
   include_once("./banner.php");
   print "</div>";
}
?>
<div id="tabs">
    <ul>
<?php 
if ( $waterfall_output ) {
?>
	<li><a href="#tab-waterfall">Page Waterfall</a></li>
<?php
}
?>
	<li><a href="#tab-url">URL Test</a></li>
	<li><a href="#tab-dns">DNS</a></li>
<?php
if ( $pingmtr_enabled ) {
?>	
	<li><a href="#tab-pingmtr">Ping/MTR</a></li>
<?php
}
?>
	<li><a href="#tab-tls-cert">TLS certificates</a></li>
<?php
if ( $tlsciphers_enabled ) {
?>
        <li><a href="#tab-tls-ciphers">TLS ciphers</a></li>
<?php
}
?>
    </ul>

    
<?php 
################################################################################################
# Waterfall
################################################################################################
if ( $waterfall_output ) {
?>

<div id="tab-waterfall">
  <div id=header>
  <form id="query_form" class="pure-form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
      print "Test from <select name='site_id'><option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  <input type="text" name="url" id="checked_url" class="pure-input-rounded" placeholder="URL" size=100 required=""/>
  <button class="query_buttons" id="query_button" onclick="getTimings(); return false;">Get waterfall</button>
  <p />
  or upload a <a target="_blank" href="https://support.zendesk.com/hc/en-us/articles/4408828867098-Generating-a-HAR-file-for-troubleshooting" title="How go generate a HAR file">HAR (HTTP archive)</a>
  <input type="file" id="har_file" name="har_file" class="pure-button" onchange='$("#checked_url").val(""); getTimings(); return false;'>

  <button class="query_buttons" onclick="$('#query_form')[0].reset(); return(false)">Reset Form</button>
  </form>
  </div>
  <div id=results>
  </div>
</div>

<?php
}
?>
<?php 

################################################################################################
# URL Check
################################################################################################

?>
<div id="tab-url">
  <div id=header>

  <div id="url-accordion">
	  <h3>Form input</h3>
  <div>
  <form id="url_form" class="pure-form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  <p>
  <select class="pure-input-rounded" name="protocol" id="http-protocol">
     <option value="http2">HTTP 2</option>
     <option value="http1.1">HTTP 1.1</option>
  </select>
  <select class="pure-input-rounded" name="method" onChange="checkHTTPMethod()" id="http-method">
   <?php
   foreach($conf['allowed_http_methods'] as $method) {
	   print "<option>" . $method . "</option>";
   }?>
  </select>
  <input type="text" name="url" id="url" class="pure-input-rounded" placeholder="URL" size=100 required=""/>
  Max time to wait for load <input id="timeout" class="pure-input-rounded" name="timeout" type="number" value=60>
  </p>
  <p>
  <span class="pure-form-message">Optional: </span> <input name="arbitrary_headers" class="pure-input-rounded" placeholder="Arbitrary headers (multiple need to be || delimited e.g. Cookie: 1234 || Accept-Language: es)" <?php if ( isset($conf['arbitrary_headers']) ) print "value=\"" . htmlentities($conf['arbitrary_headers']) . "\""; ?> size=80>
  &nbsp;<input name="override_ip" class="pure-input-rounded" placeholder="Override IP/Hostname" size=50>
  <?php if ( $conf['allow_proxy_for_url_check'] ) {
  ?> <input name="http_proxy" class="pure-input-rounded" placeholder="HTTP proxy" size=50>
  <?php } ?>
  </p>
  <p>
  <span class="pure-form-message">Payload content Type: </span>
  <select class="pure-input-rounded" id="url-content-type" name="url-content-type" disabled>
      <option>application/x-www-form-urlencoded</option>
      <option>application/json</option>
      <option>multipart/form-data</option>
  </select>
  <textarea class="pure-input-1-2" name="payload" id="url-payload" disabled placeholder="Optional Payload. Available for methods other than GET/HEAD"></textarea>  
  <button class="query_buttons" id="url_querybutton" onclick="getURL(); return false;">Get timings</button><p>
  </p>
  </form>
  </div>
    <h3>Power user</h3>
  <div>
	<form class="pure-form">
      <textarea class="pure-input-19-24" name="json-poweruser" id="url_poweruser" 
		rows="10" cols="150" placeholder="Request JSON"></textarea>  
	  <button class="query_buttons" id="url_poweruser_button" onclick="getURLPowerUser(); return false;">Get timings</button><p>
	</form>
  </div>
  </div>
  <script>
  $( function() {
    $( "#url-accordion" ).accordion();
  } );
  </script>
  </div>
  <div id=url_results>
  </div>

</div>
<?php 

################################################################################################
# DNS Check
################################################################################################

?>
<div id="tab-dns">
  <div id=header>
  
  <form id="dns_form" class="pure-form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  <input id="hostname" name="hostname" placeholder="Name to resolve" size=100>
  Query Type <select name="query_type">
  <?php
  
  foreach ( $conf['allowed_dns_query_types'] as $query_type ) {
    print "<option value=\"" . $query_type . "\">" . $query_type . "</a>";
  }
  
  ?>
  </select>
  
  <button class="query_buttons" id="dns_querybutton" onclick="getDns(); return false;">Resolve</button>
  <br />
  </form>
  </div>
  <div id=dns_results>
  </div>
</div>

<?php
#############################################################################################
# Ping mtr
if ( $pingmtr_enabled ) {
?>

<div id="tab-pingmtr">
  <div id=header>
  
  <form id="pingmtr_form" class="pure-form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and count($conf['remotes'] ) > 0 ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  <input id="hostname" name="hostname" placeholder="Hostname or IP" size=80>
  # Pings <input id="ping_count" name="ping_count" value=5 size=4>
  <button class="query_buttons" id="ping_querybutton" onclick="getPingMtr(); return false;">Ping/MTR</button>
  <br />
  </form>
  </div>
  <div id=pingmtr_results>
  </div>
</div>
<?php
}
?>

<div id="tab-tls-cert">
  <div id=header>
  <form id="tls_cert_form" class="pure-form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and count($conf['remotes'] ) > 0 ) {
      print "Test from <select name='site_id'>
      <option value='-1'>Local</option>
      <option value='-100'>All Remotes</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  <p>
  <input id="hostname" name="hostname" type="text" placeholder="Host name or IP" size=100>
  Port <input id="port" name="port" type="number" value=443 size=2> <p />
  <span class="pure-form-message">Optional: </span> <input id="sni_name" name="sni_name" placeholder="SNI Hostname" size=60>
  <button class="query_buttons" id="ssl_querybutton" onclick="getTLSCert(); return false;">Get certificate</button>
  <br />
  </form>
  </div>
  <div id=tls_cert_results>

  </div>
</div>

<?php
if ( $tlsciphers_enabled ) {
?>
<div id="tab-tls-ciphers">
  <div id=header>
  
  <form id="tls_ciphers_form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and count($conf['remotes'] ) > 0 ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  Host name <input id="hostname" name="hostname" size=100>
  Port <input id="port" name="port" value=443 size=6>
  <button class="query_buttons" id="tls_ciphers_querybutton" onclick="getTLSCiphers(); return false;">Get TLS ciphers</button>
  <br />
  </form>
  </div>
  <div id=tls_ciphers_results>
  </div>
</div>
<?php
}
?>

<script>
$(function(){
    $("#tabs").tabs();
    $(".query_buttons").button();

    $( document ).tooltip();

});
var myhash = window.location.hash;
if ( myhash.indexOf("#http") == 0 ) {
  $("#checked_url").val(myhash.replace("#",""));
  getTimings();
}
</script>
</body>
</html>
