<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Neevo Driver interface
 * @package Neevo
 */
interface INeevoDriver {


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo);

  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8
   * );</pre>
   * @return bool
   */
  public function connect(array $opts);


  /**
   * Closes given resource
   * @param resource $resource
   * @return void
   */
  public function close($resource);


  /**
   * Frees memory used by result
   * @param resource $result
   * @return bool
   */
  public function free($result);


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @param resource Connection resource
   * @return resource
   */
  public function query($query_string, $resource);


  /**
   * Returns error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return string
   */
  public function error($neevo_msg);


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resource Query resource
   * @return array
   */
  public function fetch($resource);


  /**
   * Move internal result pointer
   * @param resource $resource Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resource, $row_number);


  /**
   * Get the ID generated in the INSERT query
   * @param resource $resource Query resource
   * @return int
   */
  public function insertId($resource);


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query);


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return int|FALSE Number of rows (int) or FALSE
   */
  public function rows(NeevoQuery $query);


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query);


  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   */
  public function escapeString($string);


  /**
   * Returns driver-specific column quotes (opening and closing chars)
   * @return array
   */
  public function getQuotes();


  /**
   * Return Neevo class instance
   * @return Neevo
   */
  public function neevo();
}
