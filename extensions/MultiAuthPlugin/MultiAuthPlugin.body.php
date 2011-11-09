<?php
/*
 * This file is part of the MediaWiki MultiAuth extension.
 * Copyright 2009, RRZE, and individual contributors
 * as indicated by the @author tags. See the copyright.txt file in the
 * distribution for a full listing of individual contributors.
 *
 * This is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this software.  If not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301
 * USA, or see the FSF site: http://www.fsf.org.
 */

// TODO create a Config class to hold all configuration options
// TODO create a Method class
// TODO try to use UserMailer::send( $to, $sender, $subject, $body, $replyto ); for mail sending
// TODO add SingleLogout support
// FIXME no method (null) and 'local' method have a unique status, that is checked via multiple if-else constructs
// FIXME storing/retrieving of session data is very low level -> write some abstractions for that and use MW's session hook when possible

// Check to make sure we're actually in MediaWiki.
if (!defined('MEDIAWIKI')) die('This file is part of MediaWiki. It is not a valid entry point.');


require_once("$IP/includes/AuthPlugin.php");

/**
 * Main class for the Multi Authentication Plugin for Mediawiki.<br/>
 * <i>This plugin was developed and tested with MW 1.13.4 and MW 1.15.1</i>
 *
 * @author Florian Löffler (RRZE, unrza249)
 * @version 1.3.1
 */

class MultiAuthPlugin extends AuthPlugin {

	/**
	 * @var array
	 * Configuration array
	 */
	var $config = null;

	/**
	 * @var string
	 * Name of the currently active authentication method.
	 */
	var $currentMethodName = null;

	/**
	 * @var AuthPlugin
	 * The original instance of the AuthPlugin class for local
	 * authentication.
	 */
	var $old_wgAuth = null;

	/**
	 * @var array
	 * Array containing only all the currently activated authentication
	 * methods.
	 */
	var $activatedMethods = null;

	/**
	 * @var boolean
	 * Flag indicating whether we are currently inside the session hook
	 * function.
	 * Used to prevent recursion.
	 *
	 */
	var $inUserLoadFromSessionHook = false;


	/* ********************************************
	 *                 METHODS                  *
	 ******************************************** */

