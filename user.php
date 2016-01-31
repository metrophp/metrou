<?php

class Metrou_User {

	public $username        = "anonymous";
	public $password;
	public $email;
	public $userId          = 0;
	public $loggedIn        = FALSE;
	public $locale          = '';
	public $tzone           = '';
	public $idProvider      = 'self';
	public $idProviderToken = NULL;
	public $validationToken = NULL;
	public $activeOn        = NULL;

	public $enableAgent     = NULL;
	public $agentKey        = NULL;
	 
	//array of group membership groups["public"], groups["admin"], etc.
	public $groups          = array();

	//account object
	public $account         = NULL;

	//flag for lazy loading
	public $_accountLoaded  = FALSE;

	/**
	 * Simple getter
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * Return a name suitable for display on a Web site.
	 *
	 * Try to load the user's account.  Combine first and last names if available.
	 * If no account is avaiable, compare the username and emails.  If they are the 
	 * same, return the first half of the email (username@example.com).
	 * If they are different, return the username by itself.
	 *
	 * @return String name for the user suitable for displaying
	 */
	public function getDisplayName() {
		$this->fetchAccount();

		//check the account object
		if ($this->account->firstname != '' ||
			$this->account->lastname != '') {
				return $this->account->firstname. ' '.$this->account->lastname;
		}
		//check if emails are the same as usernames
		if ($this->username === $this->email && strpos($this->username, '@')) {
			return substr($this->username, 0, strpos($this->username, '@'));
		}
		return $this->username;
	}

	/**
	 * Returns true or false based on if the current user is
	 * logged into the site or not
	 */
	public function isAnonymous() {
		return !$this->loggedIn;
	}

	/**
	 * Return one user given the database key
	 *
	 * @return  object  new lcUser
	 * @static
	 */
	public static function load($key) {
		if ($key < 1) { return NULL; }

		$item = _makeNew('dataitem', 'user_login');
		$item->load($key);
		$user = new Metrou_User();
		$user->populate( $item->valuesAsArray() );
		return $user;
	}

	public function populate($rec) {
		$this->userId      = @$rec['user_login_id'];
		if ($this->userId == NULL && array_key_exists('user_id', $rec)) {
			$this->userId      = @$rec['user_id'];
		}
		$this->username        = @$rec['username'];
		$this->password        = @$rec['password'];
		$this->email           = @$rec['email'];
		$this->locale          = @$rec['locale'];
		$this->tzone           = @$rec['tzone'];
		$this->activeOn        = @$rec['active_on'];
		$this->validationToken = @$rec['validation_token'];
		$this->idProviderToken = @$rec['ip_provider_token'];
		$this->enableAgent     = @$rec['enable_agent'] == '1'? TRUE : FALSE;
		if ($this->enableAgent) {
			$this->agentKey   = @$rec['agent_key'];
		}
		return $this;
	}

	/**
	 * Load group association from the database
	 */
	public function loadGroups() {
		$finder = _makeNew('dataitem', 'user_group_link');
		$finder->andWhere('user_login_id', $this->userId);
		$finder->hasOne('user_group', 'UG', 'user_group_id', 'user_group_id');
		$groups = $finder->find();
		$this->groups = array();
		foreach ($groups as $_group) {
			if ($_group->code != '')
			$this->groups[ $_group->user_group_id ] = $_group->code;
		}
	}

	/**
	 * Return an array of user_group_id integers
	 *
	 * @return 	Array 	list of primary keys of groups this user belongs to
	 */
	public function getGroupIds() {
		if (count($this->groups)) {
			return array_keys($this->groups);
		} else {
			return array(0);
		}
	}

	/**
	 * Add a user to a group
	 *
	 * @param int $gid 		internal database id of the group
	 * @param string $gcode 		special code for the group
	 */
	public function addToGroup($gid, $gcode) {
		$this->groups[(int)$gid] = $gcode;
	}

