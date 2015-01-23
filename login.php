<?php

class Metrou_Login {

	public function accessDenied($event, $args) {
		if ($args['request']->appName == 'login') {
			return;
		}
		_clearHandlers('process');
		_iCanHandle('process', 'metrou/login.php::mainAction');
	}

	public function authSuccess($event, $args) {
		$session = _make('session');
		$request = $args['request'];
		if ($request->cleanString('remember_me')) {
			$session->enableLts();
		}
	}

	public function authFailure($event, $args) {

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
