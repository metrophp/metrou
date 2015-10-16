<?php

/**
 * signals fired:
 * 'login.register_before'
 * 'login.register_error'
 * 'login.register_after'
 * 'login.register_email'
 * 'login.after'
 */
class Metrou_Register {

	public $_allowRegister = TRUE;
	public $_validateEmail = TRUE;

	public $regUser      = NULL;
	public $regUsername  = NULL;
	public $regEmail     = NULL;
	public $regPw        = NULL;
	public $regPw2       = NULL;
	public $regPwHash    = NULL;
	public $registerAfterUrl = '';

	public $emailService;


	public function authorize($request, $kernel) {
		_set('template_layout', 'register');
		//allow access by clearing the access.denied slots
		$kernel->clearHandlers('access.denied');
		$request->unauthorized = FALSE;
	}

	/**
	 * Checks a global setting for allowing self registration.
	 *
	 * [config]
	 * _set('register.allow_selfr_egister', [ true | false ]);
	 * _set('register.send_validation_email', [ true | false ]);
	 */
	public function resources($container) {
			$this->_allowRegister = (bool)
				_get('register.allow_self_register', TRUE);

			$this->_validateEmail = (bool)
				_get('register.send_validation_email', FALSE);
	}

	public function mainAction($request, $response) {
		$u = $request->getUser();

		//are we awaiting email activation?
		if (! $u->isAnonymous() && !$u->activeOn ) {
			$response->redir = m_appurl('register/activate');
			return;
		}
	}

	public function activateAction($request, $response) {
	}

	/**
	 * save the registration
	 */
	public function doRegisterAction($request, $response, $kernel) {
		$u = $request->getUser();
		if (! $u->isAnonymous() && $u->activeOn ) {
			$response->addUserMessage('You cannot register when you are already logged in.');
			$response->redir = m_appurl('register');
			return;
		}
		//are we awaiting email activation?
		if (! $u->isAnonymous() && !$u->activeOn ) {
			$response->redir = m_appurl('register/activate');
			return;
		}


		$em  = $request->cleanString('email');
		$pw  = $request->cleanString('pw');
		$pw2 = $request->cleanString('pw2');

		//possible username different from email
		if (!$un = $request->cleanString('username')) {
			$un = $em;
		}

		$u->username = $un;
		$u->email    = $em;

		$authHandler = _make('auth_handler');
		if ($authHandler == NULL || $authHandler instanceof Metrodi_Proto) {
			$authHandler = new Metrou_Authdefault();
		}

		$u->password = $authHandler->hashPassword($pw);

		$this->registerUser      = $u;

		//if we're not going to send a validation email, let's "activate" the user now
		if (!$this->_validateEmail) {
			$u->activeOn = time();
		} else {
			$u->validationToken = md5(rand(1000000, 9999999));
		}

		$saveSuccess = FALSE;
		//check basic registration requirements
		try {
			$this->guardAgainstShortPassword($pw);

			$this->guardAgainstMistypedPassword($pw, $pw2);

			//signalResult should be true to continue with registration
			$signalResult = $kernel->signal('login.register_before', $this);

			if ($signalResult === FALSE) {
				throw new Exception('Unknown error with registration.', 506);
			}

			$saveSuccess = $u->save();

		} catch (Exception $e) {
			$response->addUserMessage($e->getMessage());
		}

		if (!$saveSuccess) {
			$response->redir = m_appurl('register');
			$response->addUserMessage('That e-mail or username is already taken.');
			$kernel->signal('login.register_error', $this);
			return;
		}

		$kernel->iCanHandle('login.register_after', array($this, 'updateRegInfo'));

		$signalResult = $kernel->signal('login.register_after', $this);

		$u->login($request->getSession());
		$response->redir = m_appurl('');
		$signalResult = $kernel->signal('login.after', $this);


		if ($this->_validateEmail) {
			if (!$kernel->hasHandlers('login.register_email')) {
				$kernel->iCanHandle('login.register_email', array($this, 'sendValidationEmail'));
			}

			$validationResult = $kernel->signal('login.register_email', $this);
		}
	}

/*
	public function verifyEvent($request, $response, $kernel) {
		$tk = $req->cleanString('tk');
		$u = $req->getUser();
		if ($u->active_on > 0 && $tk == NULL) {
			$u->addMessage("Your account is already active." );
			$response->redir = m_appurl('account');
			return TRUE;
		}
		$loader = _make('dataitem', 'user_login');
		$loader->andWhere('id_provider', 'self');
		$loader->andWhere('val_tkn', $tk);
		$loader->_rsltByPkey = false;
		$userList = $loader->find();
		$user = $userList[0];

		if (!is_object($user)) {
			$response->addUserMessage("There is no account with this validation token.", 'msg_warn');
			return TRUE;
		}
		//clean up
		$user->set('active_on', time());
		$user->set('val_tkn', NULL);
		$user->_nuls[] = 'val_tkn';
		$user->save();


		$u->password = $user->password;
		$u->username = $user->username;
		$u->userId   = $user->cgn_user_id;
		$u->email    = $user->email;

		$u->loadGroups();
		$u->bindSession();

		// signal
		$this->regUser      = $u;
		$this->regUsername  = $u->username;
		$this->regEmail     = $u->email;
		$this->regPwHash    = $u->password;

		$this->emit('login_register_save_after');

		unset($this->regUser);
		unset($this->regUsername);
		unset($this->regEmail);
		//end signal

		$reponse->redir = m_appurl('account');
		$reponse->addUserMessage("Congratulations, your account has been verified.");
	}
	*/

	public function sendValidationEmail($signal, $args) {
		$token = $signal->source->registerUser->validationToken;
		$email = $signal->source->registerUser->email;
		$un    = $signal->source->registerUser->username;
		$site  = _get('site_title');
		$request  = $args['request'];
		$response = $args['response'];
		$from  = _get('email.default_from', 'no-reply@'.trim($request->baseUri, '/'));

		$sendSuccess = $this->emailService->send(array(
			'recipients'=> array($email),
			'subject'   => 'Registration verification for '.$site,
			'from'      => $from,
			'body'      => 'Your '.$site.' account has been created. You must verify your e-mail address before your account can be activated for use.

Click the following link to activate your account
'.m_appurl('login', 'register', 'verify', array('tk'=>$token)).'

Note: If the link above does not work, copy and paste the link into your browser.
',
		));

		if ($sendSuccess) {
			$response->addUserMessage("Sorry, we had problems sending your validation e-mail.  We're working right now to fix this issue.", 'msg_warn');
		}
		$response->redir = m_appurl('register/activate');
	}

	public function guardAgainstShortPassword($pw) {
		if (strlen($pw) < 3) {
			throw new Exception('Password is not long enough.', 506);
		}
	}

	public function guardAgainstMistypedPassword($pw, $pw2) {
		if ($pw !== $pw2) {
			throw new Exception('Passwords do not match.', 506);
		}
	}

	public function updateRegInfo($signal, $args) {
		$user = $signal->source->registerUser;
		$request = $args['request'];

		$di = _makeNew('dataitem', 'user_login');
		$di->_isNew = FALSE;
		$di->set('reg_ip_addr',    $request->remoteAddr);
		$di->set('login_ip_addr',  $request->remoteAddr);
		$di->set('user_login_id',  $user->userId);
		$di->save();
	}
}