	/**
	 * Remove a user to a group
	 *
	 * @param int $gid 		internal database id of the group
	 * @param string $gcode 		special code for the group
	 */
	public function removeFromGroup($gid, $gcode) {
		unset($this->groups[$gid]);
	}

	/**
	 * Write groups to the database and the session.
	 *
	 * If this user has a session, update it as well.
	 */
	public function saveGroups() {
		$finder = _makeNew('dataitem', 'user_group_link');
		$finder->andWhere('user_login_id', $this->getUserId());
		$items = $finder->find();
		$oldGids = array();
		if (is_array($items))foreach ($items as $_item) {
			$oldGids[] = $_item->user_group_id;
		}
		$newGids = $this->getGroupIds();
		$delGids = array_diff($oldGids, $newGids);
		$addGids = array_diff($newGids, $oldGids);

		foreach ($addGids as $_g) {
			if ($_g == 0) { continue; }
			$newGroup = _makeNew('dataitem', 'user_group_link');
			//table doesn't have a primary key
			unset($newGroup->user_group_link_id);
			$newGroup->user_group_id = $_g;
			$newGroup->user_login_id = $this->getUserId();
			$newGroup->activeOn  = time();
			$newGroup->save();
		}

		foreach ($delGids as $_g) {
			$oldGroup = _makeNew('dataitem', 'user_group_link');
			$oldGroup->andWhere('user_group_id', $_g);
			$oldGroup->andWhere('user_login_id', $this->getUserId());
			$oldGroup->delete();
		}

		$this->updateSessionGroups();
	}

	/**
	 * If this user is the logged in user of the session, save the groups 
	 * to the session.
	 */
	public function updateSessionGroups() {
		$mySession = _make('session');
		if ($this->getUserId() == $mySession->get('userId')) {
			$mySession->set('groups', serialize( $this->groups ));
		}
	}

	/**
	 * Load the account object if it is not already loaded.
	 *
	 * The account object shall be a simple Metrodb_Dataitem.
	 */
	public function fetchAccount() {
		if ($this->_accountLoaded) {
			return;
		}
		$this->account = _makeNew('dataitem', 'user_account');
		$this->account->andWhere('user_login_id', $this->userId);
		$this->account->load();

		$this->_accountLoaded = TRUE;
	}

	/**
	 * Turn on the API agent feature.
	 *
	 * If $createKey is true make a new key only if none exists
	 */
	public function enableApi($createKey = FALSE) {
		$this->enableAgent = TRUE;

		//peek directly at the db, because we don't keep the 
		// agent key loaded in memory normally
		$d = _makeNew('dataitem', 'user_login');
		$d->load( $this->getUserId());

		if ($d->agent_key == '') {
			if(!$this->regenerateAgentKey()) {
				//failed, turn off agent api
				$this->enableAgent = FALSE;
			}
		}
		$this->save();
		return $this->enableAgent;
	}

	/**
	 * Turn on the API agent feature.
	 *
	 * If $createKey is true make a new key only if none exists
	 */
	public function disableApi() {
		$this->enableAgent = FALSE;
		$this->save();
		return $this->enableAgent === FALSE;
	}

	/**
	 * Create a new, unique agent key string
	 */
	public function regenerateAgentKey($deep=0) {
		if ($deep == 3) {
			$this->agentKey = '';
			return FALSE;
		} 
		$rand = rand(100000000, PHP_INT_MAX);
		$crc = sprintf('%u',crc32($rand));
		$tok =  base_convert( $rand.'a'.$crc, 11,26);

		$d = _makeNew('dataitem', 'user_login');
		$d->andWhere('agent_key', $tok);
		$t = $d->find();
		if (is_array($t) && count($t) > 0) {
			$this->regenerateAgentKey($deep+1);
		} else {
			$this->agentKey = $tok;
		}
		return TRUE;
	}

