<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 */


/**
 * Neevo statement abstract base ancestor.
 *
 * @method NeevoBaseStmt and($expr, $value = true)
 * @method NeevoBaseStmt or($expr, $value = true)
 * @method NeevoBaseStmt if($condition)
 * @method NeevoBaseStmt else()
 * @method NeevoBaseStmt end()
 *
 * @author Martin Srank
 * @package Neevo
 */
abstract class NeevoBaseStmt implements INeevoObservable {


	/** @var string */
	protected $source;

	/** @var string */
	protected $type;

	/** @var int */
	protected $limit;

	/** @var int */
	protected $offset;

	/** @var array */
	protected $conditions = array();

	/** @var array */
	protected $sorting = array();

	/** @var float */
	protected $time;

	/** @var bool */
	protected $performed;

	/** @var NeevoConnection */
	protected $connection;

	/** @var array Event type conversion table */
	protected static $eventTable = array(
		Neevo::STMT_SELECT => INeevoObserver::SELECT,
		Neevo::STMT_INSERT => INeevoObserver::INSERT,
		Neevo::STMT_UPDATE => INeevoObserver::UPDATE,
		Neevo::STMT_DELETE => INeevoObserver::DELETE
	);

	/** @var array */
	protected $subqueries = array();

	/** @var NeevoObserverMap */
	protected $observers;

	/** @var array */
	private $stmtConditions = array();


	/**
	 * Create statement.
	 * @param NeevoConnection $connection
	 * @return void
	 */
	public function __construct(NeevoConnection $connection){
		$this->connection = $connection;
		$this->observers = new NeevoObserverMap;
	}


	/**
	 * String representation of object.
	 * @return string
	 */
	public function __toString(){
		return (string) $this->parse();
	}


	/**
	 * Create clone of object.
	 * @return void
	 */
	public function __clone(){
		$this->resetState();
	}


	/**
	 * @return NeevoBaseStmt fluent interface
	 * @internal
	 * @throws BadMethodCallException
	 * @throws InvalidArgumentException
	 */
	public function __call($name, $args){
		$name = strtolower($name);

		// AND/OR where() glues
		if(in_array($name, array('and', 'or'))){
			if($this->validateConditions())
				return $this;

			$this->resetState();
			$this->conditions[count($this->conditions)-1]['glue'] = strtoupper($name);
			if(count($args) >= 1)
				call_user_func_array(array($this, 'where'), $args);
			return $this;
		}

		// Conditional statements
		elseif(in_array($name, array('if', 'else', 'end'))){

			// Parameter counts
			if(count($args) < 1 && $name == 'if')
				throw new InvalidArgumentException('Missing argument 1 for '.__CLASS__."::$name().");

			$conds = & $this->stmtConditions;
			if($name == 'if')
				$conds[] = (bool) $args[0];
			elseif($name == 'else')
				$conds[count($conds)-1] = !end($conds);
			elseif($name == 'end')
				array_pop($conds);

			return $this;

		}
		throw new BadMethodCallException('Call to undefined method '.__CLASS__."::$name()");
	}


	/*  ************  Statement clauses  ************  */


	/**
	 * Set WHERE condition. Accepts infinite arguments.
	 *
	 * More calls append conditions with 'AND' operator. Conditions can also be specified
	 * by calling and() / or() methods the same way as where().
	 * Corresponding operator will be used.
	 * @param string $expr
	 * @param mixed $value
	 * @return NeevoBaseStmt fluent interface
	 */
	public function where($expr, $value = true){
		if(is_array($expr) && $value === true)
			return call_user_func_array(array($this, 'where'), $expr);

		if($this->validateConditions())
			return $this;

		$this->resetState();

		// Simple format
		if(strpos($expr, '%') === false){
			$field = trim($expr);
			$this->conditions[] = array(
				'simple' => true,
				'field' => $field,
				'value' => $value,
				'glue' => 'AND'
			);
			if($value instanceof self)
				$this->subqueries[] = $value;
			return $this;
		}

		// Format with modifiers
		$args = func_get_args();
		array_shift($args);
		preg_match_all('~%(bin|sub|b|i|f|s|d|a|l)?~i', $expr, $matches);
		$this->conditions[] = array(
			'simple' => false,
			'expr' => $expr,
			'modifiers' => $matches[0],
			'types' => $matches[1],
			'values' => $args,
			'glue' => 'AND'
		);
		foreach($args as $arg){
			if($arg instanceof self)
				$this->subqueries[] = $arg;
		}
		return $this;
	}


	/**
	 * Define order. More calls append rules.
	 * @param string|array|Traversable $rule
	 * @param string $order Use constants - Neevo::ASC, Neevo::DESC
	 * @return NeevoBaseStmt fluent interface
	 */
	public function order($rule, $order = null){
		if($this->validateConditions())
			return $this;

		$this->resetState();

		if(is_array($rule) || $rule instanceof Traversable){
			foreach($rule as $key => $val){
				$this->order($key, $val);
			}
			return $this;
		}
		$this->sorting[] = array($rule, $order);

		return $this;
	}


