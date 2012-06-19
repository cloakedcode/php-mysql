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
		$err = mysql_error($this->_db());
		return (empty($err) ? FALSE : $err);
	}

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
		$sql = "INSERT INTO `{$table}` SET";
		$sql .= $this->_sql_set($values);
		
		return $this->query($sql);
	}

	function update($table, $values, $where)
	{
		$sql = "UPDATE `{$table}` SET";
		$sql .= $this->_sql_set($values);

		$sql .= ' WHERE ';
		if (is_array($where)) {
			$sql .= $this->_sql_set($where);
		} else {
			$sql .= $where;
		}

		return $this->query($sql);
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
	private $count;
	private $array;
	private $index;

	function __construct($result)
	{
		$this->result = $result;
		$this->count = mysql_num_rows($result);
		$this->array = array();
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
				$this->array[] = mysql_fetch_object($this->result);
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
