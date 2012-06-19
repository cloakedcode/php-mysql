<?php

ini_set('display_errors', TRUE);
error_reporting(E_ALL);

include('db.php');

try {
	$db = new Database(array('hostname' => 'localhost', 'username' => 'root', 'password' => 'mysql', 'database' => 'php-mysql'));
} catch (Exception $e) {
	die("Failed to connect to database.");
}

/*
$res = $db->query('INSERT INTO `test` SET body="This is the body. Break it and think of me."');
if ($res === NULL)
	die("Failed to insert data into 'test' table.");
	*/

$res = $db->query('SELECT * FROM `test`');
if ($res === NULL || count($res) <= 0)
	die("Failed to select data from `test` table.");
var_dump($res[0]);
