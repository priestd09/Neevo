<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author	 Martin Srank (http://smasty.net)
 * @license	http://neevo.smasty.net/license MIT license
 * @link    	 http://neevo.smasty.net/
 *
 */


/**
 * Neevo SQLite driver (PHP extension 'sqlite')
 *
 * Driver configuration:
 *  - database (or file)
 *  - charset => Character encoding to set (defaults to utf-8)
 *  - dbcharset => Database character encoding (will be converted to 'charset')
 *  - persistent (bool) => Try to find a persistent link
 *  - unbuffered (bool) => Sends query without fetching and buffering the result
 * 
 *  - updateLimit (bool) => Set TRUE if SQLite driver was compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT
 *  - resource (type resource) => Existing SQLite link
 *  - lazy, table_prefix... => see NeevoConnection
 * 
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverSQLite extends NeevoStmtParser implements INeevoDriver {


	/** @var string */
	private $charset;

	/** @var string */
	private $dbCharset;

	/** @var bool */
	private $updateLimit;

	/** @var resource */
	private $resource;

	/** @var bool */
	private $unbuffered;

	/** @var bool */
	private $persistent;

	/** @var int */
	private $affectedRows;

	/** @var array */
	private $tblData = array();


	/**
	 * Check for required PHP extension.
	 * @return void
	 * @throws NeevoException
	 */
	public function __construct(){
		if(!extension_loaded("sqlite")){
			throw new NeevoException("PHP extension 'sqlite' not loaded.");
		}
	}


	/**
	 * Create connection to database.
	 * @param array $config Configuration options
	 * @return void
	 * @throws NeevoException
	 */
	public function connect(array $config){
		NeevoConnection::alias($config, 'database', 'file');
		NeevoConnection::alias($config, 'updateLimit', 'update_limit');

		$defaults = array(
			'resource' => null,
			'updateLimit' => false,
			'charset' => 'UTF-8',
			'dbcharset' => 'UTF-8',
			'persistent' => false,
			'unbuffered' => false
		);

		$config += $defaults;

		// Connect
		if(is_resource($config['resource'])){
			$connection = $config['resource'];
		} elseif($config['persistent']){
			$connection = @sqlite_popen($config['database'], 0666, $error);
		} else{
			$connection = @sqlite_open($config['database'], 0666, $error);
		}

		if(!is_resource($connection)){
			throw new NeevoException("Opening database file '$config[database]' failed.");
		}

		$this->resource = $connection;
		$this->updateLimit = (bool) $config['updateLimit'];

		// Set charset
		$this->dbCharset = $config['dbcharset'];
		$this->charset = $config['charset'];
		if(strcasecmp($this->dbCharset, $this->charset) === 0){
			$this->dbCharset = $this->charset = null;
		}

		$this->unbuffered = $config['unbuffered'];
		$this->persistent = $config['persistent'];
	}


	/**
	 * Close the connection.
	 * @return void
	 */
	public function close(){
		if(!$this->persistent){
			@sqlite_close($this->resource);
		}
	}


	/**
	 * Free memory used by given result set.
	 * @param resource $resultSet
	 * @return bool
	 */
	public function free($resultSet){
		return true;
	}


	/**
	 * Execute given SQL statement.
	 * @param string $queryString
	 * @return resource|bool
	 * @throws NeevoException
	 */
	public function query($queryString){

		$this->affectedRows = false;
		if($this->dbCharset !== null){
			$queryString = iconv($this->charset, $this->dbCharset . '//IGNORE', $queryString);
		}

		if($this->unbuffered){
			$result = @sqlite_unbuffered_query($this->resource, $queryString, null, $error);
		} else{
			$result = @sqlite_query($this->resource, $queryString, null, $error);
		}

		if($error && $result === false){
			throw new NeevoException("Query failed. $error", sqlite_last_error($this->resource), $queryString);
		}

		$this->affectedRows = @sqlite_changes($this->resource);
		return $result;
	}


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function begin($savepoint = null){
		$this->query('BEGIN');
	}


	/**
	 * Commit statements in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function commit($savepoint = null){
		$this->query('COMMIT');
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null){
		$this->query('ROLLBACK');
	}


	/**
	 * Fetch row from given result set as an associative array.
	 * @param resource $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		$row = @sqlite_fetch_array($resultSet, SQLITE_ASSOC);
		if($row){
			$charset = $this->charset === null ? null : $this->charset.'//TRANSLIT';

			$fields = array();
			foreach($row as $key => $val){
				if($charset !== null && is_string($val)){
					$val = iconv($this->dbcharset, $charset, $val);
				}
				$key = str_replace(array('[', ']'), '', $key);
				$pos = strpos($key, '.');
				if($pos !== false){
					$key = substr($key, $pos + 1);
				}
				$fields[$key] = $val;
			}
			$row = $fields;
		}
		return $row;
	}


	/**
	 * Move internal result pointer.
	 * @param resource $resultSet
	 * @param int $offset
	 * @return bool
	 * @throws NeevoDriverException
	 */
	public function seek($resultSet, $offset){
		if($this->unbuffered){
			throw new NeevoDriverException('Cannot seek on unbuffered result.');
		}
		return @sqlite_seek($resultSet, $offset);
	}


	/**
	 * Get the ID generated in the INSERT statement.
	 * @return int
	 */
	public function insertId(){
		return @sqlite_last_insert_rowid($this->resource);
	}


	/**
	 * Randomize result order.
	 * @param NeevoStmtBase $statement
	 * @return void
	 */
	public function rand(NeevoStmtBase $statement){
		$statement->order('RANDOM()');
	}


	/**
	 * Get the number of rows in the given result set.
	 * @param resource $resultSet
	 * @return int|FALSE
	 * @throws NeevoDriverException
	 */
	public function rows($resultSet){
		if($this->unbuffered){
			throw new NeevoDriverException('Cannot seek on unbuffered result.');
		}
		return @sqlite_num_rows($resultSet);
	}


	/**
	 * Get the number of affected rows in previous operation.
	 * @return int
	 */
	public function affectedRows(){
		return $this->affectedRows;
	}


	/**
	 * Escape given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function escape($value, $type){
		switch($type){
			case Neevo::BOOL:
				return $value ? 1 :0;

			case Neevo::TEXT:
			case Neevo::BINARY:
				return "'". sqlite_escape_string($value) ."'";

			case Neevo::IDENTIFIER:
				return str_replace('[*]', '*', '[' . str_replace('.', '].[', $value) . ']');

			case Neevo::DATETIME:
				return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new InvalidArgumentException('Unsupported data type.');
				break;
		}
	}


	/**
	 * Decode given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function unescape($value, $type){
		if($type === Neevo::BINARY){
			return $value;
		}
		throw new InvalidArgumentException('Unsupported data type.');
	}


	/**
	 * Get the PRIMARY KEY column for given table.
	 * @param string $table
	 * @return string
	 */
	public function getPrimaryKey($table){
		$key = '';
		$pos = strpos($table, '.');
		if($pos !== false){
			$table = substr($table, $pos + 1);
		}
		if(isset($this->tblData[$table])){
			$sql = $this->tblData[$table];
		} else{
			$q = $this->query("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
			$r = $this->fetch($q);
			if($r === false){
				return '';
			}
			$this->tblData[$table] = $sql = $r['sql'];
		}

		$sql = explode("\n", $sql);
		foreach($sql as $field){
			$field = trim($field);
			if(stripos($field, 'PRIMARY KEY') !== false && $key === ''){
				$key = preg_replace('~^"(\w+)".*$~i', '$1', $field);
			}
		}
		return $key;
	}


	/**
	 * Get types of columns in given result set.
	 * @param resource $resultSet
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table){
		if(isset($this->tblData[$table])){
			$sql = $this->tblData[$table];
		} else{
			$q = $this->query("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
			$r = $this->fetch($q);
			if($r === false){
				return array();
			}
			$this->tblData[$table] = $sql = $r['sql'];
		}
		$sql = explode("\n", $sql);

		$cols = array();
		foreach($sql as $field){
			$field = trim($field);
			preg_match('~^"(\w+)"\s+(integer|real|numeric|text|blob).+$~i', $field, $m);
			if(isset($m[1], $m[2])){
				$cols[$m[1]] = $m[2];
			}
		}
		return $cols;
	}


	/*  ============  NeevoStmtParser overrides  ============  */


	/**
	 * Parse UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$sql = parent::parseUpdateStmt();
		if($this->updateLimit){
			$sql = $this->applyLimit($sql . $this->clauses[3]);
		}
		return $sql;
	}


	/**
	 * Parse DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		$sql = parent::parseDeleteStmt();
		if($this->updateLimit){
			$sql = $this->applyLimit($sql . $this->clauses[3]);
		}
		return $sql;
	}

	
}
