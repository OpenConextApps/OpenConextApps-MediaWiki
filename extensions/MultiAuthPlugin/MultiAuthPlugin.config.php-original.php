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

$config = array();

/* ********************************************
   *                  GENERAL                 *
   ******************************************** */
$config['general'] = array(
	
	/*
	 * Current version number string and home url of the plugin.
	 * PLEASE DON'T CHANGE THIS, THAT'S THE AUTHOR'S JOB ;)
	 */
	'version' => '1.3.2',
	'url' => 'https://www.pp.wiki.uni-erlangen.de/index.php/MediaWikiAuthPlugin',

);


/* ********************************************
   *                  PATHS                   *
   ******************************************** */
$config['paths'] = array(

	/*
	 * Paths to potentially installed libraries. You may modify this to your 
	 * needs as you wish.
	 * Note that this only modifies the search path for the specified libs but
	 * does not imply that they get loaded. This depends on the configuration
	 * below.
	 * DEFAULTS:
	 * 	'simplesamlphp' => dirname(__FILE__) . '/libs/simplesamlphp',
	 */
	'libs' => array(
		'simplesamlphp' => '/var/simplesamlphp',
	),

);


/* ********************************************
   *                 INTERNAL                 *
   ******************************************** */
$config['internal'] = array(

	/*
	 * The authentication library to use for external authentication.
	 * 
	 * NOTE: Currently supported libraries are 'shibboleth' (needs running 
	 * shibd) and 'simplesamlphp' (needs simplesamlphp installed).
	 */
	'authLib' => 'shibboleth',
	//'authLib' => 'simplesamlphp',

	/*
	 * In this step authentication data from is acquired and made available in 
	 * the global namespace as an associative array called "authData", so that 
	 * the methodSetupFile can access it.
	 * The data will be read using the authLib specified above.
	 * 
	 * IMPORTANT: All attributes that could not be retrieved are set with an 
	 * empty string value.
	 */
	'authData' => array('uid', 'givenName', 'sn', 'cn', 'mail', 'eduPersonPrincipalName'),


	/*
	 * Lazy authentication leaves MW with complete control over who gets access
	 * to specific pages.
	 * 
	 * Strict authentication leaves a higher level authority (e.g. Shibboleth)
	 * with complete control who gets access to the MW files. One drawback of
	 * this constellation is that after logging out there is no access to MW's
	 * logout-success page. Therefore an external logout-success page has to be
	 * specified using the strictLogoutTarget function.
	 * 
	 * DEFAULT:
	 * 	'authMode' => 'lazy',
	 *	'strictLogoutTarget' => 'https://www.sso.uni-erlangen.de/logout.html',
	 * 
	 */
	'authMode' => 'lazy',
	'strictLogoutTarget' => 'https://www.sso.uni-erlangen.de/logout.html',

	/*
	 * Does a redirect to the specified URL after the logout process is
	 * completed. If the URL is left empty the MultiAuth logout page will be
	 * displayed.
	 * IMPORTANT: This can be used to archive the same effect as with the 'strictLogoutTarget'
	 * option although using lazy authentication
	 * DEFAULT: 'redirectAfterLogoutComplete' => '',
	 */
	'redirectAfterLogoutComplete' => '',


	/*
	 * Automatically create local accounts for successfully authenticated
	 * users.
	 * If enabled above notification emails will be sent.
	 * DEFAULT: 'enableAutoCreateUsers' => true,
	 */
	'enableAutoCreateUsers' => true,



/*
	 * Automatically update local accounts with changed user data provided
	 * by the IdP.
	 * DEFAULT: 'enableAutoUpdateUsers' => true,
	 */
	'enableAutoUpdateUsers' => true,



	/*
	 * This allows you to configure if and/or who may and may not
	 * authenticate himself to the local database via MW's original
	 * login mechanism.
	 * This does not disable the 'local' option but controls whether a login
	 * attempt via 'local' will be processed or immediately denied.
	 * Use _ONE_ of the following lines to configure authentication
	 * against the local database.
	 * DEFAULT: 'enableLocalAuth' => true 
	 */
	'enableLocalAuth' => true,
	//'enableLocalAuth' => array( 'Admin' ),
	//'disableLocalAuth' => true,
	//'disableLocalAuth' => array( 'UserXY' ),


	/*
	 * This file sets up the authentication choices available via the login
	 * dialog.
	 * The file will be included and automatically processed during 
	 * MA's initialisation process.
	 */
	'methodSetupFile' => dirname(__FILE__) . '/MultiAuthPlugin.methods.php',


	/*
	 * This allows you to configure which of the entries in the 'methods'
	 * array (see methodSetupFile) are processed by the MultiAuthPlugin. 
	 * Disabled methods will not be displayed or be otherwise accessible by 
	 * the user. 
	 * Methods are processed in their order of apearance here.
	 * 
	 * NOTE: This refers to the methods defined in the methodSetupFile.
	 * IMPORTANT: Removing 'local' here does NOT make it impossible to log in
	 * locally, it just removes the link! 
	 */
	'methods' => array(
		'local',
		'shibboleth-default',

		/*
		 * SAMPLES
		 * This should be commented out in production!
		 */

//		'sample-shibboleth-default',
//		'sample-shibboleth-restricted',
//		'sample-shibboleth-someApp',
//		'sample-simplesamlphp-default',
//		'sample-simplesamlphp-someIdP',

	),

		
);



