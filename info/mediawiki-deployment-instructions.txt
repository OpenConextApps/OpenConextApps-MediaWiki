MediaWiki Deployment Instructions
================================================================
Date: 2011-nov-03
Author: M. Dobrinic


MediaWiki can be installed form a Debian package. This copies the application
files to /usr/share/mediawiki and /var/lib/mediawiki
It also creates a directory /etc/mediawiki that contains the application configuration
file (LocalSettings.php) as well as webserver-configurations to manage MediaWiki
as a module for an Apache-website.


* Install MediaWiki from a package
The mediawiki package is called (surprisingly...) 'mediawiki'.
Install with:

	# apt-get install mediawiki


* Install extension for external authentication
Authentication using SURFfederatie is done using the MultiAuthPlugin package
This must be installed in /var/lib/mediawiki/extensions

Default configuration is provided.
Please see multiauthplugin-documentation for more information.


* Install extension for OpenSocial group support
This is performed through the conext-extension.
The conext-extension must be installed in /var/lib/mediawiki/extensions

Default configuration is provided, but OpenSocial OAuth-settings (endpoints and
key/secrets) must be reviewed!
Please see conext-documentation for more information.
 
