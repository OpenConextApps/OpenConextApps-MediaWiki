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
global $authData; // make auth data available for the methodSetupFile

/* ********************************************
   *                  METHODS                 *
   ******************************************** */

$config['methods'] = array(
	/**************************************************************************/
	/* LOCAL - DON'T CHANGE THIS                                              */
	/**************************************************************************/
	'local' => array(
		'login' => array(
			'text' => 'Login (local)',
			'href' => SpecialPage::getTitleFor('Userlogin')->escapeFullURL() . '?returnto={RETURN_URL}',
		),
		
		'logout' => array(
			'text' => 'Logout (local)',
			'href' => SpecialPage::getTitleFor('Userlogout')->escapeFullURL() . '?returnto={RETURN_URL}',
		),
		
		'attributes' => array(),
		
	),
	/**************************************************************************/
	/* END LOCAL                                                              */
	/**************************************************************************/
	
	
	'shibboleth-default' => array(
		'login' => array(
			'text' => 'Login (SSO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Login?target={RETURN_URL}',
		),
	
		'logout' => array(
			'text' => 'Logout (SLO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Logout?return={RETURN_URL}',
		),
		
		'attributes' => array(
			'username'	=> ucfirst($authData['uid']),
			'fullname'	=> $authData['cn'],
			'email'		=> $authData['mail'],
		),

		'requirements' => array(
			//'username' => '*',  	// this is always implied (hardcoded!)
			'email' => '*', 		// email is mandatory, but any value will be accepted 
		),
				
	),
	
	
	
	
	/**************************************************************************/
	/* SAMPLES TO BE COPIED AND MODIFIED                                      */
	/**************************************************************************/
	
	/*
	 * SAMPLE
	 * Shibboleth SP with some basic user mappings 
	 */
	'sample-shibboleth-default' => array(
		'login' => array(
			'text' => 'SAMPLE - Login via Shibboleth SP default target',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Login?target={RETURN_URL}',
		),
	
		'logout' => array(
			'text' => 'SAMPLE - Logout (SLO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Logout?return={RETURN_URL}',
		),
		
		'attributes' => array(
			'username'	=> ucfirst($authData['uid']),
			'fullname'	=> $authData['cn'],
			'email'	=> $authData['mail'],
		),
		
	),
	
	/*
	 * SAMPLE
	 * Same as above but with some login requirements
	 */
	'sample-shibboleth-restricted' => array(
		'login' => array(
			'text' => 'SAMPLE - Login via Shibboleth SP default target with requirements',
			//'text' => 'Login via Shibboleth SP (RRZE-SSO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Login?target={RETURN_URL}',
		),
	
		'logout' => array(
			'text' => 'SAMPLE - Logout (SLO)',
			//'text' => 'Logout via Shibboleth SP (RRZE-SLO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Logout?return={RETURN_URL}',
		),
		
		'attributes' => array(
			'username'	=> ucfirst($authData['uid']),
			'fullname'	=> $authData['cn'],
			'email'	=> $authData['mail'],
		),
		
		'requirements' => array(
			//'username' => '*',  // this is always implied (hardcoded!)
			'email' => '*', // email is mandatory, but any value will be accepted 
			'entitlement' => 'rrze_wiki', // entitlement has to be 'rrze_wiki'
		),
		
	),
	
	
	
	/*
	 * SAMPLE
	 * Shibboleth SP using a preconfigured application target 
	 * other than the default one 
	 */
	'sample-shibboleth-someApp' => array(
		'login' => array(
			'text' => 'SAMPLE - Login via Shibboleth SP someApp target',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Login/someApp?target={RETURN_URL}',
		),
	
		'logout' => array(
			'text' => 'SAMPLE - Logout (SLO)',
			'href' => WebFunctions::getBaseURL() .  '/Shibboleth.sso/Logout?return={RETURN_URL}',
		),

		'attributes' => array(
			'username'	=> ucfirst($authData['uid']),
			'fullname'	=> $authData['cn'],
			'email'	=> $authData['mail'],
		),
	),
	
	
	/*
	 * SAMPLE
	 * SimpleSamlPHP SP basic   
	 */
	'simplesamlphp-default' => array(
		'login' => array(
			'text' => 'Login via SURFfederatie',
			// 'href' => WebFunctions::getBaseURL() .  '/simplesaml/saml2/sp/initSSO.php?RelayState={RETURN_URL}',
			'href' => WebFunctions::getBaseURL() . '/MediaWiki/simplesaml/module.php/core/as_login.php?AuthId=default-sp&ReturnTo={RETURN_URL}',
//			'href' => WebFunctions::getBaseURL() . '/MediaWiki/simplesaml/module.php/core/as_login.php?AuthId=default-sp',
			'sp-definition' => 'default-sp', 
	),
	
		'logout' => array(
//			'text' => 'SAMPLE - Logout (SLO)',
//			'href' => WebFunctions::getBaseURL() .  '/simplesaml/saml2/sp/initSLO.php?RelayState={RETURN_URL}',
			'text' => 'Logout (local)',
			'href' => SpecialPage::getTitleFor('Userlogout')->escapeFullURL() . '?returnto={RETURN_URL}',
	
		),

		'attributes' => array(
			// 'username'	=> ucfirst($authData['uid']),
			//			'username' => $authData['eduPersonPrincipalName'],
			'username' => $authData['NameID'],			
			'fullname'	=> $authData['cn'],
			'email'	=> $authData['mail'],
		),
	),
	
	/*
	 * SAMPLE
	 * SimpleSamlPHP SP using a preconfigured IdP as the
	 * target IdP for user authentication
	 */
	'sample-simplesamlphp-someIdP' => array(
		'login' => array(
			'text' => 'SAMPLE - Login via SimpleSamlPHP SP someIdP target',
			'href' => WebFunctions::getBaseURL() .  '/simplesaml/saml2/sp/initSSO.php?RelayState={RETURN_URL}&idpentityid=' . wfUrlencode('someIdP'),
		),
	
		'logout' => array(
			'text' => 'SAMPLE - Logout (SLO)',
			'href' => WebFunctions::getBaseURL() .  '/simplesaml/saml2/sp/initSLO.php?RelayState={RETURN_URL}',
		),

		'attributes' => array(
			'username'	=> ucfirst($authData['uid']),
			'fullname'	=> $authData['cn'],
			'email'	=> $authData['mail'],
		),
	),
);

?>