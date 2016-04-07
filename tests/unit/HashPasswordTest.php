<?php

include_once(dirname(__FILE__).'/../../authenticator.php');
include_once(dirname(__FILE__).'/../../hash/bcrypt.php');
include_once(dirname(__FILE__).'/../../hash/legacy.php');

class Metrou_Unit_Tests_HashPassword extends PHPUnit_Framework_TestCase { 

	public function setUp() {
	}

	public function test_deafault_handler_returns_false_without_adapters() {
		$handler = new Metrou_Authdefault();
		$pwd = $handler->hashPassword('foo');
		$this->assertFalse($pwd);
	}

	public function test_bcrypt_handler_doesnt_return_false() {
		$adapter = new Metrou_Hash_Bcrypt();
		$pwd = $adapter->hashPassword('foo');

		$this->assertTrue($pwd != FALSE);
		$this->assertTrue( strlen($pwd) == 60);
		$this->assertTrue( substr($pwd,0,1) == '$');
	}

	public function test_bcrypt_handler_returns_false_over_72_chars() {
		$adapter = new Metrou_Hash_Bcrypt();
		$pwd2 = $adapter->hashPassword( str_repeat('x', 74) );
		$this->assertFalse($pwd2);
	}

	public function test_handler_verifies_multiple_adapters() {
		$adapter1 = new Metrou_Hash_Bcrypt();
		$adapter2 = new Metrou_Hash_Legacy();
		$adapterList = [$adapter1, $adapter2];

		$handler = new Metrou_Authdefault();
		$handler->setHashAdapters($adapterList);
		$compare = $handler->comparePasswordHash('foo', $adapter2->hashPassword('foo'));

		$compare = $handler->comparePasswordHash('bar', $adapter1->hashPassword('bar'));

		$this->assertTrue($compare);
	}
}