	/**
	 * Constructor for the MultiAuthPlugin class
	 *
	 * @param string $configFile
	 * Path to the configuration file. Defaults to 'MultiAuthPlugin.config.php'.
	 */
	function __construct($configFile = null) {
		wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "\n=================================================================================================="); /// seperator
		wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "*** I'm alive!");

		if ($this->config['debug']['logServerVariables']) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "*** SERVER: " . print_r($_SERVER, true));
		}

		// start the whole thing up...
		$this->config = array();
		$this->loadConfig();
		//$this->loadCurrentMethodNameFromSession();
		$this->updateConfiguredMethods();
	}

	/**
	 * Load a $config array from the given file and merge
	 * it in the MultiAuthPlugin's configuration
	 * @param string $configFile
	 * the path to the configuration file to be loaded
	 * @return boolean Successfull execution
	 */
	private function loadConfig($configFile = null) {
		if (is_null($configFile)) {
			$configFile = dirname(__FILE__) . '/MultiAuthPlugin.config.php';
		}
		if (!file_exists($configFile)) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "The specified configuration file '$configFile' does not exist.");
			return false;
		}

		require($configFile);

		if (!isset($config) || !is_array($config)) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "The specified configuration file '$configFile' does not contain a \$config array.");
			return false;
		}

		$this->config = array_merge($this->config, $config);

		return true;
	}

	/**
	 * Tries to load the currentMethodName from the MA_methodName session variable.
	 * When already at it we also perform some checks and recover from unexpected
	 * configuration changes concerning the validity of a loaded method.
	 *
	 * @return String
	 * The loaded method name or null if none could be loaded
	 */
	private function loadCurrentMethodNameFromSession() {
		if (isset($_SESSION['MA_methodName'])) {
			$methodName = $_SESSION['MA_methodName'];
			if ($this->isValidMethod($methodName)) {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Got method name '{$methodName}'" );
				$this->currentMethodName = $methodName;
			}
			else {
				// The method name stored in the session may become invalid when the configuration
				// is changed live. To recover from that, we reset the method name and do a local logout.
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Got invalid method name '{$methodName}'. Trying to recover via method reset and local logout." );
				unset($_SESSION['MA_methodName']);
				$this->currentMethodName = null;
				$this->logout();
			}
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "No method chosen, yet." );
			$this->currentMethodName = null;
			return false;
		}

		return $this->currentMethodName;
	}


	private function updateConfiguredMethods() {
		unset($this->config['methods']);
		$this->loadConfig($this->config['internal']['methodSetupFile']);
	}


	/**
	 * Tries to retrieve the configured auth data (see internal->authData) from
	 * its' methods and make it availabe in the global context for later usage
	 * by the configured methodSetupFile.
	 */
	private function retrieveAuthData() {
		global $authData;
		$authData = array();

		$libName = $this->config['internal']['authLib'];
		$attributes = $this->config['internal']['authData'];

		$skipped = false;
		switch ($libName) {

			/*
			 * SHIBBOLETH
			 */
			case 'shibboleth':
				if ($this->config['debug']['logRawAuthLibAttibuteData']) {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Shibboleth:\n" . print_r($_SERVER, true));
				}
				foreach ($attributes as $attribute) {
					if (isset($_SERVER[$attribute])) {
						$authData[$attribute] = $_SERVER[$attribute];
					}
					else {
						$authData[$attribute] = '';
					}
				}
				break;

				/*
				 * SIMPLESAMLPHP
				 */
			case 'simplesamlphp':
				$ssphpPath = $this->config['paths']['libs']['simplesamlphp'];

				// TODO put this somewhere else
				// switch to simpleSAMLphp session
				if (session_id()) {
					$oldSessionName = session_name();
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Closing existing session '{$oldSessionName}' for simpleSAMLphp.");
					session_write_close();
				}
				//session_name("simpleSAMLphp");
				//session_start();
				//wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "SESSION:\n" . print_r($_SESSION, true) . "\n");


				if (file_exists($ssphpPath . "/www/_include.php")) {
					// load simpleSAMLphp library
					require_once($ssphpPath . "/www/_include.php");

					// Update to support SimpleSAMLphp > 1.8.0
					// Load simpleSAMLphp configuration and session.
					// $config = SimpleSAML_Configuration::getInstance();
					$session = SimpleSAML_Session::getInstance();
					$as = new SimpleSAML_Auth_Simple($session->getAuthority());
					
					//wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "SAML-SESSION:\n" . print_r($session, true) . "\n");
					
					$ssphpAttrs = array();
					if ($as->isAuthenticated()) {
						// retrieve attributes
						$ssphpAttrs = $as->getAttributes();
						if ($this->config['debug']['logRawAuthLibAttibuteData']) {
							wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "SimpleSAMLphp:\n" . print_r($ssphpAttrs, true));
						}
					}
					else {
						wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "No valid session found.");
					}

					foreach ($attributes as $attribute) {
						if (isset($ssphpAttrs["urn:mace:dir:attribute-def:" . $attribute][0])) {
							$authData[$attribute] = $ssphpAttrs["urn:mace:dir:attribute-def:" . $attribute][0];
						} elseif (isset($ssphpAttrs[$attribute][0])) {
							$authData[$attribute] = $ssphpAttrs[$attribute][0];
						}
						else {
							$authData[$attribute] = '';
						}
					}
				}
				else {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Could not load SimpleSAMLphp lib from '{$ssphpPath}'.");
				}


				// TODO put this somewhere else
				// switch back to old session
				session_write_close();
				if (isset($oldSessionName)) {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Restoring previous session '{$oldSessionName}'.");
					session_write_close();
					session_name($oldSessionName);
					session_start();
				}


				break;

				/*
				 * UNKNOWN/INVALID LIBRARY
				 */
			default:
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Skipped unknown authentication library '{$libName}'.");
				$skipped = true;
				// set attributes to '' for the unknown lib
				foreach ($attributes as $attribute) {
					$authData[$attribute] = '';
				}

		}

		if (!$skipped && $this->config['debug']['logRetrievedAttributeData']) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "" . print_r($authData, true));
		}
	}

	/**
	 * Returns the current version string
	 * @return string
	 * The current version string
	 */
	function getVersion() {
		return $this->config['general']['version'];
	}

	/**
	 * Returns the current homepage URL
	 * @return string
	 * The current homepage URL
	 */
	function getURL() {
		return $this->config['general']['url'];
	}

	/**
	 * Returns the name of the currently active authentication method
	 * @return string
	 * The name of the currently active authentication method
	 */
	function getCurrentMethodName() {
		return $this->currentMethodName;
	}

	/**
	 * Returns the configuration array for the current authentication method
	 * @return array
	 * Configuration array for the current authentication method
	 */
	function getCurrentMethod() {
		return $this->config['methods'][$this->currentMethodName];
	}

	/**
	 * Returns an array containing only all the currently activated
	 * authentication methods.
	 * <i>The array is cached internally by the MultiAuthPlugin object so it
	 * does not have to be built on every request</i>
	 * @return array
	 * Array containing only all the currently activated authentication methods
	 */
	function getActivatedMethods() {
		// if not already done...
		if (is_null($this->activatedMethods)) {
			// ...copy all activated methods to the activatedMethods array
			$this->activatedMethods = array();
			$configuredMethods = $this->getConfiguredMethods();
			foreach ($configuredMethods as $methodName => $method) {
				if (in_array($methodName, $this->config['internal']['methods'])) {
					// add method to the activated methods array
					$this->activatedMethods[$methodName] = $method;
				}
			}
		}

		return $this->activatedMethods;
	}

	/**
	 * Returns an array containing the configuration settings for the requested
	 * method name.
	 * @param string $methodName
	 * Name of the method you want to get the configuration for
	 * @return array
	 * The configuration array for the requested method
	 */
	function getMethod($methodName) {
		if (isset($this->config['methods'][$methodName])) {
			return $this->config['methods'][$methodName];
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "The requested method named '$methodName' does not exist.");
			return array();
		}
	}

	/**
	 * If the given method name is configured true is returned,
	 * false otherwise
	 * @param string $methodName
	 * The name of the authentication method
	 * @return boolean
	 * true if the requested method is valid, false otherwise
	 */
	function isValidMethod($methodName) {
		if (in_array($methodName, $this->config['internal']['methods'])) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Returns an array with all configured authentication methods
	 * @return array
	 * Array with all configured authentication methods
	 */
	function getConfiguredMethods() {
		return $this->config['methods'];
	}

	/**
	 * This is just a wrapper function that marks if the userLoadFromSessionHook
	 * function is entered.
	 * @see userLoadFromSessionHook
	 */
	private function enterUserLoadFromSessionHook() {
		if ($this->inUserLoadFromSessionHook === true) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Tried to enter the hook function twice. This is not good.");
		}

		$this->inUserLoadFromSessionHook = true;
	}

	/**
	 * This is just a wrapper function that marks if the userLoadFromSessionHook
	 * function is left.
	 * @see userLoadFromSessionHook
	 */
	private function leaveUserLoadFromSessionHook() {
		if ($this->inUserLoadFromSessionHook === false) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Tried to leave the hook function twice. This is not right.");
		}

		$this->inUserLoadFromSessionHook = false;
	}

	/**
	 * This function is called during mediawiki's authentication process if
	 * the normal login (e.g. via an old session) fails.
	 * In this case the function looks for externally provided authentication
	 * credentials to log in a user.
	 *
	 * @see userLoadFromSessionHook
	 * @param User &$user
	 * Reference to the global user object. This is passed on from the invoking
	 * hook function.
	 * @return boolean
	 * Success status of the login attempt
	 */
	function login(&$user) {
		$methodName = $this->getCurrentMethodName();

		// start the logging in...
		if (!empty($methodName)) {
			// got a valid method -> go on with the login process
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Trying to log in a user with method '{$this->getCurrentMethodName()}'." );

			// Get authentication data retrieved from the IdP using the configured SSO library
			if ($methodName != 'local' && $methodName != null) {
				$this->retrieveAuthData();
			}
			$this->updateConfiguredMethods();
			$method = $this->getCurrentMethod();

			$attrs = $method['attributes'];

			// Hardcoded requirement: We really need the username!!
			$username = isset($attrs['username'])?$attrs['username']:'';
			if ($username == '') {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Missing username.");
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Cannot login.");
				return false;
			}

			// mdobrinic: close the external authmethod attempt; it has been finalized
			unset($_SESSION['MA_methodStatus']);
						
			// mdobrinic: Format the username according to MediaWiki specs:
			$nt = Title::makeTitleSafe( NS_USER, $username );
			if( is_null( $nt ) ) {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Invalid username provided.");
				return false;
			}
			$username = $nt->getText();
			// end-of-preparing-username 

			// Check other requirements for this method
			if (!$this->checkRequirements($method)) {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Unmatched requirements. Cannot login.");
				return false;
			}

			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Attempting user login for '{$username}'.");

			// Check if we need to auto-create this user
			$autoCreated = false;
			if ($this->config['internal']['enableAutoCreateUsers'] && !User::idFromName($username)) {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Could not find user '{$username}' in the local database. Trying to automatically create it.");
				$this->createUser($user, $attrs);
				$autoCreated = true;
			}


			// check if a user with the given attributes exists
			if(User::idFromName($username)) {
				// log the user in
				$user = User::newFromName($username);
				$user->load();
				$user->setupSession();

				if ($user->isLoggedIn()) {
					$user->setCookies();

					if ($autoCreated || $this->config['internal']['enableAutoUpdateUsers']) {
						$this->modifyUserIfNeeded($user, $attrs);
					}

					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Logged in user '$username' via method '{$methodName}'.");
					return true;
				}
				else {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Failed to login user '$username'.");
					return false;
				}
			}

		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Failed to log in a user with chosen method '{$this->getCurrentMethodName()}'." );
			return false;
		}
	}

	/**
	 * Checks access requirements defined via the method's
	 * 'requirements' array.
	 *
	 * @param Array $method
	 * An array holding the method configuration
	 * @return boolean
	 * True if all requirements are matched, false otherwise
	 */
	private function checkRequirements($method) {
		if (isset($method['requirements'])) {
			$reqs = $method['requirements'];
		}
		else {
			// no requirements
			return true;
		}

		// check requirements
		$requirementMismatch = false;
		foreach ($reqs as $attrName => $req) {
			if (!isset($method['attributes'][$attrName]) || $method['attributes'][$attrName] == '') {
				// required attribute is missing entirely
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Missing required attribute '{$attrName}'" );
				$requirementMismatch = true;
			}
			else {
				// required attribute is present -- check if the value is ok, too
				$value = $method['attributes'][$attrName];
				if ($req != '*') {
					if ($req != $value) {
						wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Mismatched attribute value for '{$attrName}': '{$value}' != '{$req}'" );
						$requirementMismatch = true;
					}
				}
			}
		}
		if ($requirementMismatch) {
			return false;
		}

		return true;
	}


	/**
	 * Creates a new user with the given attributes in the database.
	 * Additionally notification mails are sent if properly configured.
	 * @param User $user the user object to process
	 * @param array $attrs the user's attributes
	 * @return boolean true on success, false otherwise
	 */
	private function createUser(&$user, $attrs) {
		// setup mail
		if ($this->config['comm']['onUserCreation']['notifyMail']) {
			// prepare extra headers
			$headers = 	'From: '. $this->config['comm']['onUserCreation']['notifyMailFrom'] . "\r\n" .
    							'Reply-To: ' . $this->config['comm']['onUserCreation']['notifyMailFrom'] . "\r\n" .
    							'X-Mailer: PHP/' . phpversion();

			// prepare search/replace
			global $wgSitename;
			$search = array('{SITENAME}', '{USERNAME}');
			$replace = array($wgSitename, $attrs['username']);
		}


		// setup the user object
		// mdobrinic: pre-format the username (capitalize first character...)
		$nt = Title::makeTitleSafe( NS_USER, $attrs['username'] );
		if( is_null( $nt ) ) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Invalid username provided.");
			return false;
		}
		$username = $nt->getText();
		
		$user->loadDefaults($username);
		$this->initUser($user, true);

		// create database entry
		$user->addToDatabase();

		// see if it worked ...
		if ($user->mId > 0) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Added database entry for user '{$attrs['username']}' with ID " . $user->mId . ".");

			// set all attributes other than the Uid/Username
			$this->modifyUserIfNeeded($user, $attrs);

			if ($this->config['comm']['onUserCreation']['notifyMail']) {

				// send success email
				if (
				mail(
				$this->config['comm']['onUserCreation']['notifyMailTo'],
				str_replace($search, $replace, $this->config['comm']['onUserCreation']['notifyMailSubjectSuccess']),
				str_replace($search, $replace, $this->config['comm']['onUserCreation']['notifyMailMessageSuccess']),
				$headers
				)
				) {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Notification mail accepted for delivery.");
				}
				else {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Notification mail rejected for delivery.");
				}
			}
			return true;
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Error while adding a database entry for the user '{$attrs['username']}'.");

			if ($this->config['comm']['onUserCreation']['notifyMail']) {
				// send error email
				if (
				mail(
				$this->config['comm']['onUserCreation']['notifyMailTo'],
				str_replace($search, $replace, $this->config['comm']['onUserCreation']['notifyMailSubjectError']),
				str_replace($search, $replace, $this->config['comm']['onUserCreation']['notifyMailMessageError']),
				$headers
				)
				) {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Notification mail accepted for delivery.");
				}
				else {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Notification mail rejected.");
				}
			}
			return false;
		}
	}


	/**
	 * Updates the user's details according to what was given from the SSO
	 * library.
	 * Note that this will be called every time after authenticating
	 * to the IdP.
	 *
	 * @param User $user
	 * User object from MW
	 * @param Array $attrs
	 * Attribute array
	 */
	private function modifyUserIfNeeded(&$user, $attrs) {
		$username = $user->getName();
		$dirty = false;

		/*
		 * Email
		 */
		if (isset($attrs['email'])) {
			$new = $attrs['email'];
			$old = $user->getEmail();

			if ($new != $old) {
				$user->setEmail($new);
				$user->confirmEmail();
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Updated email for user '{$username}' from '{$old}' to '{$new}'");
				$dirty = true;
			}
		}

		/*
		 * Fullname
		 */
		if (isset($attrs['fullname'])) {
			$new = $attrs['fullname'];
			$old = $user->getRealName();

			if ($new != $old) {
				$user->setRealName($new);
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Updated realName for user '{$username}' from '{$old}' to '{$new}'");
				$dirty = true;
			}
		}

		if ($dirty) {
			$user->saveSettings();
		}
	}


	/**
	 * Tries to log in a user from an already existing session
	 *
	 * @param User &$user
	 * Reference to the global user object. This is passed on from the invoking hook function.
	 * @return boolean
	 * Success status
	 */
	function loginFromSession(&$user) {
		wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Trying to log in a user from session.");

		// try to load a new user from a saved session cookie
		$user = User::newFromSession();
		$user->load();

		// check if the user could be logged in from a saved session
		if ($user->isLoggedIn()) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Logged in user '{$user->getName()}' from session.");
			return true;
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "No session found.");
			return false;
		}
	}

	/**
	 * Does a local logout
	 *
	 * @return boolean
	 * Success status
	 */
	function logout() {
		global $wgUser;
		$oldName = $wgUser->getName();

		wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Logging out user '" . $wgUser->getName() . "'.");

		// log the user out
		$wgUser->logout();
		$wgUser->setId(0);

		// Make sure we deliver the correct result of the
		// logout status
		if (!$this->isLoggedIn()) {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Successfull local logout for user '" . $oldName . "'.");
			return true;
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Failed local logout for user '" . $wgUser->getName() . "'.");
			return false;
		}
	}


	/**
	 * Returns the login status of the user
	 *
	 * @return boolean
	 * The login status of the user (true = logged in, false = not logged in)
	 */
	function isLoggedIn() {
		global $wgUser;

		if ($wgUser->isLoggedIn()) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Tries to extract the returnto= parameter out of the original login links
	 * generated by MW. This way we can provide the same functionality of
	 * the original login/logout system (pointing back to the url the user came from)
	 * without hacking into the code.
	 *
	 * @param array &$personal_urls
	 * The global array holding the link defintions for the personla url bar
	 * @return string
	 * The value of the returnto= parameter or an empty string if not defined
	 */
	private function extractReturnToParam(&$personal_urls) {
		//check for link definitions parameter
		if (isset($personal_urls['anonlogin']) && is_array($personal_urls['anonlogin'])) {
			$t_href = $personal_urls['anonlogin']['href'];
		}
		else if (isset($personal_urls['login']) && is_array($personal_urls['login'])) {
			$t_href = $personal_urls['login']['href'];
		}
		else if (isset($personal_urls['logout']) && is_array($personal_urls['logout'])) {
			$t_href = $personal_urls['logout']['href'];
		}
		else {
			// no link found
			return '';
		}

		// try to extract the value of the returnto parameter
		$t_pos = strpos($t_href, 'returnto');
		if ( is_int($t_pos) ) {
			$t_pos += strlen('returnto=');
			$returnto = substr($t_href, $t_pos);
			return $returnto;
		}
		else {
			return '';
		}
	}

	/********************************************
	 *                  HOOKS                   *
	 ********************************************/

	/**
	 * If correctly installed this hook provides the means to modify the
	 * global aliases array which manages all available pages.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/LanguageGetSpecialPageAliases
	 * @param $specialPageAliases
	 * @param $langCode
	 * @return boolean
	 * Success status
	 */
	function filterSpecialPageAliasesHook( &$specialPageAliases, $langCode ) {
		global $wgTitle;

		// TODO userCanEdit must only be checked if the user also _wants_ to edit the page
		//if (!is_null($wgTitle) && (!$wgTitle->userCanRead() || !$wgTitle->userCanEdit())) {
		if (!is_null($wgTitle) && !$wgTitle->userCanRead()) {

			if (isset($specialPageAliases['Userlogin']) && isset($specialPageAliases['MultiAuthSpecialLogin'])) {
				$specialPageAliases['Userlogin'] = $specialPageAliases['MultiAuthSpecialLogin'];
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Replaced 'Userlogin' page with 'MultiAuthSpecialLogin' page.");
			}
			else {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "ERROR while trying to replace 'Userlogin' page with 'MultiAuthSpecialLogin' page.");
			}
		}

		return true;
	}

	/*
	 function getLocalURLHook( $title, $url, $query ) {
		return true;
		}
		*/


	/**
	 * If correctly installed this hook adds available login links to the
	 * personal urls bar.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 * @param array &$personal_urls
	 * The global array holding the link defintions for the personla url bar
	 * @param Title &$title
	 * @return boolean
	 * Success status
	 */
	function addLinkHook(&$personal_urls, &$title) {
		// get the returnto page
		if (!isset($_REQUEST['returnto'])) {
			$returnto = $this->extractReturnToParam($personal_urls);
		}
		else {
			$returnto = $_REQUEST['returnto'];
		}

		// kill the standard login/logout links
		unset($personal_urls['anonlogin']);
		unset($personal_urls['login']);
		unset($personal_urls['logout']);

		// Build link to the login/logout special pages of the MultiAuthPlugin
		$loginLink = SpecialPage::getTitleFor('MultiAuthSpecialLogin')->escapeFullURL() . "?returnto=" . $returnto;
		$logoutLink = SpecialPage::getTitleFor('MultiAuthSpecialLogout')->escapeFullURL();

		if (!$this->isLoggedIn()) {
			$personal_urls['MA_login'] = array(
				'text' => wfMsg('special_login_link'),
				'href' => $loginLink,
			);
		}
		else {
			$method = $this->getCurrentMethod();
			$personal_urls['MA_logout'] = array(
				'text' => $method['logout']['text'],
				'href' => $logoutLink,
			);
		}

		return true;
	}


	/**
	 * If correctly installed this hook will be called to authenticate a
	 * given user.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UserLoadFromSession
	 * @param $user
	 * @param $result
	 * @return boolean
	 * Success status
	 */
	function userLoadFromSessionHook($user, &$result) {
		/*
		 * RECURSION PREVENTION
		 * This is no error!
		 * We need this to hack in the login mechanism of Mediawiki in the
		 * following way:
		 *  - first try what would have been done anyway (login via session)
		 *  - if that fails move on to our plugin code
		 */

		// check if this hook was already called
		if ($this->inUserLoadFromSessionHook) {
			return true;
		}
		$this->enterUserLoadFromSessionHook();

		
		// TODO put this somewhere else and invent a config option
		if (session_id() == '') {
			// Normally MW should create the session itself, but if you delete it manually you're gonna crash
			global $wgCookiePrefix;
			$MW_sessionName = $wgCookiePrefix . "_session";
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "RECOVERY: Initiating MW session '{$MW_sessionName}'." );
			session_name($MW_sessionName);
			session_start();
		}
		else {
			// This should be the case most of the time
			$MW_sessionName = session_name();
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Using existing MW session '{$MW_sessionName}'." );
			
		}

		// TODO put this somewhere else and invent a config option
		//wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "COOKIE: " . print_r($_COOKIE,true) );


		// try to retrieve the a previously stored authentication method from MW's session
		$this->loadCurrentMethodNameFromSession();


		// try to log the user in
		// first from session then with one of the configured methods
		$loginSuccessSession = $this->loginFromSession($user);
		if (!$loginSuccessSession) {
			if ($this->getCurrentMethodName() == 'local' || $this->getCurrentMethodName() == null) {
				// local login requested -- nothing to do for MultiAuth
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Nothing more to do for MultiAuth." );
			}
			else {
				// turn login control over to MultiAuth
				$loginSuccessMA = $this->login($user);
				if (!$loginSuccessMA) {
					wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Failed to log in.");
				}
			}

			$this->leaveUserLoadFromSessionHook();
		}
		else {
			if ($this->getCurrentMethodName() === null) {
				wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "User was logged in from session but no method could be loaded from the session. Assuming method 'local' for recovery.");
				$_SESSION['MA_methodName'] = 'local';
			}
		}

		return true;
	}


	/**
	 * If correctly installed this hook will be called when authenticating
	 * a new user (specifically when calling $user->setCookies(); ).
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UserSetCookies
	 * @param $user
	 * @param $session
	 * @param $cookies
	 * @return boolean
	 * Success status
	 */
	function userSetCookiesHook($user, &$session, &$cookies) {
		/*
		 wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "HOOK START");

		 if (is_string($this->currentMethodName) && $this->currentMethodName != '') {
		 wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Storing 'MA_methodName = $this->currentMethodName' in session.");
		 $session['MA_methodName'] = $this->currentMethodName;
		 }
		 else {
		 wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "No 'MA_methodName' found. This can happen if another plugin (e.g. the normal local login page) has been used to authenticate the user. For now we just default to 'MA_methodName = local' in that case.");
		 $this->currentMethodName = 'local';
		 $session['MA_methodName'] = $this->currentMethodName;
		 }
		 */
		return true;
	}


	/**
	 * If correctly installed this hook will be called when a user logout
	 * has been initiated.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UserLogout
	 * @param $user
	 * @return boolean
	 * Success status
	 */
	function userLogoutHook(&$user) {
		if (isset($_SESSION['MA_methodName'])) {
			unset($_SESSION['MA_methodName']);
		}
		else {
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "'MA_methodName' already cleared from session.");
		}
		$this->currentMethodName = '';
		return true;
	}


	/********************************************
	 *                OVERRIDES                 *
	 ********************************************/


	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @see includes/AuthPlugin.php
	 * @param $username
	 * @param $password
	 * @return boolean
	 */
	function authenticate( $username, $password) {
		return false;
	}


	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @see includes/AuthPlugin.php
	 * @return boolean
	 */
	function strict() {
		if (is_bool($this->config['internal']['enableLocalAuth'])) {
			return !$this->config['internal']['enableLocalAuth'];
		}

		if (is_bool($this->config['internal']['disableLocalAuth'])) {
			return $this->config['internal']['disableLocalAuth'];
		}

		// if the configuration did not use the bool notation strictUserAuth
		// is called next and performs a check against the specified usernames,
		// therefore we are going to allow this.
		return false;
	}


	/**
	 * Check if a user should authenticate locally if the global authentication fails.
	 * If either this or strict() returns true, local authentication is not used.
	 *
	 * @see includes/AuthPlugin.php
	 * @param $username
	 * @return boolean
	 */
	function strictUserAuth( $username ) {
		if (is_array($this->config['internal']['enableLocalAuth'])) {
			return !in_array($username, $this->config['internal']['enableLocalAuth']);
		}

		if (is_array($this->config['internal']['disableLocalAuth'])) {
			return in_array($username, $this->config['internal']['disableLocalAuth']);
		}

		// if we did not get an array in the configuration we default to not allow
		// local authentication.
		return true;
	}


	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @see includes/AuthPlugin.php
	 * @return bool
	 */
	function autoCreate() {
		return $this->config['internal']['enableAutoCreateUsers'];
	}


	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object.
	 * @param $autocreate bool True if user is being autocreated on login
	 */
	function initUser( &$user, $autocreate = false ) {
		$user->mPassword = "nologin";
		/*
		 * Due to MW limitations this defaults to the current time
		 * and can't be null or 0. This way a newly created user would
		 * have to wait 24 hours until he could set his local password.
		 * To circumvent this the time stamp is beeing set to 1!
		 */
		$user->mNewpassTime = 1;
		$user->setOption('rememberpassword', 0);

		return true;
	}
}



?>