	/**
	 * Set LIMIT and OFFSET clauses.
	 * @param int $limit
	 * @param int $offset
	 * @return NeevoBaseStmt fluent interface
	 */
	public function limit($limit, $offset = null){
		if($this->validateConditions())
			return $this;

		$this->resetState();
		$this->limit = array($limit,
			($offset !== null && $this->type === Neevo::STMT_SELECT) ? $offset : null);
		return $this;
	}


	/**
	 * Randomize order. Removes any other order clause.
	 * @return NeevoBaseStmt fluent interface
	 */
	public function rand(){
		if($this->validateConditions())
			return $this;

		$this->resetState();
		$this->connection->getDriver()->randomizeOrder($this);
		return $this;
	}


	/*  ************  Statement manipulation  ************  */


	/**
	 * Print out syntax highlighted statement.
	 * @param bool $return
	 * @return string|NeevoBaseStmt fluent interface
	 */
	public function dump($return = false){
		$sql = PHP_SAPI === 'cli' ? $this->parse() . "\n" : Neevo::highlightSql($this->parse());
		if(!$return)
			echo $sql;
		return $return ? $sql : $this;
	}


	/**
	 * Perform the statement.
	 * @return resource|bool
	 */
	public function run(){
		$start = -microtime(true);

		$query = $this->performed ?
			$this->resultSet : $this->connection->getDriver()->runQuery($this->parse());

		$this->time = $start + microtime(true);

		$this->performed = true;
		$this->resultSet = $query;

		$this->notifyObservers(self::$eventTable[$this->type]);

		return $query;
	}


	/**
	 * Perform the statement. Alias for run().
	 * @return resource|bool
	 */
	public function exec(){
		return $this->run();
	}


	/**
	 * Build the SQL statement from the instance.
	 * @return string The SQL statement
	 * @internal
	 */
	public function parse(){
		if($this->hasCircularReferences($this))
			throw new RuntimeException('Circular reference found, aborting.');

		$this->connection->connect();

		$parser = $this->connection->getParser();
		$instance = new $parser($this);
		return $instance->parse();
	}


	/*	 * ***********  INeevoObservable implementation  ************  */


	/**
	 * Attach given observer to given event.
	 * @param INeevoObserver $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer, $event){
		$this->observers->attach($observer, $event);
	}


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer){
		$this->observers->detach($observer);
	}


	/**
	 * Notify all observers attached to given event.
	 * @param type $event
	 * @return void
	 */
	public function notifyObservers($event){
		foreach($this->observers as $observer){
			if($event & $this->observers->getEvent())
				$observer->updateStatus($this, $event);
		}
	}


	/*  ************  Getters  ************  */


	/**
	 * Query execution time.
	 * @return int
	 */
	public function getTime(){
		return $this->time;
	}


	/**
	 * If query was performed.
	 * @return bool
	 */
	public function isPerformed(){
		return $this->performed;
	}


	/**
	 * Get full table name (with prefix).
	 * @return string
	 */
	public function getTable(){
		$table = str_replace(':', '', $this->source);
		$prefix = $this->connection->getPrefix();
		return $prefix . $table;
	}


	/**
	 * Statement type.
	 * @return string
	 */
	public function getType(){
		return $this->type;
	}


	/**
	 * Get LIMIT and OFFSET clauses.
	 * @return array
	 */
	public function getLimit(){
		return $this->limit;
	}


	/**
	 * Statement WHERE clause.
	 * @return array
	 */
	public function getConditions(){
		return $this->conditions;
	}


	/**
	 * Statement ORDER BY clause.
	 * @return array
	 */
	public function getSorting(){
		return $this->sorting;
	}


	/**
	 * Name of the PRIMARY KEY column.
	 * @return string|null
	 */
	public function getPrimaryKey(){
		$table = $this->getTable();
		if(!$table)
			return null;
		$key = null;
		$cached = $this->connection->getCache()->fetch($table . '_primaryKey');

		if($cached === null){
			try{
				$key = $this->connection->getDriver()->getPrimaryKey($table);
			} catch(NeevoException $e){
				return null;
			}
			$this->connection->getCache()->store($table . '_primaryKey', $key);
			return $key === '' ? null : $key;
		}
		return $cached === '' ? null : $cached;
	}


	/*  ************  Internal methods  ************  */


	/**
	 * Get the connection instance.
	 * @return NeevoConnection
	 */
	public function getConnection(){
		return $this->connection;
	}


	/**
	 * Reset the state of the statement.
	 * @return void
	 */
	protected function resetState(){
		$this->performed = false;
		$this->resultSet = null;
		$this->time = null;
	}


	/**
	 * Validate the current statement condition.
	 * @return bool
	 */
	protected function validateConditions(){
		if(empty($this->stmtConditions))
			return false;
		foreach($this->stmtConditions as $cond){
			if($cond) continue;
			else return true;
		}
		return false;
	}


	/**
	 * Check the query tree for circular references.
	 * @param NeevoBaseStmt $parent
	 * @param array $visited
	 * @return bool True if circular reference found.
	 */
	protected function hasCircularReferences($parent, $visited = array()){
		foreach($parent->subqueries as $child){
			if(isset($visited[spl_object_hash($child)]))
				return true;
			$visited[spl_object_hash($child)] = true;
			if($this->hasCircularReferences($child, $visited))
				return true;
			array_pop($visited);
		}
		return false;
	}


}