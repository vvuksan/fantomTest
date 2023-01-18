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
<html>
<head>
<title>FantomTest Tools</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link type="text/css" href="<?php print $conf['jqueryui_css_path']; ?>" rel="stylesheet" />
<link type="text/css" href="css/fantomtest.css" rel="stylesheet" />
<script type="text/javascript" src="<?php print $conf['jquery_js_path']; ?>"></script>
<script type="text/javascript" src="<?php print $conf['jqueryui_js_path']; ?>"></script>
<script type="text/javascript" src="<?php print $conf['jquery_tablesorter']; ?>"></script>
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
    $.get('waterfall.php', $("#query_form").serialize(), function(data) {
	$("#results").html(data);
        var totalTime = parseInt(parseFloat($('#total-time').text()) * 1000);
        buildTimeTicks(totalTime);
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
    $.get('get_url.php', $("#url_form").serialize(), function(data) {
	$("#url_results").html(data);
     });
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
	<li><a href="#tab-tls-cert">TLS certificate</a></li>
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
  <form id="query_form">
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
  URL <input id="checked_url" name="url" size=100>
  <button class="query_buttons" id="query_button" onclick="getTimings(); return false;">Get waterfall</button>
  </form>
  </div>
  <div id=results>
  </div>
</div>

<?php
}
?>

<div id="tab-url">
  <div id=header>
  
  <form id="url_form">
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
  URL <input id="url" name="url" size=100>
  Max time to wait for load <input id="timeout" name="timeout" size=5 value=60>
  <button class="query_buttons" id="url_querybutton" onclick="getURL(); return false;">Get timings</button><p>
  Arbitrary headers (multiple need to be || delimited e.g. Cookie: 1234 || Accept-Language: es):
  <input name="arbitrary_headers" <?php if ( isset($conf['arbitrary_headers']) ) print "value=\"" . htmlentities($conf['arbitrary_headers']) . "\""; ?> size=80>
  </form>
  </div>
  <div id=url_results>
  </div>

</div>


<div id="tab-dns">
  <div id=header>
  
  <form id="dns_form">
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
  Host name <input id="hostname" name="hostname" size=100>
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
  
  <form id="pingmtr_form">
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
  Host name <input id="hostname" name="hostname" size=80>
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
  <form id="tls_cert_form">
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
  Host name <input id="hostname" name="hostname" size=100>
  Port <input id="port" name="port" value=443 size=6> <p />
  Optional SNI name (usually blank): <input id="sni_name" name="sni_name" size=60>
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

    $('#large_screenshot').dialog({
      title: "Large Picture",
      autoOpen: false,
      width: 800 });   

});
var myhash = window.location.hash;
if ( myhash != "" ) {
  $("#checked_url").val(myhash.replace("#",""));
  getTimings();
}
</script>
</body>
</html>
