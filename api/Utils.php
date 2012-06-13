<?php


function isNew($id) {
        //if a given id is negative it must be new (contract, contract item or bid)
        return $id < 0;
}


function getConnection() {
	$options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');

	$dbhost="localhost";
	$dbuser="ihsondev";
	$dbpass="1hs0ndev";
	$dbname="ihson_dev";
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, $options);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	return $dbh;
}

?>
