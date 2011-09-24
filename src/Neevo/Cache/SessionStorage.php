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

namespace Neevo\Cache;

use Neevo\ICache;


/**
 * Session cache storage.
 * @author Martin Srank
 */
class SessionStorage implements ICache {


	public function fetch($key){
		return array_key_exists($key, $_SESSION['NeevoCache']) ? $_SESSION['NeevoCache'][$key] : null;
	}


	public function store($key, $value){
		$_SESSION['NeevoCache'][$key] = $value;
	}


	public function flush(){
		return!$_SESSION['NeevoCache'] = array();
	}


}
