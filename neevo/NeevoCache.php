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
 * Neevo cache interface
 * @package NeevoCache
 */
interface INeevoCache {


  /**
   * Load stored data
   * @param string $key
   * @return mixed|null null if not found
   */
  public function load($key);
  

  /**
   * Save data
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function save($key, $value);

}


/**
 * Neevo session cache
 * @package NeevoCache
 */
class NeevoCacheSession implements INeevoCache {


  public function load($key){
    if(!isset($_SESSION['NeevoCache'][$key]))
      return null;
    return $_SESSION['NeevoCache'][$key];
  }


  public function save($key, $value){
    $_SESSION['NeevoCache'][$key] = $value;
  }

}


/**
 * Neevo file cache
 * @package NeevoCache
 */
class NeevoCacheFile implements INeevoCache {

  private $filename, $data = array();

  public function __construct($filename){
    $this->filename = $filename;
    $this->data = unserialize(@file_get_contents($filename)); // @ - file can not exist
  }


  public function load($key){
    if(!isset($this->data[$key]))
      return null;
    return $this->data[$key];
  }


  public function save($key, $value){
    $this->data[$key] = $value;
    file_put_contents($this->filename, serialize($this->data), LOCK_EX);
  }

}
?>