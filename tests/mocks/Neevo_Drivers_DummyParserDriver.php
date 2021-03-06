<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Drivers;

use DateTime;
use InvalidArgumentException;
use Neevo\BaseStatement;
use Neevo\DriverInterface;
use Neevo\Manager;
use Neevo\Parser;

/**
 * Dummy Neevo driver/parser.
 */
class DummyParserDriver extends Parser implements DriverInterface {


	/** @var BaseStatement */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	function __construct(BaseStatement $statement = null){
		if($statement !== null)
			return parent::__construct($statement);
	}

	function connect(array $config){}
	function closeConnection(){}
	function freeResultSet($resultSet){}
	function runQuery($queryString){}
	function beginTransaction($savepoint = null){}
	function commit($savepoint = null){}
	function rollback($savepoint = null){}
	function fetch($resultSet){}
	function seek($resultSet, $offset){}
	function getInsertId(){}
	function randomizeOrder(BaseStatement $statement){}
	function getNumRows($resultSet){}
	function getAffectedRows(){}
	function getPrimaryKey($table){}
	function getColumnTypes($resultSet, $table){}

	function escape($value, $type){
		switch($type){
			case Manager::BOOL:
				return $value ? 'true' : 'false';

			case Manager::TEXT:
				return "'$value'";

			case Manager::IDENTIFIER:
				return "`$value`";

			case Manager::BINARY:
				return "bin:'$value'";

			case Manager::DATETIME:
				return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new InvalidArgumentException('Unsupported data type.');
				break;
		}
	}

	function unescape($value, $type){
		return $value;
	}

	public function applyLimit($sql){
		return parent::applyLimit($sql);
	}

	public function escapeValue($value, $type = null){
		return parent::escapeValue($value, $type);
	}

	public function applyModifiers($expr, array $modifiers, array $values){
		return parent::applyModifiers($expr, $modifiers, $values);
	}

	public function parse(){
		return parent::parse();
	}

	public function parseDeleteStmt(){
		return parent::parseDeleteStmt();
	}

	public function parseFieldName($field, $table = false){
		return parent::parseFieldName($field, $table);
	}

	public function parseGrouping(){
		return parent::parseGrouping();
	}

	public function parseInsertStmt(){
		return parent::parseInsertStmt();
	}

	public function parseSelectStmt(){
		return parent::parseSelectStmt();
	}

	public function parseSorting(){
		return parent::parseSorting();
	}

	public function parseSource(){
		return parent::parseSource();
	}

	public function parseUpdateStmt(){
		return parent::parseUpdateStmt();
	}

	public function parseWhere(){
		return parent::parseWhere();
	}

	public function tryDelimite($expr){
		return parent::tryDelimite($expr);
	}


}