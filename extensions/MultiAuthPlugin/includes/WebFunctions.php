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

/**
 * Some web-related convenience functions 
 * 
 * @author Florian Löffler <florian.loeffler@rrze.uni-erlangen.de>
 */
class WebFunctions {
	
	/**
	 * Get the base url for the wiki installation, 
	 * eg. http://sub.domain.com
	 * @return string baseURL
	 */
	static function getBaseURL() {
		global $wgServer;
		$linkBase = $wgServer;
		return $linkBase;
	}

	/**
	 * Get the url pointing to the wiki. That means including 
	 * subdirectories, eg. http://sub.domain.com/wiki
	 * @return string wikiURL
	 */
	static function getWikiURL() {
		global $wgScriptPath;
		$t_scriptPath = trim($wgScriptPath, '/ ');
		$linkHome = WebFunctions::getBaseURL() . '/' . $t_scriptPath;
		$linkHome = trim($linkHome, '/ ');
		return $linkHome;
	}
	
	/**
	 * Get the url pointing to the wiki's index file. In short: The main page
	 * for this wiki, eg. http://sub.domain.com/wiki/index.php
	 * @return string homeURL
	 */
	static function getHomeURL() {
		global $wgScriptExtension;
		$linkHome = WebFunctions::getWikiURL() . '/index' . $wgScriptExtension;
		return $linkHome;
	}
	
}


?>
