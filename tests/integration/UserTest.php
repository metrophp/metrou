<?php

include_once(dirname(__FILE__).'/../../user.php');
include_once(dirname(__FILE__).'/../../session.php');
include_once(dirname(__FILE__).'/../../sessionsimple.php');

class Metrou_Intg_Tests_User extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$this->user = new Metrou_User();

		$db = Metrodb_Connector::getHandle('default');
		$db->execute('DELETE FROM \'user_login\'');
	}

	public function test_save_new_user() {
		$this->user->isAnonymous();

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
		$this->user->hashPassword();
		$pkey = $this->user->save();
		$this->assertFalse( !$pkey );
		$this->assertTrue( is_numeric($pkey) );
		$this->assertTrue( is_numeric($this->user->userId) );
		$this->assertTrue( $this->user->userId > 0 );
	}


	public function test_save_duplicate_new_user_fails() {

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
		$this->user->hashPassword();
		$pkey = $this->user->save();
		$this->assertFalse( !$pkey );
		$this->assertTrue( $this->user->userId > 0 );

		$duplicateUser = new Metrou_User();
		//should NOT read array key 'loggedIn'
		$duplicateUser->populate(
			array(
				'username'  => 'testuser',
				'password'  => 'testpassword',
				'email'     => 'test@localhost',
				'loggedIn'  => TRUE,
				'logged_in' => TRUE
			)
		);
		$duplicateUser->hashPassword();
		$pkey = $duplicateUser->save();
		$this->assertFalse( $pkey );
	}

	public function test_save_user_twice_doesnt_double_register() {

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
		$this->user->hashPassword();
		$pkey = $this->user->save();
		$this->assertFalse( !$pkey );
		$this->assertTrue( $this->user->userId > 0 );

		$pkey = $this->user->save();
		$this->assertFalse( !$pkey );
		$this->assertTrue( $pkey > 0 );
	}


	public function test_save_new_is_not_active() {
		$this->user->isAnonymous();

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
		$this->user->hashPassword();
		$pkey = $this->user->save();
		$this->assertNull( $this->user->activeOn );
	}
}
