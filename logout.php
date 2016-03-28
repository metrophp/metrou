<?php
/**
 *
 */
class Metrou_Logout {

	/**
	 * Initialize a new handler for the given context.
	 * If no context is supplied, a default handler will be created.
	 * The default handler is based on the local mysql installation.
	 */
	public function authenticate($request, $response) {

		$user    = _make('user');
		$session = _make('session');
		$session->start();
		$user->startSession($session);
		//unbind is only for testing
		//$user->unBindSession($session);
		$session->erase();
		$response->redir = m_appurl();
		return;
	}
}
