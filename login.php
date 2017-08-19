<?php

class Metrou_Login {

	public function accessDenied($event, $args) {
		$response = \_make('response');
		$response->statusCode = 403;
		_clearHandlers('process');
		$user = \_make('user');
		if ( $user->isAnonymous()) {
			$response->statusCode = 401;
			_connect('process', 'metrou/login.php::mainAction');
		}
		//pass the redirect_url if any, otherwise set the current URL as rediret
		$request = \_make('request');
		if ($request->cleanString('redir_url')) {
			$response->redir_url = $request->cleanString('redir_url');
		} else {
			$response->redir_url = $request->requestedUrl;
			if (array_key_exists('QUERY_STRING', $_SERVER) && $_SERVER['QUERY_STRING'] != '') {
				$response->redir_url .= '?'.$_SERVER['QUERY_STRING'];
			}
		}
		return TRUE;
	}

	public function authSuccess($event, $args) {
		$session  = _make('session');
		$request  = $args['request'];
		$response = \_make('response');

		$session->setAuthTime();
		if ($request->cleanString('remember_me')) {
			$session->enableLts();
		}

		$response->addUserMessage('Login succeeded');
		if ($request->appUrl == 'login') {
			$response->redir = m_appurl();
		}
		//We must be mindful of redirecting offsite.
		//if there is a list of trusted offsite redirects
		//then connect to authenticate.success and
		//overwrite $response->redir
		if (strlen($request->cleanString('redir_url')) > 5) {
			$redir = $request->cleanString('redir_url');
			$redir = ltrim($redir, '/');
			$response->redir = m_appurl() . $redir;
		}
	}

	public function authFailure($event, $args) {
		$response = $args['response'];
		$response->addUserMessage('login failed', 'msg_warn');
		_clearHandlers('process');
		_iCanHandle('process', 'metrou/login.php::mainAction');
		//pass the redirect_url if any
		$request = \_make('request');
		if ($request->cleanString('redir_url')) {
			$response->redir_url = $request->cleanString('redir_url');
		}
	}

	/**
	 * Show templates/{template_name}/login.html.php
	 */
	public function mainAction() {
		_set('template_layout',    'login');
		_set('template.main.file', '/metrou/login_main');
	}
}
