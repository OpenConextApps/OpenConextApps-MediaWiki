<?php
/**
 * File Structure:
 * 1) Include config- and setup
 * 2) Define GroupContext instance
 * 3) Install GroupContext into MediaWiki UserLoadFromSession-hook
 */


// Check to make sure we're actually in MediaWiki.
if (!defined('MEDIAWIKI')) die('This file is part of MediaWiki. It is not a valid entry point.');

// include my dependencies:
require_once('ConextGroup.php');	//model class


// Setup context in which the extension is being executed
require_once("GroupContext.setup.php");

// Wrapper for caching an object in session
class GroupContext_CacheItem {
	static $expiration_period = 3600;	// number of seconds a cached item is valid; can be
										// overruled by define('CACHE_TTL' ...
	
	public $_created;	// unix timestamp when item was created
	public $_content;	// data to store; must be serializable/deserializable!
	
	function __construct($content, $created = -1) {
		$this->_content = serialize($content);
		$this->_created = ($created < 0 ? time() : $created);
	}
	
	function getContent() {
		return unserialize($this->_content);
	}
	
	function isExpired() {
		if (defined('CACHE_TTL')) {
			$exp = intval(CACHE_TTL);
		} else {
			$exp = self::$expiration_period;
		}
		
		return ($this->_created < (time() - $exp));
	}
}



class GroupContext {
	
	/**
	 * @var String
	 * Name of the session-variable that will hold the (cached) groups of a user
	 */
	static $sessionkey = 'GroupContext';
	
	/**
	 * @var boolean
	 * Flag indicating whether we are currently inside the session hook
	 * function.
	 * Used to prevent recursion.
	 *
	 */
	static $inUserLoadFromSessionHook = false;
	
	

	/**
	 * Helper to retrieve GroupContext-array from session
	 * Returns array when available, or null when no (valid) groupcontext was
	 * found in session
	 */
	private static function getGroupContextFromSession() {
		if (isset($_SESSION[self::$sessionkey])) {
			$a = $_SESSION[self::$sessionkey];
			if (is_object($a) && (get_class($a) == 'GroupContext_CacheItem')) {
				if (! $a->isExpired()) {
					// be done.
					return $a->getContent();
				}
			}
		}
		
		return null;
	}
	

	/**
	 * Clean the group context from the session
	 * This means: remove the session data
	 */
	static function removeGroupContext() {
		unset($_SESSION[self::$sessionkey]); 
	}
	
	
	/**
	 * Establish a valid Group-array in the session
	 * @param $user User to establish groups for
	 * @return boolean true when all ok
	 */
	static function setGroupContext($user) {
		// Are we recursing into ourself? [working arnound mediawiki 'bug']
		if (self::$inUserLoadFromSessionHook) {
			return true;
		}
		
		self::enterUserLoadFromSessionHook();
		
		// retrieve session:
		$g = self::getGroupContextFromSession();
		
		if ($g !== null) {
			// groups already exist in session, so be done.
			self::leaveUserLoadFromSessionHook();
			return true;
		}
		
		// Add teams as groups that the user is member of in MediaWiki
		global $grouprel_config;
		$oGroupRel = IGroupRelations::create($grouprel_config['impl']);
		
		// try to load a new user from a saved session cookie
		$user = User::newFromSession();
		$user->load();
		
		$username = $user->getName();
		
		// Establish username to use, as in: decapitalize first character:
		$username = lcfirst($username);
		
		// Make OpenSocial-call:
		try {
			$r = $oGroupRel->fetch(array('userId' => $username));
			wfDebugLog('conext', __METHOD__ . ': ' . "Resolved external groups: " . implode($r));
		} catch(Exception $e) {
			wfDebugLog('conext', __METHOD__ . ': ' . "Problem with resolving external groups: " . $e->getMessage());
			
			self::leaveUserLoadFromSessionHook();
			return false;
		}
		
		// Set resulting GroupContext in session
		$a = new GroupContext_CacheItem($r);
		$_SESSION[self::$sessionkey] = $a; 

		// Be done.
		self::leaveUserLoadFromSessionHook();
		return true;
	}

	/**
	 * MediaWiki Hook function that establishes effective groups
	 * Responsible for inserting groups from OpenSocial into user's EffectiveGroup array
	 * @param unknown_type $user User to work for
	 * @param unknown_type $aUserGroups Group that were already established for the user
	 * returns true, indicating that next hook can be performed.
	 */
	public static function onUserEffectiveGroups( &$user, &$aUserGroups ) {
		global $wgVersion;
		$msgs = null;
		
		/*
		 * Still figuring out how to make the groupnames to translate to their title
		if (version_compare( $wgVersion, '1.18.0', '<' )) {
			global $wgMessageCache;
			$msgs = $wgMessageCache;
		} else {
			$msgs = MessageCache::singleton();
		}
		$lc = Language::getLocalisationCache();
		print_r($lc);

		*/
		
		$groupContextGroups = array();
		
		// Assert that groups are set in session, or cancel attempt
		if (! self::setGroupContext($user)) {
			return false;
		}
		
		// Resolve the group context from the session; these are set as 
		// GroupRel/Model/Group-instances
		$g = self::getGroupContextFromSession();
		if ($g !== null) {
			foreach ($g as $group) {
				$g = new ConextGroup(); 
				// $g->set($group->getIdentifier(), $group->_aAttributes["title"]);
 				$groupContextGroups[] = $group->getIdentifier();
 
 				// $a = array($group->getIdentifier() => $group->_aAttributes["title"]);
				// $msgs->addMessages($a);
				
				
			}
		}
		
		
		// Merge groups into the user's groups
		$aUserGroups = array_merge($aUserGroups, $groupContextGroups);
		
		// Be done.
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
	function onUserLogout(&$user) {
		wfDebugLog('conext', __METHOD__ . ': ' . 'Cleaning up GroupContext on UserLogout for user ' . $user->getName());
		self::removeGroupContext();
		
		return true;
	}
	
	
	
	/**
	 * This is just a wrapper function that marks if the userLoadFromSessionHook
	 * function is entered.
	 * @see userLoadFromSessionHook
	 */
	private static function enterUserLoadFromSessionHook() {
		if (self::$inUserLoadFromSessionHook === true) {
			wfDebugLog('conext', __METHOD__ . ': ' . "Tried to enter the hook function twice. This is not good.");
		}

		self::$inUserLoadFromSessionHook = true;
	}

	/**
	 * This is just a wrapper function that marks if the userLoadFromSessionHook
	 * function is left.
	 * @see userLoadFromSessionHook
	 */
	private static function leaveUserLoadFromSessionHook() {
		if (self::$inUserLoadFromSessionHook === false) {
			wfDebugLog('conext', __METHOD__ . ': ' . "Tried to leave the hook function twice. This is not right.");
		}

		self::$inUserLoadFromSessionHook = false;
	}
	
	
}


// ==========================================================================
// Install hooks:
global $wgHooks;
$wgHooks['UserEffectiveGroups'][] = 'GroupContext::onUserEffectiveGroups';


$wgHooks['UserLogout'][] = 'GroupContext::onUserLogout';

?>
