<?php

include_once(dirname(__FILE__).'/../../session.php');
include_once(dirname(__FILE__).'/../../sessionsimple.php');

class Metrou_Tests_Session extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$this->user    = new Metrou_User();
		$this->session = new Metrou_SessionSimple();
	}

	public function test_start_session_touches_time() {
		$this->session->start();
		$touchTime = time();
		
		$this->assertEquals($touchTime, $this->session->touchTime);
		$this->assertEquals($touchTime, $this->session->lastTouchTime);


		//simulate hit in the future
		$this->session->touch($touchTime+10);
		$this->assertEquals($touchTime+10, $this->session->touchTime);
		$this->assertEquals($touchTime, $this->session->lastTouchTime);
	}

	public function test_start_session_is_new() {
		$this->session->start();
		$touchTime = time();
		
		$this->assertTrue( $this->session->isNew() );

		//simulate hit in the future
		$this->session->touch($touchTime+10);
		$this->assertFalse( $this->session->isNew() );
	}
}