	/**
	 * Returns true or false if this user is in a group
	 * @return Boolean TRUE if $g is in groups array
	 */
	public function belongsToGroup($g) {
		if (!is_array($this->groups) ) {
			return FALSE;
		}
		return in_array($g, $this->groups);
	}


	public function getUserId() {
		return @$this->userId;
	}

	/**
	 * @static
	 */
	/*
	static function registerUser($u, $idProvider='self') {
		//check to see if this user exists
		$finder = _makeNew('dataitem', 'user_login');
		$finder->andWhere('id_provider', $idProvider);
		if ($u->idProviderToken !== NULL) {
			$finder->andWhere('id_provider_token', $u->idProviderToken);
		}

		$finder->andWhere('email', $u->email);
		if ($u->username == '') {
			$finder->orWhereSub('username', $u->email);
		} else {
			$finder->orWhereSub('username', $u->username);
		}
		$finder->_rsltByPkey = FALSE;
		$results = $finder->find();

		if (count($results)) {
			$foundUser = $results[0];
			if (!$foundUser->_isNew && 
				($foundUser->username == $u->username ||
				$foundUser->email == $u->email ||
				$foundUser->username == $u->email)) {
				//username exists
				return false;
			}
		}

		//save
		$u->idProvider = $idProvider;
		$u->hashPassword();
		$x = $u->save();
		//if there is a duplicate key error, it is a PHP error.

		if( $u->userId > 0 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	 */

	/**
	 * return true if password was hashed
	 * @return Boolean TRUE if password was set and hashed on the object
	 */
	public function hashPassword($authHandler=NULL) {
		if (!isset($this->password) || $this->password == '') {
			return FALSE;
		}
		if ($authHandler == NULL) {
			$authHandler = _make('auth_handler');
		}
		$this->password = $authHandler->hashPassword($this->password);
		return TRUE;
	}

	public function save() {
		$dataitem = _makeNew('dataitem', 'user_login');
		$dataitem->_pkey = 'user_login_id';
		$dataitem->load($this->userId);
		$dataitem->email            = $this->email;
		$dataitem->locale           = $this->locale;
		$dataitem->tzone            = $this->tzone;
		$dataitem->username         = $this->username;
		$dataitem->validation_token = $this->validationToken;
		if (isset($this->password) && $this->password != '') {
			$dataitem->password         = $this->password;
		}
		$dataitem->_nuls[]          = 'validation_token';
		$dataitem->_nuls[]          = 'id_provider_token';
		$dataitem->_nuls[]          = 'active_on';
		$dataitem->_nuls[]          = 'enable_agent';
		$dataitem->_nuls[]          = 'agent_key';

		//user is new, so add registration info
		if (!$this->userId) {

			$finder = _makeNew('dataitem', 'user_login');
			$finder->orWhere('email', $this->email);
			$finder->orWhere('username', $this->username);
			$list = $finder->find();
			if (count($list)) {
				return FALSE;
			}

			$session = _make('session');
			$this->_prepareRegInfo($dataitem, $session);
		}

		//only if there's been a change
		if ($this->agentKey !== NULL) {
			$dataitem->agent_key = $this->agentKey;
		}
		//only if there's been a change
		if ($this->enableAgent !== NULL) {
			$dataitem->enable_agent = $this->enableAgent? 1 : 0;
		}

		$result = $dataitem->save();

		if ($result !== FALSE) {
			$this->userId = $result;
		}
		return $result;
	}

	/**
	 * Save some user session data into the $dataItem
	 *
	 * This method does not set reg_cpm, that is left up to user scripts.
	 * @param Object $dataItem  Metrodb_Dataitem class from user_login table
	 */
	public function _prepareRegInfo($dataItem, $session) {
		$dataItem->_nuls[] = 'reg_cpm';
		$dataItem->_nuls[] = 'reg_id_addr';
		$dataItem->_nuls[] = 'id_provider_token';
		$dataItem->set('reg_date', time());
		$dataItem->set('login_date', time());

		if ($session->get('_sess_referrer') != NULL ) {
			$dataItem->set('reg_referrer',   $session->get('_sess_referrer'));
			$dataItem->set('login_referrer', $session->get('_sess_referrer'));
		}

		$dataItem->set('active_on', $this->activeOn );

		//handle ID Providers
		$dataItem->set('id_provider', $this->idProvider);
		$dataItem->set('id_provider_token', $this->idProviderToken);
	}

