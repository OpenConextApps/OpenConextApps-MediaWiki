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

/**
 * The logout special page
 *
 * @author Florian Löffler <florian.loeffler@rrze.uni-erlangen.de>
 *
 */
class MultiAuthSpecialLogout extends SpecialPage {

	/**
	 * @var MultiAuthPlugin
	 * reference to the active instance of the MultiAuthPlugin
	 */
	var $multiAuthPlugin = null;



	/**
	 * Default constructor
	 * @see includes/SpecialPage.php
	 */
	function __construct() {
		// The name should be the same as the id of the special page
		parent::__construct('MultiAuthSpecialLogout', '', true, false, 'default', false);

		// get a reference to an instance of the multi auth plugin
		global $wgMultiAuthPlugin;
		$this->multiAuthPlugin =  &$wgMultiAuthPlugin;
	}

	/**
	 * This function is called if the special page is accessed
	 * inspired by @see includes/special/SpecialUserlogout.php
	 * @param $par parameters
	 * @see includes/SpecialPage.php
	 */
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		$this->setHeaders();
		$param = $wgRequest->getText('param');

		// build the page
		$html = "";

		if (!$this->multiAuthPlugin->isLoggedIn()) {

			// check if we should be redirected to an external URL after complete logout
			if ($this->multiAuthPlugin->config['internal']['redirectAfterLogoutComplete'] != '') {
				// do the redirect
				header('Location: ' . $this->multiAuthPlugin->config['internal']['redirectAfterLogoutComplete']);
				exit; // Stop execution here
			}
			else {
				$html .= "<p>" . wfMsg('msg_logoutSuccess') . "</p>\n";
			}

		}
		else {

			// get information about the currently active authentication method
			$currentMethodName = $this->multiAuthPlugin->getCurrentMethodName();

			if (!empty($currentMethodName) && $currentMethodName != 'local') {
				$this->doExternalLogout($html);
			}
			else {
				$this->doLocalLogout($html);
			}

		}

		$wgOut->addHTML($html);
		$wgOut->returnToMain();
	}

	/**
	 * Initiates a local logout and adds a status message to
	 * the page HTML code.
	 * @param string $html the variable containing the HTML code for the page
	 * @return boolean
	 * was the local logout complete?
	 */
	private function doLocalLogout(&$html) {
		global $wgUser;
		$oldName = $wgUser->getName();

		// start the logout process
		$success = $this->multiAuthPlugin->logout();

		if ($success) {
			$injectedHtml = "";
			
			// Run the logout complete hook for local logout
			wfRunHooks('UserLogoutComplete', array(&$wgUser, &$injectedHtml, $oldName));

			$html .= "<p>" . wfMsg('msg_logoutSuccess') . "</p>\n";
			$html .= $injectedHtml;
			return true;
		}
		else {
			$html .= "<p>" . wfMsg('msg_logoutFailure') . "</p>\n";
			return false;
		}
	}

	/**
	 * Initiates a two-stage logout process.
	 * At first the external logout URL is called for external logout.
	 * After return to this logout page a local logout is performed additionally.
	 * @param string $html the variable containing the HTML code for the page
	 */
	private function doExternalLogout(&$html) {
		global $wgUser;
		$oldName = $wgUser->getName();

		// prepare data for external logout
		$currentMethod = $this->multiAuthPlugin->getCurrentMethod();
		$link = $currentMethod['logout'];
		$link_text = $link['text'];
		$link_href = $link['href'];


		if ($this->multiAuthPlugin->config['internal']['authMode'] == 'lazy') {
			// we can come back to MW
			$local_href = SpecialPage::getTitleFor('MultiAuthSpecialLogout')->escapeLocalURL();
		}
		else {
			// we won't be able to come back to MW because of access restrictions
			$local_href = isset($this->multiAuthPlugin->config['internal']['strictLogoutTarget'])?
			$this->multiAuthPlugin->config['internal']['strictLogoutTarget']:'';
		}

		// build the logout url
		if (strstr($link_href, '{RETURN_URL}')) {
			$return_url = $local_href;
			if (strstr($return_url, '{RETURN_URL}') && isset($_REQUEST['returnto'])) {
				$return_url = str_replace('{RETURN_URL}', $_REQUEST['returnto'], $return_url);
			}
			$link_href = str_replace('{RETURN_URL}', wfUrlencode($return_url), $link_href);
		}

			
		// first: local
		$success = $this->multiAuthPlugin->logout();
		if ($success) {
			// finish off the local logout
			wfRunHooks('UserLogoutComplete', array(&$wgUser, &$injectedHtml, $oldName));

			// second: external
			wfDebugLog('MultiAuthPlugin', __METHOD__ . ': ' . "Initiating external logout process (".$link_href.").");
			header("Location: " . $link_href);
			exit(); // no execution past here!
		}
		else {
			$html .= "<p>" . wfMsg('msg_logoutFailure') . "</p>\n";
			return false;
		}
	}

}

?>
