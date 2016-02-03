<?php
include_once(dirname(__FILE__).'/session.php');

/**
 * The Db session plugin uses php's session_set_save_handler to 
 * hook in to the session lifecycle and stores session information 
 * in the database.
 */
class Metrou_Sessiondb extends Metrou_Session {

	public $data         = array();
	public $hashOrig     = '';
	public $readeritem   = NULL;
	public $writeritem   = NULL;
	public $isDirty      = FALSE;

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
		if (rand(1,100) > 90)
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
		$this->readeritem = NULL;
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
			$this->readeritem = $sessions[0];
			if ( strlen($this->readeritem->data) ) {
				$this->hashOrig = sha1($this->readeritem->data);
				return (string) $this->readeritem->data;
			}
			return '';
		} else {
			$this->readeritem = $sess;
		}
		return FALSE;
	}

	public function open($path, $name) {
		return TRUE;
	}

	public function close() {
		return TRUE;
	}

	/**
	 * Flush needed variables to the session storage.
	 * For DB based sessions these values might need to go
	 * into the table and not the session itself.
	 */
	public function commit() {
		$this->writeritem = _makeNew('dataitem', 'user_sess', 'user_sess_key');
		$this->writeritem->_isNew = $this->readeritem->_isNew;
		$this->writeritem->set('user_sess_key', $this->readeritem->get('user_sess_key'));
		$this->writeritem->_typeMap['lts']             = 'int';
		$this->writeritem->_typeMap['auth_time']       = 'int';
		$this->writeritem->_typeMap['touch_time']      = 'int';
		$this->writeritem->_typeMap['last_touch_time'] = 'int';
		$this->writeritem->_typeMap['user_sess_key']   = 'string';
		$this->writeritem->_typeMap['data']            = 'text';

		if ($this->readeritem->get('lts') != $this->longTermStorage) {
			$this->writeritem->set('lts', $this->longTermStorage);
		}

		if ($this->readeritem->get('auth_time') != $this->authTime) {
			$this->writeritem->set('auth_time', $this->authTime);
		}

		if ($this->readeritem->get('touch_time') != $this->touchTime) {
			$this->writeritem->set('touch_time', $this->touchTime);
		}

		if ($this->readeritem->get('last_touch_time') != $this->lastTouchTime) {
			$this->writeritem->set('last_touch_time', $this->lastTouchTime);
		}
	}

	/**
	 * We must write everything to $_SESSION to trigger session write handler,
	 * but we may want to save some special session keys into their own 
	 * database columns.
	 * commit() will read out and set the proper valuse for the columns,
	 * but we need to remove this from $sess_data so that we don't update
	 * a blob column every hit
	 *
	 * $sess_data is useless because we cannot re-encode any array to be valid
	 * php session data, we must use session_encode() which only takes void.
	 *
	 * @return String new session blob
	 */
	public function removeMeta() {
		$metakeys = ['_lts', '_touch', '_auth', '_lastTouch'];
		foreach($metakeys as $_m) {
			//unset($s[ $_m ]);
			unset($_SESSION[ $_m ]);
		}

		return session_encode();
	}

	public function write ($id, $sess_data) {
		$this->commit();
		$sess_data = $this->removeMeta();
		$this->writeritem->set('user_sess_key', $id);

		//only update if dirty
		if ($this->isDirty || $this->hashOrig != sha1($sess_data)) {
			$this->writeritem->set('data', $sess_data);
		}

		if ($this->readeritem->get('ip_addr') != $_SERVER['REMOTE_ADDR']) {
			//TODO fix this so it works behind load balancers
			$this->writeritem->set('ip_addr', $_SERVER['REMOTE_ADDR']);
		}

		$this->writeritem->save();
		return TRUE;
	}

	public function clear($key) {
		unset($_SESSION[$key]);
	}

	public function set($key, $val) {
		$this->isDirty = TRUE;
		$_SESSION[$key] = $val;
	}

	public function getMeta($key, $default=NULL) {
		switch($key) {
			case '_lts':
				return (int)$this->readeritem->get('lts');

			case '_auth':
				return (int)$this->readeritem->get('auth_time');

			case '_touch':
				return (int)$this->readeritem->get('touch_time');

			case '_lastTouch':
				return (int)$this->readeritem->get('last_touch_time');

			default:
				return $default;
		}
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
