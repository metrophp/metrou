<?php

include_once(dirname(__FILE__).'/../../user.php');
include_once(dirname(__FILE__).'/../../session.php');
include_once(dirname(__FILE__).'/../../sessionsimple.php');

class Metrou_Intg_Tests_Group extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$this->user = new Metrou_User();

		$db = Metrodb_Connector::getHandle('default');
		$db->execute('TRUNCATE `user_login`');
		$db->execute('TRUNCATE `user_group_rel`');
		$db->execute('TRUNCATE `user_group`');

		$inserter = _makeNew('dataitem', 'user_group');
		$inserter->set('user_group_id', 1);
		$inserter->set('code', 'sysadmin');
		$inserter->save();
	}

	public function test_save_new_user_with_groups() {
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
		$this->user->addToGroup(1,'sysadmin');
		$this->user->hashPassword();
		$pkey = $this->user->save();
		$this->user->saveGroups();
		$this->assertFalse( !$pkey );

		$sameUser = $this->user->loadGroups();
		$this->assertEquals( 1, count($this->user->groups) );
	}
}
