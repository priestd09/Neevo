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
 * The map of observers - instances of INeevoObserver.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoObserverMap implements Iterator, Countable {


	/** @var array */
	private $storage = array();

	/** @var int */
	private $pointer = 0;


	/**
	 * Add given observer to map for given event.
	 * @param INeevoObserver $observer
	 * @param int $event Event bitmap
	 * @return void
	 */
	public function attach(INeevoObserver $observer, $event){
		$this->storage[spl_object_hash($observer)] = array(
			'observer' => $observer,
			'event' => $event
		);
	}


	/**
	 * Remove given observer from map.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detach(INeevoObserver $observer){
		unset($this->storage[spl_object_hash($observer)]);
	}


	/**
	 * Check if given observer is in the map.
	 * @param INeevoObserver $observer
	 * @return bool
	 */
	public function contains(INeevoObserver $observer){
		return isset($this->storage[spl_object_hash($observer)]);
	}


	/**
	 * Get the event associated with current observer in iteration.
	 * @return int
	 */
	public function getEvent(){
		$c = current($this->storage);
		return $c['event'];
	}


	/**
	 * Get number of observers in map.
	 * @return int
	 */
	public function count(){
		return count($this->storage);
	}


	/**
	 * Rewind internal pointer.
	 * @return void
	 */
	public function rewind(){
		reset($this->storage);
		$this->pointer = 0;
	}


	/**
	 * Move to next observer.
	 * @return void
	 */
	public function next(){
		next($this->storage);
		$this->pointer++;
	}


	/**
	 * Check for valid current observer.
	 * @return bool
	 */
	public function valid(){
		return key($this->storage) !== null;
	}


	/**
	 * Return the current observer.
	 * @return INeevoObserver
	 */
	public function current(){
		$current = current($this->storage);
		return $current['observer'];
	}


	/**
	 * Return the key of current observer.
	 * @return int
	 */
	public function key(){
		return $this->pointer;
	}


}
