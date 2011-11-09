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

// Check to make sure we're actually in MediaWiki.
if (!defined('MEDIAWIKI')) die('This file is part of MediaWiki. It is not a valid entry point.');

require_once("MultiAuthPlugin.body.php");


/*********************************************
 *             AUTH PLUGIN SETUP             *
 *********************************************/
/*
 * This is called earlier in the setup process to explicitly
 * setup an authentication plugin, which we also do here as intended.
 */

function multiAuthSetup() {

	/*********************************************
	 *             SETUP DEBUGGING               *
	 *********************************************/

	/**
	 * The debug log file should be not be publicly accessible if it is used, as it
	 * may contain private data.  But it must be in a directory to which PHP run
	 * within your Web server can write.
	 *
	 * Setup the logging directory like this:
	 * > cd WIKIPATH
	 * > mkdir logs
	 * > chown apache:apache logs
	 */
	global $wgDebugLogGroups;
	$wgDebugLogGroups['MultiAuthPlugin'] = dirname(__FILE__) . '/log/debug.log';


	/********************************************
	 *              PLUGIN SETUP                *
	 ********************************************/

	// create a (global) plugin instance
	global $wgMultiAuthPlugin;
	$wgMultiAuthPlugin = new MultiAuthPlugin(dirname(__FILE__) . '/MultiAuthPlugin.config.php');

	// setup special pages
	require_once("special/login/SpecialLogin.setup.php");
	require_once('special/logout/SpecialLogout.setup.php');
	
	// replace standard AuthPlugin with the MultiAuthPlugin
	global $wgAuth;
	$wgMultiAuthPlugin->old_wgAuth = $wgAuth;
	$wgAuth = &$wgMultiAuthPlugin;


	return true;
}
// register setup function
global $wgHooks;
$wgHooks['AuthPluginSetup'][] = 'multiAuthSetup';





/*********************************************
 *           DEFERRED PLUGIN SETUP           *
 *********************************************/
/*
 * This is called together with all other extension functions at the
 * very end of the initialization process
 */


function deferredMultiAuthSetup() {

	/********************************************
	 *                HOOKS SETUP               *
	 ********************************************/

	global $wgHooks;
	global $wgMultiAuthPlugin;

	/*
	 * Hook for adding/modifying the URLs contained in the personal URL bar
	 */
	$wgHooks['PersonalUrls'][] = array(&$wgMultiAuthPlugin, 'addLinkHook');

	/*
	 * User authentication hook
	 *
	 * This is not really perfect but the best place mediwiki provides to hook
	 * in the user instantiation/authentication process.
	 */
	$wgHooks['UserLoadFromSession'][] = array(&$wgMultiAuthPlugin, 'userLoadFromSessionHook');

	/*
	 * Hook to place own data in the session managed by mediawiki.
	 * This is called every time $wgUser->setCookies() is called.
	 */
	$wgHooks['UserSetCookies'][] = array(&$wgMultiAuthPlugin, 'userSetCookiesHook');

	/*
	 * Hook to manage logout of a user properly (e.g. clear own session data)
	 * This is called every time $wgUser->logout() is called.
	 */
	$wgHooks['UserLogout'][] = array(&$wgMultiAuthPlugin, 'userLogoutHook');

	/*
	 * Hook to modify aliases of special pages.
	 * This is used to replace the regular login page with the MultiAuth login
	 * page.
	 */
	$wgHooks['LanguageGetSpecialPageAliases'][] = array(&$wgMultiAuthPlugin, 'filterSpecialPageAliasesHook');

	
	/*
	 * Hook to modify URLs returned by calls to the getLocalURL method.
	 * INFO: This is here for testing purposes.
	 */
	//$wgHooks['GetLocalURL'][] = array(&$wgMultiAuthPlugin, 'getLocalURLHook');
	
	
	/********************************************
	 *            LOCALISATION SETUP            *
	 ********************************************/

	global $wgExtensionMessagesFiles;

	// localisation
	$wgExtensionMessagesFiles['MultiAuthPlugin'] =  dirname(__FILE__) . '/MultiAuthPlugin.i18n.php';
	wfLoadExtensionMessages('MultiAuthPlugin');

	/********************************************
	 *                 CREDITS                  *
	 ********************************************/

	global $wgExtensionCredits;

	// credits
	$wgExtensionCredits['other'][] = array(
		'path' 			=> __FILE__,
		'name' 			=> wfMsg('credits_name'),
		'version'		=> $wgMultiAuthPlugin->getVersion(),  // see MultiAuthPlugin.config.php to modify version number
		'author' 		=> wfMsg('credits_author'), 
		'url' 			=> $wgMultiAuthPlugin->getURL(), 
		'description' 	=> wfMsg('credits_description')
	);


	// reset the aliases chache to ensure all recently and subsequently added
	// page aliases are recongnized despite the fact that the MA extension
	// already uses URL building functions
	// TODO File bug report that the cache needs a dirty flag!
	global $wgContLang;
	if (isset($wgContLang->mExtendedSpecialPageAliases))
	unset($wgContLang->mExtendedSpecialPageAliases);


	return true;
}
// register deferred setup functionn
global $wgExtensionFunctions;
$wgExtensionFunctions[] = 'deferredMultiAuthSetup';

?>
