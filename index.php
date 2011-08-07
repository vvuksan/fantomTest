<html>
<head>
<title>Page performance</title>
<link type="text/css" href="css/flick/jquery-ui-1.8.14.custom.css" rel="stylesheet" />
<link type="text/css" href="css/fantomtest.css" rel="stylesheet" />
<script type="text/javascript" src="js/jquery-1.6.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script>
function getTimings() {
    $("#results").html('<img src="img/spinner.gif">');
    $.get('waterfall.php', $("#query_form").serialize(), function(data) {
	$("#results").html(data);
     });
}
</script>
</head>
<body>
<div id="tabs">
    <ul>
	<li><a href="#tab-waterfall">URL Test</a></li>
    </ul>

<div id="tab-waterfall">
<div id="large_screenshot">
</div>
<div id=header>
<form id="query_form">
URL <input name="url" size=60>
<button id="query_button" onclick="getTimings(); return false;">Get timings</button>
</form>
</div>
<div id=results>
</div>
</div>
<script>
$(function(){
    $("#tabs").tabs();
    $("#query_button").button();

    $('#large_screenshot').dialog({
      title: "Large Picture",
      autoOpen: false,
      width: 800 });   

});
</script>
</body>
</html>