<?php

include_once(dirname(__FILE__).'/../../user.php');
include_once(dirname(__FILE__).'/../../session.php');
include_once(dirname(__FILE__).'/../../sessionsimple.php');

class Metrou_Tests_User extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$this->user = new Metrou_User();
	}

	public function test_new_user_is_anonymous() {
		$this->user->isAnonymous();

		$this->assertTrue(
			$this->user->isAnonymous()
		);

		$this->assertEquals('anonymous', $this->user->getUsername());

		//should NOT read array key 'loggedIn'
		$this->user->populate(
			array(
				'username'  => 'testuser',
				'password'  => 'testpassword',
				'email'     => 'test@localhost',
				'loggedIn'  => TRUE,
				'logged_in' => TRUE
			)
		);

		$this->assertTrue(
			$this->user->isAnonymous()
		);
	}

	public function test_start_session_populates_user() {
		$session = new Metrou_Sessionsimple();
		$session->set('user_id',      1);
		$session->set('username',     'testuser');
		$session->set('email',        'test@localhost');
		$session->set('locale',       'en_US');
		$session->set('tzone',        'America/NewYork');
		$session->set('active_on',    1234567890);
		$session->set('enable_agent', FALSE);

		$this->user->startSession( $session );
		$this->assertTrue(  $this->user->loggedIn  );
		$this->assertFalse( $this->user->isAnonymous() );
	}

	public function test_start_anonymous_session_keeps_user_logged_out() {
		$session = new Metrou_Sessionsimple();
		$session->set('user_id',      0);

		$this->user->startSession( $session );
		$this->assertFalse( $this->user->loggedIn );
		$this->assertTrue(  $this->user->isAnonymous() );
	}

	public function test_bind_session_logs_in() {
		$session = new Metrou_Sessionsimple();

		$this->user->userId   = 1;
		$this->user->username = 'testuser';
		$this->user->bindSession( $session );

		$this->assertTrue(  $this->user->loggedIn  );
		$this->assertFalse( $this->user->isAnonymous() );
	}
}
