<?php
/**
 * Metrou_Authorizer
 *
 */
class Metrou_Authorizer {

	public $whiteList = [];
	public $blackList = [];

	public function __construct($whiteList=array(), $blackList=array()) {
		$this->whiteList = $whiteList;
		$this->blackList = $blackList;
	}

	public function whitelistApp($app) {
		$this->whiteList[] = $app;
	}

	public function whitelistUrl($url) {
		$this->whiteList[] = $url;
	}

	public function blacklistApp($app) {
		$this->blackList[] = $app;
	}

	public function blacklistUrl($url) {
		$this->blackList[] = $url;
	}

	public function authorize($request, $response, $user, $kernel) {
		return $this->requireLogin($request, $response, $user, $kernel);
	}

	/**
	 * Fire access.denied event if user is anonymous
	 */
	public function requireLogin($request, $response, $user, $kernel) {
		if ( in_array($request->appName , $this->whiteList) ) {
			$request->unauthorized = FALSE;
			return;
		}
		$args = array(
			'request'  => $request,
			'response' => $response,
			'user'     => $user
		);

		//if any white list is the first part of the requestedUrl
		foreach ($this->whiteList as $_w) {
			if (strpos($request->requestedUrl, $_w) === 0) {
				$request->unauthorized = FALSE;
				return;
			}
		}

		if ( in_array($request->appName , $this->blackList) ) {
			$request->unauthorized = TRUE;
			$kernel->signal('access.denied', $user, $args);
			return;
		}
		//if any black list is the first part of the requestedUrl
		foreach ($this->blackList as $_b) {
			if (strpos($request->requestedUrl, $_b) === 0) {
				$request->unauthorized = TRUE;
				$kernel->signal('access.denied', $user, $args);
				return;
			}
		}

		if ($user->isAnonymous()) {
			$request->unauthorized = TRUE;
			$kernel->signal('access.denied', $user, $args);
		}
	}
}
