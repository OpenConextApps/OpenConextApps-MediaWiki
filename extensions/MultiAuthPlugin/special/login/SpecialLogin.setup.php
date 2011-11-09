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

/* ********************************************
   *            LOAD SPECIAL PAGE             *
   ******************************************** */

 require_once("SpecialLogin.body.php");


/* ********************************************
   *            SPECIAL PAGE SETUP            *
   ******************************************** */

// registering the id of the special page and the class to use
global $wgSpecialPages;
$wgSpecialPages['MultiAuthSpecialLogin'] = 'MultiAuthSpecialLogin';
// setting the special page group
global $wgSpecialPageGroups;
$wgSpecialPageGroups['MultiAuthSpecialLogin'] = 'login';



/* ********************************************
   *             DEFERRED SETUP               *
   ******************************************** */

function multiAuthLoginSetup() {
	global $wgExtensionMessagesFiles;
	global $wgExtensionCredits;
	global $wgExtensionAliasesFiles;
	global $wgMultiAuthPlugin;

	// localization
	$wgExtensionMessagesFiles['MultiAuthSpecialLogin'] =  
		dirname(__FILE__) . '/SpecialLogin.i18n.php';
	wfLoadExtensionMessages('MultiAuthSpecialLogin');

	// aliases
	$wgExtensionAliasesFiles['MultiAuthSpecialLogin'] = 
		dirname(__FILE__) . '/SpecialLogin.alias.php';
	

		
	// credits
	$wgExtensionCredits['specialpage'][] = array(
		'path' 			=> __FILE__,
		'name' 			=> wfMsg('credits_name'),
		'version'		=> $wgMultiAuthPlugin->getVersion(),
		'author' 		=> wfMsg('credits_author'), 
		'url' 			=> $wgMultiAuthPlugin->getURL(), 
		'description' 	=> wfMsg('credits_description')
		);

	
	// rehook Special:UserLogin?
	if (is_array($wgMultiAuthPlugin->config['extra']) 
			&& $wgMultiAuthPlugin->config['extra']['rehookUserLogin']) 
	{
				SpecialPage::$mList['Userlogin'] = array( 'SpecialPage', 'MultiAuthSpecialLogin' ); 		
	}
}

// register deferred setup functionn
global $wgExtensionFunctions;
$wgExtensionFunctions[] = 'multiAuthLoginSetup';


?>
