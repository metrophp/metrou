<?php
/**
 * Metrou_Authenticator
 *
 */
class Metrou_Authenticator {

	public $handler  = NULL;
	public $ctx      = NULL;
	public $subject  = NULL;
	public $configs  = array();

	/**
	 * Authenticate the user, are they who they say they are
	 * based on the cookie submitted.
	 * Basically, attach a session to the user.
	 */
	public function authenticate($request, $response) {
		$user    = _make('user');
		$session = _make('session');
		$session->start();
		$user->startSession($session);
	}

	/**
	 * Alias for process
	 */
	public function login($request, $response) {
		return $this->process($request, $response);
	}

	/**
	 * Initialize a new handler for the given context.
	 * If no context is supplied, a default handler will be created.
	 * The default handler is based on the local mysql installation.
	 */
	public function process($request, $response) {
		$configs       = _get('auth_configs', array());
		$this->handler = _make('auth_handler');

		if ($this->handler == NULL || $this->handler instanceof Metrodi_Proto) {
			$this->handler = new Metrou_Authdefault();
		}
		$this->handler->initContext($configs);

		$user = _make('user');
		$uname = $request->cleanString('username');
		if ($uname == '') {
			$uname = $request->cleanString('email');
		}
		$pass  = $request->cleanString('password');

		$this->subject = Metrou_Subject::createFromUsername($uname, $pass);
		$err = $this->handler->authenticate($this->subject);

		if ($err) {
			$response->addTo('sparkMsg', 'Login Failed');
			$args = array(
					'request'=>$request,
					'response'=>$response,
					'subject'=>$this->subject,
					'user'=>$user
				);
			Metrofw_Kernel::emit('authenticate.failure', $this, $args);
			return;
		}
		$response->addTo('sparkMsg', 'Login Succeeded');
		if ($request->appUrl == 'login') {
			$response->redir = m_appurl();
		}

		@$this->handler->applyAttributes($this->subject, $user);
		$args = array(
				'request'=>$request,
				'subject'=>$this->subject,
				'user'=>$user
			);
		$user->username = $uname;
		$user->password = $this->handler->hashPassword($pass);
		Metrofw_Kernel::emit('authenticate.success', $this, $args );

		$session = _make('session');
		$user->login($session);
		$args = array(
				'user'=>$user
			);
		Metrofw_Kernel::emit('login.after', $this, $args);
	}
}

interface Metrou_Authiface {

	/**
	 * Must return a reference to this
	 *
	 * @return Object Metrou_Authiface
	 */
	public function initContext($ctx);

	/**
	 * Return any positive number other than 0 to indicate an error
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function authenticate($subject);

	/**
	 * Save a connection to this user in the local user database.
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function applyAttributes($subject, $existingUser);

}

class Metrou_Authdefault implements Metrou_Authiface {

	public function initContext($ctx) {
		return $this;
	}


	/**
	 * Return any positive number other than 0 to indicate an error
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function authenticate($subject) {

		if (!isset($subject->credentials['passwordhash'])) {
			$subject->credentials['passwordhash'] = $this->hashPassword($subject->credentials['password']);
		}

		$finder = _makeNew('dataitem', 'user_login');
		$finder->andWhere('username', $subject->credentials['username']);
		$finder->orWhereSub('email', $subject->credentials['username']);
		$finder->andWhere('password', $subject->credentials['passwordhash']);
		$finder->_rsltByPkey = FALSE;
		$results = $finder->findAsArray();

		if (!count($results)) {
			return 501;
		}

		if( count($results) !== 1) {
			//too many results, account is not unique
			return 502;
		}

		$subject->attributes = array_merge($subject->attributes, $results[0]);
		return 0;
	}

	public function hashPassword($p) {
		return md5(sha1($p));
	}

	/**
	 * Save a connection to this user in the local user database.
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function applyAttributes($subject, $user) { 
		$attribs        = $subject->attributes;
		$user->email    = $attribs['email'];
		$user->locale   = $attribs['locale'];
		$user->tzone    = $attribs['tzone'];
		$user->activeOn = $attribs['active_on'];
		$user->userId   = $attribs['user_login_id'];

		$user->enableAgent = $attribs['enable_agent'] == '1'? TRUE : FALSE;
		if ($user->enableAgent) {
			$user->agentKey    = $attribs['agent_key'];
		}
		return 0; 
	}
}

class Metrou_Authldap implements Metrou_Authiface {

	public $dsn        = '';
	public $bindBaseDn = '';
	protected $ldap    = NULL;

	public function initContext($ctx) {
		$this->dsn        = $ctx['dsn'];
		$this->bindBaseDn = $ctx['bindDn'];
		$this->authDn     = $ctx['authDn'];
		return $this;
	}

	public function setLdapConn($l) {
		$this->ldap = $l;
	}

	public function getLdapConn() {
		if ($this->ldap === NULL) {
			$this->ldap = _make('ldapconn', $this->dsn);
		}
		return $this->ldap;
	}

	/**
	 * Return any positive number other than 0 to indicate an error
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function authenticate($subject) {

		if (!isset($subject->credentials['passwordhash'])) {
			$subject->credentials['passwordhash'] = $this->hashPassword($subject->credentials['password']);
		}

		$rdn = sprintf($this->bindBaseDn, $subject->credentials['username']);
		$ldap = $this->getLdapConn();

//		$ldap->setBindUser($rdn, $subject->credentials['password']);
		$result = $ldap->bind();

		$basedn = $this->authDn;
		//query for attributes
		$res = $ldap->search($basedn, '(userid='.$subject->credentials['username'].')', array('entryUUID', 'mail', 'tzone', 'locale', 'dn', 'entryDN'));

		if ($res === FALSE) {
			//search failed
			$ldap->unbind();
			return 501;
		}

		$ldap->nextEntry();
		$attr = $ldap->getAttributes();
		$ldap->unbind();
		foreach ($attr as $_attr => $_valList) {
			if ($_attr == 'mail')
				$subject->attributes['email'] = $_valList[0];

			if ($_attr == 'entryDN')
				$subject->attributes['dn'] = $_valList[0];
		}

//		$subject->attributes = array_merge($subject->attributes, $results[0]);
		return 0;
	}

	public function hashPassword($p) {
		return md5(sha1($p));
	}

	/**
	 * Save a connection to this user in the local user database.
	 *
	 * @return int  number greater than 0 is an error code, 0 is success
	 */
	public function applyAttributes($subject, $existingUser) {
		$existingUser->username = $subject->credentials['username'];
		$existingUser->password = $subject->credentials['passwordhash'];

		$existingUser->idProviderToken = $subject->attributes['dn'];
		//tell the subject that what its new ID is
		$subject->attributes['user_login_id'] = $existingUser->userId;
		return 0;
	}
}

class Metrou_Subject {

	public $credentials = array();
	public $attributes  = array();
	public $domain      = '';
	public $domainId    = 0;

	public static function createFromUserName($uname, $pass) {

		$subj = new Metrou_Subject();
		$subj->credentials['username'] = $uname;
		$subj->credentials['password'] = $pass;
		return $subj;
	}
}

