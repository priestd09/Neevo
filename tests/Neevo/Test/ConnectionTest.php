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

namespace Neevo\Test;

use ArrayObject;
use DummyObserver;
use DummyPDO;
use Neevo\Cache\SessionStorage;
use Neevo\Connection;
use Neevo\DriverException;


class ConnectionTest extends \PHPUnit_Framework_TestCase {


	/** @var Connection */
	protected $instance;


	protected function setUp(){
		$config = array(
			'testConfig' => true,
			'driver' => 'Dummy'
		);
		$this->instance = new Connection($config);
	}


	protected function tearDown(){
		unset($this->instance);
	}


	public function testConfigFormatString(){
		$connection = new Connection('driver=Dummy');

		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $connection->getDriver());
	}


	public function testConfigFormatTraversable(){
		$config = new ArrayObject(array('driver' => 'Dummy'));
		$connection = new Connection($config);

		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $connection->getDriver());
	}


	public function testConfigFormatPDOInstance(){
		$pdo = new DummyPDO;
		$connection = new Connection($pdo);

		$this->assertEquals('pdo', $connection['driver']);
		$this->assertSame($pdo, $connection['pdo']);
	}


	public function testConfigFormatElse(){
		$this->setExpectedException('InvalidArgumentException', 'must be an array, string or Traversable.');
		new Connection(false);
	}


	public function testAutoSetDriver(){
		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $this->instance->getDriver());
	}


	public function testSetDriverNoFile(){
		$this->setExpectedException('Neevo\\DriverException', 'does not exist.');
		new Connection(array(
			'driver' => 'Foo'
		));
	}


	public function testSetDriverNoDriver(){
		$this->setExpectedException('Neevo\\DriverException', 'is not a valid Neevo driver class.');
		new Connection(array(
			'driver' => 'Wrong'
		));
	}


	public function testSetDriverUnreadableFile(){
		// Prepare unreadable file
		file_put_contents($file = __DIR__ . '/../../../src/Neevo/Drivers/foo.php', $content = 'foo driver');
		$this->assertEquals($content, file_get_contents($file));
		umask(0);
		chmod($file, 0333);

		try{
			new Connection(array(
				'driver' => 'Foo'
			));
		} catch(DriverException $e){
			$this->assertRegExp('~driver file .* is not readable.~i', $e->getMessage());

			// Remove file
			chmod($file, 0666);
			unlink($file);
			$this->assertFileNotExists($file);
			return;
		}
		$this->fail('An expected exception has not been raised.');
	}


	public function testAutoSetParser(){
		$this->assertEquals($this->instance->getParser(), 'Neevo\\Parser');
	}


	public function testSetCustomParser(){
		$connection = new Connection(array(
				'driver' => 'DummyParser'
			));

		$this->assertEquals($connection->getParser(), 'Neevo\\Drivers\\DummyParserDriver');
	}


	public function testSetCache(){
		$cache = new SessionStorage;
		$this->instance->setCache($cache);

		$this->assertEquals(spl_object_hash($this->instance->getCache()), spl_object_hash($cache));
	}


	public function testAutoSetCache(){
		$this->assertInstanceOf('Neevo\\Cache\\MemoryStorage', $this->instance->getCache());
	}


	public function testSetConfig(){
		$this->assertTrue($this->instance->getConfig('testConfig'));
		$this->assertInternalType('array', $this->instance->getConfig());
	}


	public function testAttachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->notifyObservers(1);

		$this->assertTrue($observer->isNotified());
	}


	public function testDetachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->detachObserver($observer);
		$this->instance->notifyObservers(1);

		$this->assertFalse($observer->isNotified());
	}


	public function testAlias(){
		$config = array(
			'alias' => 'value',
		);
		Connection::alias($config, 'key', 'alias');

		$this->assertEquals('value', $config['key']);
	}


	public function testGetPrefix(){
		$this->assertEquals('', $this->instance->getPrefix());
	}


	public function testArrayAccess(){
		$this->assertEquals('Dummy', $this->instance['driver'], 'offsetGet()');
		$this->assertTrue(isset($this->instance['driver']), 'offsetExists()');

		$this->instance['driver'] = 'foo';
		unset($this->instance['driver']);
		$this->assertEquals('Dummy', $this->instance['driver'], 'offsetSet(), offsetUnset()');

	}


	public function testCloseConnection(){
		$this->instance->attachObserver($o = new DummyObserver, DummyObserver::DISCONNECT);
		$this->instance->__destruct();

		$this->assertTrue($this->instance->getDriver()->isClosed());
		$this->assertTrue($o->isNotified());
	}


}
