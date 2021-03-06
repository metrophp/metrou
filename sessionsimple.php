<?php
include_once(dirname(__FILE__).'/session.php');

/**
 * The Simple session object handles "normal" sessions with the $_SESSION 
 * super global and stoes them however your PHP installation would.
 *
 * This session plugin provides consistency in design when you are not using 
 * the DB session.
 */
class Metrou_Sessionsimple extends Metrou_Session {


	public function start() { 
		//TODO:: fix metrodi dependency
/*
		if (function_exists('_get')) {
			if (_get('session_path', NULL)) {
				session_save_path(_get('session_path'));
			}
		}
*/
		parent::start();
	}

	public function close() { 
		session_write_close();
	}

	public function clear($key) {
		unset($_SESSION[$key]);
	}

	public function set($key, $val) {
		$_SESSION[$key] = $val;
	}

	public function getMeta($key, $default=NULL) {
		$x = $this->get($key);
		if ($x === NULL) {
			return $default;
		}
		return $x;
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
