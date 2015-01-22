<?php
/**
 * Metrou_Authorizer
 *
 */
class Metrou_Authorizer {

	/**
	 * Fire access.denied event if user is anonymous
	 */
	public function requireLogin($request, $response, $user) {
		if ($user->isAnonymous()) {
			$request->unauthorized = TRUE;
			Metrofw_Kernel::emit('access.denied', $user);
		}
	}
}
