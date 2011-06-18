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
 * Main Neevo exception.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoException extends Exception implements INeevoObservable {


	/** @var string */
	protected $sql;

	/** @var NeevoObserverMap */
	protected static $observers;


	/**
	 * Construct exception.
	 * @param string $message
	 * @param int $code
	 * @param string $sql Optional SQL command
	 * @param Exception $previous
	 * @return void
	 */
	public function __construct($message = '', $code = 0, $sql = null, Exception $previous = null){

		parent::__construct($message, (int) $code, $previous);
		$this->sql = $sql;
		self::$observers = new NeevoObserverMap;
		$this->notifyObservers(INeevoObserver::EXCEPTION);
	}


	/**
	 * String representation of exception.
	 * @return string
	 */
	public function __toString(){
		return parent::__toString() . ($this->sql ? "\nSQL: $this->sql" : '');
	}


	/**
	 * Get given SQL command.
	 * @return string
	 */
	public function getSql(){
		return $this->sql;
	}


	/*  ============  Implementation of INeevoObservable  ============  */


	/**
	 * Attach given observer to given event.
	 * @param INeevoObserver $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer, $event){
		self::$observers->attach($observer, $event);
	}


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer){
		self::$observers->detach($observer);
	}


	/**
	 * Notify all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		foreach(self::$observers as $observer){
			if($event & self::$observers->getEvent())
				$observer->updateStatus($this, $event);
		}
	}


}



/**
 * Exception for features not implemented by the driver.
 * @author Martin Srank
 * @package Neevo\Drivers
 */
class NeevoImplementationException extends NeevoException {}



/**
 * Neevo driver exception.
 * @author Martin Srank
 * @package Neevo\Drivers
 */
class NeevoDriverException extends NeevoException {}
