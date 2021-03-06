<?php defined('BLUDIT') or die('Bludit CMS.');

class Login {

	private $dbUsers;

	function __construct($dbUsers)
	{
		$this->dbUsers = $dbUsers;
	}

	// Returns the username of the user logged
	public function username()
	{
		return Session::get('username');
	}

	// Returns the role of the user logged
	public function role()
	{
		return Session::get('role');
	}

	// Returns TRUE if the user is logged, FALSE otherwise
	public function isLogged()
	{
		if (Session::get('fingerPrint')===$this->fingerPrint()) {
			$username = Session::get('username');
			if (!empty($username)) {
				return true;
			} else {
				Log::set(__METHOD__.LOG_SEP.'Session username empty, destroying the session.');
				Session::destroy();
				return false;
			}
		}

		Log::set(__METHOD__.LOG_SEP.'FingerPrint are differents. Current fingerPrint: '.Session::get('fingerPrint').' !== Current fingerPrint: '.$this->fingerPrint());
		return false;
	}

	// Set the session for the user logged
	public function setLogin($username, $role)
	{
		Session::set('username',	$username);
		Session::set('role', 		$role);
		Session::set('fingerPrint',	$this->fingerPrint());
		Session::set('sessionTime',	time());

		Log::set(__METHOD__.LOG_SEP.'User logged, fingerprint: '.$this->fingerPrint());
	}

	public function setRememberMe($username)
	{
		$username = Sanitize::html($username);

		// Set the token on the users database
		$token = $this->dbUsers->generateRememberToken();
		$this->dbUsers->setRememberToken($username, $token);

		// Set the token on the cookies
		Cookie::set(REMEMBER_COOKIE_USERNAME, $username, REMEMBER_COOKIE_EXPIRE_IN_DAYS);
		Cookie::set(REMEMBER_COOKIE_TOKEN, $token, REMEMBER_COOKIE_EXPIRE_IN_DAYS);

		Log::set(__METHOD__.LOG_SEP.'Cookies seted for Remember Me.');
	}

	public function invalidateRememberMe()
	{
		// Invalidate all tokens on the user databases
		$this->dbUsers->invalidateAllRememberTokens();

		// Destroy the cookies
		Cookie::set(REMEMBER_COOKIE_USERNAME, '', -1);
		Cookie::set(REMEMBER_COOKIE_TOKEN, '', -1);
		unset($_COOKIE[REMEMBER_COOKIE_USERNAME]);
		unset($_COOKIE[REMEMBER_COOKIE_TOKEN]);
	}

	// Check if the username and the password are valid
	// Returns TRUE if valid and set the session
	// Returns FALSE for invalid username or password
	public function verifyUser($username, $password)
	{
		$username = Sanitize::html($username);
		$password = Sanitize::html($password);

		$username = trim($username);
		$password = trim($password);

		if (empty($username) || empty($password)) {
			Log::set(__METHOD__.LOG_SEP.'Username or password empty. Username: '.$username);
			return false;
		}

		if (Text::length($password)<PASSWORD_LENGTH) {
			Log::set(__METHOD__.LOG_SEP.'Password lenght less than required.');
			return false;
		}

		$user = $this->dbUsers->getDB($username);
		if ($user==false) {
			Log::set(__METHOD__.LOG_SEP.'Username does not exist: '.$username);
			return false;
		}

		$passwordHash = $this->dbUsers->generatePasswordHash($password, $user['salt']);
		if ($passwordHash===$user['password']) {
			$this->setLogin($username, $user['role']);
			Log::set(__METHOD__.LOG_SEP.'User logged succeeded by username and password - Username: '.$username);
			return true;
		}

		Log::set(__METHOD__.LOG_SEP.'Password incorrect.');
		return false;
	}

	// Check if the user has the cookies and the correct token
	public function verifyUserByRemember()
	{
		if (!Cookie::isset(REMEMBER_COOKIE_USERNAME) || !Cookie::isset(REMEMBER_COOKIE_TOKEN)) {
			return false;
		}

		$username 	= Cookie::get(REMEMBER_COOKIE_USERNAME);
		$token 		= Cookie::get(REMEMBER_COOKIE_TOKEN);

		$username 	= Sanitize::html($username);
		$token 		= Sanitize::html($token);

		$username 	= trim($username);
		$token 		= trim($token);

		if (empty($username) || empty($token)) {
			$this->invalidateRememberMe();
			Log::set(__METHOD__.LOG_SEP.'Username or Token empty. Username: '.$username.' - Token: '.$token);
			return false;
		}

		if ($username !== $this->dbUsers->getByRememberToken($token)) {
			$this->invalidateRememberMe();
			Log::set(__METHOD__.LOG_SEP.'The user has different token or the token doesn\'t exist.');
			return false;
		}

		// Validate user and login
		$user = $this->dbUsers->getDb($username);
		$this->setLogin($username, $user['role']);
		Log::set(__METHOD__.LOG_SEP.'User authenticated via Remember Me.');
		return true;
	}

	public function fingerPrint()
	{
		$agent = getenv('HTTP_USER_AGENT');
		if (empty($agent)) {
			$agent = 'Bludit/2.0 (Mr Nibbler Protocol)';
		}
		return sha1($agent);
	}

	public function logout()
	{
		$this->invalidateRememberMe();
		Session::destroy();
		return true;
	}
}