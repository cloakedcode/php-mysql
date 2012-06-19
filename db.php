<?php

/**
 * All database results are returned as a special array of class 'DatabaseResult'.
 * The array can be put in a foreach loop and each item is an object. If a class exists with the same name of the table,
 * in camelcase, with the 'Model' suffix, that class will be used for each item in the array. For example:
 *
 * class UsersModel
 * {
 *	function print_name()
 *	{
 *		echo $this->first_name . ' ' . $this->last_name;
 *	}
 * }
 * 
 * $users = $db->query('SELECT * FROM `users`');
 * $users[0]->print_name();
 */

class Database
{
	private $config;

	/**
	 * $config = array(
	 *	'hostname' => 'localhost',
	 *	'username' => 'user',
	 *	'password' => 'pass',
	 *	'database' => 'test',
	 * );
	 */
	function __construct($config)
	{
		$this->config = $config;
	}

	/**
	 * Returns string on error, FALSE otherwise.
	 */
	function error()
	{
		$err = mysql_error($this->_db());
		return (empty($err) ? FALSE : $err);
	}

	/**
	 * query(sql_string, [param1, param2, ...])
	 *
	 * $db->query('SELECT * FROM `table` WHERE user_id = ? AND date < ?', 1, '2012-06-19');
	 *
	 * Returns an array if the query has results, NULL otherwise.
	 * (Use $db->error() to check for success of query if no results are returned.)
	 */
	function query($sql)
	{
		$params = array_slice(func_get_args(), 1);

		if (empty($params) === FALSE) {
			foreach ($params as $p) {
				$pos = stripos($sql, '?');
				$sql = substr_replace($sql, "'" . mysql_real_escape_string($p) . "'", $pos);
			}
		}

		$res = mysql_query($sql, $this->_db());

		if (is_resource($res)) {
			$matches = array();
			preg_match('/`(\w+)`/', $sql, $matches);

			return new DatabaseResult($res, $matches[1]);
		}

		return NULL;
	}

	/**
	 * query_first(sql_string, [param1, param2, ...])
	 * 
	 * $user = $db->query('SELECT * FROM `users` WHERE username = ? AND password = ?', $id, sha1($password));
	 *
	 * Returns the first result of a query if there is one, NULL otherwise.
	 */
	function query_first($sql)
	{
		$res = call_user_func_array(array($this, 'query'), func_get_args());

		if (empty($res) === FALSE && count($res) >= 1) {
			return $res[0];
		}

		return NULL;
	}

	/**
	 * insert($table_name, $array_of_field_name_values)
	 *
	 * insert('users', array('username' => 'alansmith', 'email' => 'me@sna.la'));
	 *
	 * Returns TRUE if the insert was successful, FALSE otherwise.
	 */
	function insert($table, $values)
	{
		$sql = "INSERT INTO `{$table}` SET";
		$sql .= $this->_sql_set($values);
		
		$this->query($sql);

		return ($this->error() === FALSE);
	}

	/**
	 * update($table_name, $array_of_field_name_values, $array_of_field_name_values/$where_string)
	 *
	 * update('users', array('name' => 'Alan Smith'), array('username' => 'alansmith', 'AND email' => 'me@sna.la'));
	 * OR
	 * update('users', array('name' => 'Alan Smith'), "username = 'alansmith' AND email = 'me@sna.la'");
	 *
	 * Returns TRUE if the update was successful, FALSE otherwise.
	 */
	function update($table, $values, $where)
	{
		$sql = "UPDATE `{$table}` SET";
		$sql .= $this->_sql_set($values);

		$sql .= ' WHERE';
		if (is_array($where)) {
			foreach ($values as $name => $val) {
				$sql .= " {$name} = '" . mysql_real_escape_string($val) . "'";
			}
		} else {
			$sql .= ' ' . $where;
		}

		$this->query($sql);

		return ($this->error() === FALSE);
	}

	private function _sql_set($values)
	{
		$sql = '';
		foreach ($values as $name => $val) {
			$sql .= " `{$name}` = '" . mysql_real_escape_string($val) . "',";
		}

		return substr($sql, 0, -1);
	}

	private function _db()
	{
		static $db = null;

		if ($db === null) {
			$db = mysql_connect($this->config['hostname'], $this->config['username'], $this->config['password']);

			if ($db === FALSE) {
				throw new Exception("Unable to connect to the database. Check the config.");
			} else {
				mysql_select_db($this->config['database'], $db);
			}
		}

		return $db;
	}
}

class DatabaseResult implements ArrayAccess, Countable, Iterator
{
	private $result;
	private $array;
	private $count;
	private $class_name;

	private $index;

	function __construct($result, $table)
	{
		$this->result = $result;
		$this->count = mysql_num_rows($result);
		$this->array = array();

		$name = str_ireplace(' ', '', ucwords(str_ireplace('_', ' ', $table))) . 'Model';
		if (class_exists($name)) {
			$this->class_name = $name;
		} else {
			$this->class_name = 'stdClass';
		}
	}

	function count()
	{
		return $this->count;
	}

	function offsetExists($index)
	{
		return ((int)$index < $this->count());
	}

	function offsetGet($index)
	{
		$index = (int)$index;
		if ($index + 1 > count($this->array)) {
			for ($i = count($this->array); $i < $index + 1; $i++) {
				$this->array[] = mysql_fetch_object($this->result, $this->class_name);
			}
		}

		return $this->array[$index];
	}
	
	function offsetSet($i, $v) {}
	function offsetUnset($i) {}

	function rewind()
	{
		$this->index = 0;
	}

	function valid()
	{
		return ($this->index < $this->count());
	}

	function next()
	{
		$this->index++;
	}

	function key()
	{
		return $this->index;
	}

	function current()
	{
		return $this->offsetGet($this->index);
	}
}
