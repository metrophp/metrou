<?php

class Metrou_Login {

	public function accessDenied($event, $args) {
		$response = \_make('response');
		$response->statusCode = 403;
		_clearHandlers('process');
		$user = \_make('user');
		if ( $user->isAnonymous()) {
			$response->statusCode = 401;
			_iCanHandle('process', 'metrou/login.php::mainAction');
		}
		return TRUE;
	}

	public function authSuccess($event, $args) {
		$session = _make('session');
		$request = $args['request'];
		if ($request->cleanString('remember_me')) {
			$session->enableLts();
		}
	}

	public function authFailure($event, $args) {
		$response = $args['response'];
		$response->addUserMessage('login failed', 'msg_warn');
		_clearHandlers('process');
		_iCanHandle('process', 'metrou/login.php::mainAction');
	}

	/**
	 * Show templates/{template_name}/login.html.php
	 */
	public function mainAction() {
		_set('template_layout', 'login');
	}
}
