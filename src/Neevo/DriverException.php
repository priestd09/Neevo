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

namespace Neevo\Drivers;

use Neevo\NeevoException;


/**
 * Neevo driver exception.
 * @author Martin Srank
 */
class DriverException extends NeevoException {

}


/**
 * Exception for features not implemented by the driver.
 * @author Martin Srank
 */
class ImplementationException extends NeevoException {

}

