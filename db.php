<?php

class Database
{
	private $config;

	function __construct($config)
	{
		$this->config = $config;
	}

	function error()
	{
		return mysql_error($this->_db());
	}

	function query($sql)
	{
		$params = array_slice(func_get_args(), 1);

		if (empty($params) === FALSE) {
			foreach ($params as $p) {
				$pos = stripos($sql, '?');
				$sql = substr_replace($sql, mysql_real_escape_string($p), $pos, '?');
			}
		}

		$res = mysql_query($sql, $this->_db());

		if ($res !== FALSE) {
			return new DatabaseResult($res);
		}

		return NULL;
	}

	function query_first($sql)
	{
		$res = call_user_func_array(array($this, 'query'), func_get_args());

		if (empty($res) === FALSE && count($res) >= 1) {
			return $res[0];
		}

		return NULL;
	}

	function insert($table, $values)
	{
		// @TODO - implement insert function 
	}

	function update($table, $values, $where)
	{
		// @TODO - implement update function 
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

class DatabaseResult extends ArrayObject
{
	private $result;
	private $array;

	function __construct($result)
	{
		$this->result = $result;
	}

	function count()
	{
		return mysql_num_rows($this->result);
	}

	function offsetExists($index)
	{
		return ((int)$index < $this->count());
	}

	function offsetGet($index)
	{
		$index = (int)$index;
		if ($index > count($this->array)) {
			for ($i = count($this->array); $i < $index; $i++) {
				$this->array[] = mysql_fetch_object($this->result);
			}
		}

		return $this->array[$index];
	}
}
