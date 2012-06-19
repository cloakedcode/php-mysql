<?php
ini_set('display_errors', TRUE);
error_reporting(E_ALL);

require('db.php');

// Connect to the database and catch the error, if there is one.
try {
	$db = new Database(array('hostname' => 'localhost', 'username' => 'root', 'password' => 'mysql', 'database' => 'php-mysql'));
} catch (Exception $e) {
	die("Failed to connect to database.");
}

// Empty the table.
$db->query('TRUNCATE TABLE `test`');

// Test the 'insert' function.
$db->insert('test', array('body' => "This is my body, broken for you."));
if ($db->error() !== FALSE)
	echo "Failed to insert data into 'test' table.<br/>" . $db->error() . '<br/>';

// Test the querying of mulitple rows.
$db->insert('test', array('body' => "This is my blood, drink it in rememberance of me."));
$res = $db->query('SELECT * FROM `test`');
if ($res === NULL || count($res) < 2)
	echo "Failed to select data from 'test' table." . '<br/>';

// Test the 'query_first' function.
$res = $db->query_first('SELECT * FROM `test`');
if ($res === NULL)
	echo "Failed to select first entry from 'test' table." . '<br/>';

// Test the 'update' function.
$qoute = "Love your neighbor as thyself.";
$res = $db->update('test', array('body' => $qoute), array('id' => 2));
if ($db->error() !== FALSE || $db->query_first('SELECT body FROM `test` WHERE id = ?', 2)->body !== $qoute || $db->query_first('SELECT body FROM `test`')->body === $qoute)
	echo "Failed to update entry in 'test' table." . '<br/>';

// Just for fun demo the TestModel class.
$res = $db->query('SELECT * FROM `test`');
foreach ($res as $row) {
	echo $row;
}

class TestModel
{
	function __toString()
	{
		return "{$this->id}: {$this->body}<br/>";
	}
}
