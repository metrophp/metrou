<?php
include_once(dirname(__FILE__).'/session.php');

/**
 * The Db session plugin uses php's session_set_save_handler to 
 * hook in to the session lifecycle and stores session information 
 * in the database.
 */
class Metrou_Sessiondb extends Metrou_Session {

	public $data = array();

	public function start() { 
		session_set_save_handler(
					array(&$this, 'open'),
					array(&$this, 'close'),
					array(&$this, 'read'),
					array(&$this, 'write'),
					array(&$this, 'destroy'),
					array(&$this, 'gc'));
		register_shutdown_function('session_write_close');
		//some php.ini's don't use the gc setting, they assume
		//that a cron will clean up /var/lib/php/
		//We will set a gc func here 10% of the time
		if (rand(1,10) > 9)
			register_shutdown_function( array(&$this, 'gc') );

		parent::start();
	}

	public function destroy($id) {
		if (strlen($id) < 1) { return TRUE; }
		$sess = _makeNew('dataitem', 'user_sess', 'user_sess_key');
		$sess->delete($id);
		return TRUE;
	}

	/**
	 * Remove any entries in the user_sess table that have not been
	 * accessed in timeout seconds (24 hours generally) unless
	 * the long term session flag is set.  If this is the case then leave it
	 * indefinitely.
	 */
	public function gc($maxlifetime=0) {
		$sess = _makeNew('dataitem', 'user_sess', 'user_sess_key');
		$sess->andWhere('saved_on', (time()- $this->timeout), '<');
		$sess->andWhere('lts', '1', '!=');
		$sess->delete();
		return TRUE;
	}

	public function read($id) {
		$sess = _makeNew('dataitem', 'user_sess', 'user_sess_key');
		$sess->set('user_sess_key', $id);
		$sess->andWhere('user_sess_key',$id);
		$sess->_rsltByPkey = FALSE;
		$sess->_typeMap['user_sess_key'] = 'string';
		$sessions = $sess->find();
		if (count($sessions) && is_array($sessions)) {
			$sess = $sessions[0];
			if ( strlen($sess->data) ) {
				return (string) $sess->data;
			}
			return '';
		}
		return FALSE;
	}

	public function open($path, $name) {
		return TRUE;
	}

	public function close() {
		return TRUE;
	}

	public function write ($id, $sess_data) {
		$this->commit();
		$sess = _makeNew('dataitem', 'user_sess', 'user_sess_key');
		$sess->andWhere('user_sess_key', $id);
		$sess->_rsltByPkey = FALSE;
		$sess->_typeMap['user_sess_key'] = 'string';
		$sessions = $sess->find();

		if (count($sessions) && is_array($sessions)) {
			$sess = $sessions[0];
		} else {
			$sess = _makeNew('dataitem', 'user_sess', 'user_sess_key');
			$sess->user_sess_key = $id;
		}
		$sess->set('lts', $this->longTermStorage);
		$sess->_typeMap['lts']           = 'int';
		$sess->_typeMap['user_sess_key'] = 'string';
		$sess->_typeMap['data']          = 'text';

		//TODO fix this so it works behind load balancers
		$sess->set('ip_addr', $_SERVER['REMOTE_ADDR']);
		$sess->set('data', $sess_data);
		$sess->set('saved_on', time());
		$sess->_typeMap['saved_on'] = 'int';
		$sess->save();
		return TRUE;
	}

	public function clear($key) {
		unset($_SESSION[$key]);
	}

	public function set($key, $val) {
		$_SESSION[$key] = $val;
	}

	public function get($key) { 
		if (isset($_SESSION[$key])) {
			return @$_SESSION[$key];
		} else {
			return NULL;
		}
	}

	public function append($key, $val) {
		$_SESSION[$key][] = $val;
	}

	public function setArray($a) {
		foreach ($a as $key=>$val) {
			$_SESSION[$key] = $val;
		}
	}

	public function valuesAsArray() {
		return $_SESSION;
	}

	/**
	 * Clear all session variables
	 */
	public function clearAll() {
		parent::clearAll();
		foreach ($_SESSION as $k=>$v) {
			unset($_SESSION[$k]);
		}
	}
}
