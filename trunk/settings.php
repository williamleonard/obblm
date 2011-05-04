<?php
/**************************
 * MySQL database settings
 **************************/

$db_name   = 'obblmdb';
$db_user   = 'obblmdb';
$db_passwd = 'obblmdb';
$db_host   = 'localhost';

// For local settings, ie. per league settings, edit the localsettings/settings_<LEAGUE ID>.php files.

/*************************
 * Global settings
 *************************/

$settings['site_name'] = 'DoubleSkulls League'; // Site name.
$settings['default_visitor_league'] = 1;    // ID of default league to show on front page when not logged in/coach has not selected a home league.
$settings['default_leagues'] = array(1);    // When creating a coach the coach will automatically become a regular coach in leauges with these IDs.
$settings['hide_ES_extensions'] = true;    // Default is false. Hides ES (Extra Stats) tables and ES references.

/*****************
 * House ranking systems
 *****************/

// For help see http://www.nicholasmr.dk/obblmwiki/index.php?title=Customization

// Rule #1
$hrs[1]['rule']   = array('-pts', '-td', '+smp');    // Sort teams against: most points, then most TDs and then least sportsmanship points.
$hrs[1]['points'] = '3*[won] + 2*[draw] + 1*[lost]'; // The definition of points.

// Rule #2
$hrs[2]['rule']   = array('-pts', '-ki', '-mvp'); // Sort teams against: most points, then most killed and then by most MVPs.
$hrs[2]['points'] = '2*[gf] - 1*[ga]';            // The definition of points.

// Rule #3
$hrs[3]['rule']   = array('-sdiff', '-smp'); // Sort teams against: largest score difference, then most sportsmanship points.
$hrs[3]['points'] = '';                      // Points not used.

// Add you own rules here...
$hrs[4]['rule']   = array('-pts', '-sdiff', '-tcdiff', '-td', '-tcasf', '-smp');    // Sort teams against: most points, then most net TDs and most net Cas.
$hrs[4]['points'] = '3*[won] + 1*[draw] + 0*[lost]'; // The definition of points.


?>
