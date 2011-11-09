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
   *                 INCLUDES                 *
   ******************************************** */

require_once("includes/WebFunctions.php");



/* ********************************************
   *                PLUGIN SETUP              *
   ******************************************** */

require_once("MultiAuthPlugin.setup.php");




// DEBUG
// MAKE SURE THIS IS COMMENTED OUT IN PRODUCTION !!!

//$wgDebugLogFile = "debug.log";  // activate MW's logging mechanism
//print_r($GLOBALS);
$wgShowExceptionDetails = true; 
//error_reporting (E_ALL); ini_set("display_errors", 1); 
//echo "<!--"; print_r($_SERVER); print_r($_COOKIE); print_r($_REQUEST); print_r($_SESSION); echo "--> <b>DEBUGGING ENABLED</b>";


// MAKE SURE THIS IS COMMENTED OUT IN PRODUCTION !!!

?>
