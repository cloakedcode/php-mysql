<?php

/**
 * An ORM database PHP interface for MySQL (in under 150 LOC).
 *
 * Establishing the connection to the database is done lazily, when a SQL query is executed.
 * If a SQL query is never made, the connection is never established, conserving resources.
 *
 * All database results are returned as a special array of class `DatabaseResult`.
 * The array can be put in a foreach loop and each item is an object. If a class exists with the same name of the table,
 * in camelcase, with the `Model` suffix (e.g. `user_groups` becomes `UserGroupsModel`), that class will be used for each item in the array. For example:
 *
 *     class UsersModel
 *     {
 *	    function print_name()
 *	    {
 *		    echo $this->first_name . ' ' . $this->last_name;
 *	    }
 *     }
 *     
 *     $users = $db->query('SELECT * FROM `users`');
 *     $users[0]->print_name();
 */

class Database
{
	private $config;

	/**
	 * Creates a new database object with the given config info.
	 *
	 *     $config = array(
	 *         'hostname' => 'localhost',
	 *         'username' => 'user',
	 *         'password' => 'pass',
	 *         'database' => 'test',
	 *     );
	 *     $db = new Database($config);
	 */
	function __construct($config)
	{
		$this->config = $config;
	}

	/**
	 * Returns a string describing an error if the last SQL query resulted in an error.
	 *
	 * @return {String|Bool} String on error, FALSE otherwise.
	 */
	function error()
	{
		$err = mysql_error($this->_db());
		return (empty($err) ? FALSE : $err);
	}

	/**
	 * Queries the database and returns the result.
	 * Any number of extra parameters can be passed to be inserted into the SQL.
	 * All SQL parameters are carefully escaped, preventing SQL injection.
	 * 
	 * Use $db->error() to check for success of query if no results are returned.
	 *
	 *     $db->query('SELECT * FROM `table` WHERE user_id = ? AND date < ?', 1, '2012-06-19');
	 *
	 * @param {String} sql The SQL string to execute.
	 * @param {String} ... Extra parameters to be inserted into the SQL.
	 * @return {Array|Null} If the query has results an array is returned, NULL otherwise.
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
	 * Executes the SQL statement and returns the first row. The syntax is the same as `query`.
	 *
	 *     $user = $db->query('SELECT * FROM `users` WHERE username = ? AND password = ?', $id, sha1($password));
	 *
	 * @param {String} sql The SQL string to execute.
	 * @param {String} ... Extra parameters to be inserted into the SQL.
	 * @return {Object|Null} First result of a query as an object, if there is one, NULL otherwise.
	 * @see query
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
	 * Inserts a row into the table with the given data.
	 *
	 *     $db->insert('users', array('username' => 'alansmith', 'email' => 'me@sna.la'));
	 *
	 * @param {String} table Name of table to insert the row into.
	 * @param {Array} values Array of key/value pairs of data to insert.
	 * @return {Bool} TRUE if the insert was successful, FALSE otherwise.
	 */
	function insert($table, $values)
	{
		$sql = "INSERT INTO `{$table}` SET";
		$sql .= $this->_sql_set($values);
		
		$this->query($sql);

		return ($this->error() === FALSE);
	}

	/**
	 * Updates a row in the table specified by the `WHERE` conditions. The `where` parameter can be either a string or key/value array.
	 *
	 *     $db->update('users', array('name' => 'Alan Smith'), array('username' => 'alansmith', 'AND email' => 'me@sna.la'));
	 * OR
	 *
	 *     $db->update('users', array('name' => 'Alan Smith'), "username = 'alansmith' AND email = 'me@sna.la'");
	 *
	 * @param {String} table Name of table to insert the row into.
	 * @param {Array} values Array of key/value pairs of data to insert.
	 * @param {Array|String} where Array of key/value pairs or string to use in the `WHERE` clause.
	 * @return {Bool} TRUE if the update was successful, FALSE otherwise.
	 */
	function update($table, $values, $where)
	{
		$sql = "UPDATE `{$table}` SET";
		$sql .= $this->_sql_set($values);

		$sql .= ' WHERE';
		if (is_array($where)) {
			foreach ($where as $name => $val) {
				$sql .= " {$name} = '" . mysql_real_escape_string($val) . "'";
			}
		} else {
			$sql .= ' ' . $where;
		}

		$this->query($sql);

		return ($this->error() === FALSE);
	}

	/**
	 * Returns the auto-incremented ID of the last `INSERT` operation.
	 *
	 *     $id = $db->last_insert_id();
	 *
	 * @return {Integer} ID of the last `INSERT` operation.
	 */
	function last_insert_id()
	{
		return mysql_insert_id($this->_db());
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
