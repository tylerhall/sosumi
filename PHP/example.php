<?PHP
    require 'class.sosumi.php';
    
    // You'll need to enter your own Google Maps API key
    // Get one from here: http://code.google.com/apis/maps/signup.html
    $google_maps_key = '';

    // Enter your MobileMe username and password
    $ssm = new Sosumi('your_username', 'your_password');
    $loc = $ssm->locate();
    
    if(isset($_POST['btnSend']))
    {
        $alarm = isset($_POST['alarm']);
        $ssm->sendMessage($_POST['msg'], $alarm);
        header('Location: ' . $_SERVER['PHP_SELF']);
    }
?>
<!DOCTYPE html "-//W3C//DTD XHTML 1.0 Strict//EN" 
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Sosumi</title>
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.8.0r4/build/reset-fonts-grids/reset-fonts-grids.css">
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.8.0r4/build/base/base-min.css">
    <style type="text/css" media="screen">
        p { text-align:left; }
        #map_canvas { width:640px; height:480px; border:1px solid #000; }
    </style>
    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=<?PHP echo $google_maps_key; ?>&amp;sensor=false" type="text/javascript"></script>
    <script type="text/javascript">
        function initialize() {
            function zoomFit() {
                newzoom = map.getBoundsZoomLevel(bounds);
                newcenter = bounds.getCenter();
                map.setCenter(newcenter, newzoom);
            }

            function createMarker(point, msg) {
                bounds.extend(point);
                var marker = new GMarker(point);
                GEvent.addListener(marker, "click", function() {
                    map.openInfoWindowHtml(point, msg);
                });
                zoomFit();
                return marker;
            }

            if (GBrowserIsCompatible()) {
                var bounds = new GLatLngBounds();

                var map = new GMap2(document.getElementById("map_canvas"));
                map.setUIToDefault();

                var point = new GLatLng(<?PHP echo $loc['latitude']; ?>, <?PHP echo $loc['longitude']; ?>);
                map.addOverlay(createMarker(point, "Your Location"));
            }
        }
    </script>
</head>
<body onload="initialize()" onunload="GUnload()">
    <form action="" method="post">
        <p>
            <label for="msg">Message:</label>
            <input type="text" name="msg" value="" id="msg">
            <input type="checkbox" name="alarm" value="1" id="alarm">
            <label for="alarm">Alarm?</label>
            <input type="submit" name="btnSend" value="Send" id="btnSend">
        </p>
    </form>
    <div id="map_canvas"></div>
</body>
</html>