/* ********************************************
   *              COMMUNICATION               *
   ******************************************** */
$config['comm'] = array(

	/*
	 * Configures communication events that should be processed when
	 * a new user is created and added to the database.
	 */
	'onUserCreation' => array(

		/*
         * Notify the configured address about new automatically created aucounts 
	 	 * via email.
	 	 * IMPORTANT: This uses PHP's mail() function which needs a working mail
	 	 * system (MTA, eg. postfix) and a correct php.ini configuration.
	 	 * DEFAULT: 
	 	 * 	'notifyMail' => true,
	 	 * 	'notifyMailTo' => '',
	 	 */
		'notifyMail' => true,
		'notifyMailTo' => 'florian.loeffler@rrze.uni-erlangen.de',
		'notifyMailFrom' => 'noreply@ma-mailer',


		/*
		 * Subject and message content for a success notification mail.
		 * {USERNAME} will be replaced with the respective username.
	 	 */
		'notifyMailSubjectSuccess' => '{SITENAME}: Added new user {USERNAME}',
		'notifyMailMessageSuccess' => '{SITENAME}: Successfully added a new user \'{USERNAME}\' to the database.',

		/*
		 * Subject and message content for an error notification mail.
		 * {USERNAME} will be replaced with the respective username.
	 	 */
		'notifyMailSubjectError' => '{SITENAME}: FAILED to add new user {USERNAME}',
		'notifyMailMessageError' => '{SITENAME}: FAILED to add a new user \'{USERNAME}\' to the database.',
	),
	
);

/* ********************************************
   *                DEBUGGING                 *
   ******************************************** */
$config['debug'] = array(

	/*
	 * Log a dump of the $_SERVER variable on EVERY request.
	 * This can make your log quite big.
	 * DEFAULT: 'logServerVariables' => false,
	 */
	'logServerVariables' => false,


	/*
	 * Log a dump of the attribute data as it comes directly
	 * from the authentication lib.
	 * DEFAULT: 'logRawAuthLibAttibuteData' => false, 
	 */
	'logRawAuthLibAttibuteData' => false,

	/*
	 * Log all retrived attributes as it is made accessible to the
	 * methodSetupFile for mapping to the methods. 
	 * In short: A dump of the $authData array.
	 * DEFAULT: 'logRetrievedAttributeData' => false,
	 */
	'logRetrievedAttributeData' => true,

);

?>