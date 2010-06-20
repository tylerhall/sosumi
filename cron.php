#!/usr/bin/php
<?PHP
	// This is a simple cron script you can use to track
	// your location over time. It uses the MySQL schema
	// pasted below.
	
	// CREATE TABLE `history` (
	//   `dt` datetime NOT NULL,
	//   `lat` decimal(10,6) NOT NULL,
	//   `lng` decimal(10,6) NOT NULL,
	//   UNIQUE KEY `dt` (`dt`)
	// )

	require 'class.sosumi.php';

	$ssm = new Sosumi('your_username', 'your_password');
	$loc = $ssm->locate();

	if(strlen($loc->latitude))
	{
		$db = mysql_connect('localhost', 'root', '');
		mysql_select_db('sosumi', $db) or die(mysql_error());

		$dt = date('Y-m-d H:i:s');
		$lat = mysql_real_escape_string($loc->latitude, $db);
		$lng = mysql_real_escape_string($loc->longitude, $db);

		$query = "INSERT INTO history (`dt`, `lat`, `lng`) VALUES ('$dt', '$lat', '$lng')";
		mysql_query($query, $db) or die(mysql_error());
	}