	/**
	 * Save login info back to the user table
	 *
	 * This method does not set reg_cpm, that is left up to user scripts.
	 * @param Object $dataItem  Metrou_Dataitem class from user_login table
	 */
	protected function recordLastLogin($session) {
		if (!$this->userId) {
			return;
		}
		$dataItem  = _makeNew('dataitem', 'user_login');
		$dataItem->set('login_date', time());
		if ($session->get('_sess_referrer') != NULL ) {
			$dataItem->set('login_referrer', $session->get('_sess_referrer'));
		}
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$dataItem->set('login_ip_addr', $_SERVER['REMOTE_ADDR']);
		}
		$dataItem->_isNew = FALSE;
		$dataItem->set('user_login_id', $this->userId);
		$dataItem->save();
	}

	/**
	 * Grab the current session and apply values to the current user object.
	 *
	 * This is to avoid a database hit for most commonly accessed user 
	 * properties.
	 * This applies the loggedIn = TRUE property
	 * @return Boolean TRUE if successfully applied session values
	 */
	public function startSession($session) {
		//user is not logged in, skip population
		if ($session->get('user_id') == 0 ) {
			return FALSE;
		}
		$this->populate( $session->valuesAsArray());

		$this->loggedIn = TRUE;
		$this->groups = unserialize( $session->get('groups') );

		if ($this->tzone != '' && function_exists('date_default_timezone_set')) {
			@date_default_timezone_set($this->tzone);
		}
		return TRUE;
	}

	/**
	 * links an already started session with a registered user
	 * sessions can exist w/anonymous users, this function
	 * will link userdata to the open session;
	 * also destroys multiple logins
	 */
	public function bindSession($session) {
		//$session = _make('session');
		$session->setAuthTime();
		$session->set('user_id',         $this->userId);
		$session->set('last_bind_time',  time());
		$session->set('username',        $this->username);
		$session->set('email',           $this->email);
		$session->set('password',        $this->password);
		$session->set('locale',          $this->locale);
		$session->set('tzone',           $this->tzone);
		$session->set('active_on',       $this->activeOn );
		$session->set('enable_agent',    $this->enableAgent);
		if ($this->enableAgent) {
			$session->set('agent_key',   $this->agentKey);
		}
		$session->set('groups',serialize( $this->groups ));
		$this->loggedIn = TRUE;

		if ($this->tzone != '' && function_exists('date_default_timezone_set')) {
			@date_default_timezone_set($this->tzone);
		}
	}

	/**
	 * Erases the link between a logged in user ID and the session, 
	 * but keeps the data for debugging/logging.
	 */
	public function unBindSession($session) {
		$session->clear('user_id');
		$session->clear('last_bind_time');
		$session->clear('username');
		$session->clear('email');
		$session->clear('password');
		$session->clear('groups');
		$session->clear('locale');
		$session->clear('tzone');
		$session->clear('enable_agent');
		if ($this->enableAgent) {
			$session->clear('agent_key');
		}
		$this->loggedIn = FALSE;
	}


	/**
	 * Erases the users current session.
	 * if you simply want to end a session, but keep the
	 * data in the db for records, use $u->unBindSession();
	 */
	public function endSession() {
		$session = _make('session');
		$session->erase();
	}

	public function login($session) {
		$this->loggedIn = TRUE;
		$this->loadGroups();
		$this->bindSession($session);
		$this->recordLastLogin($session);
	}
}
