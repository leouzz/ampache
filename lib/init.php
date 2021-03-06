<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2013 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

// Use output buffering, this gains us a few things and
// fixes some CSS issues
ob_start();

$ampache_path = dirname(__FILE__);
$prefix = realpath($ampache_path . "/../");
require_once $prefix . '/lib/init-tiny.php';

// Explicitly load and enable the custom session handler.
// Relying on autoload may not always load it before sessiony things are done.
require_once $prefix . '/lib/class/session.class.php';
Session::_auto_init();

// Set up for redirection on important error cases
$path = preg_replace('#(.*)/(\w+\.php)$#', '$1', $_SERVER['PHP_SELF']);
$path = $http_type . $_SERVER['HTTP_HOST'] . $path;

// Check to make sure the config file exists. If it doesn't then go ahead and 
// send them over to the install script.
if (!file_exists($configfile)) {
    $link = $path . '/install.php';
}
else {
    // Make sure the config file is set up and parsable
    $results = @parse_ini_file($configfile);

    if (!count($results)) {
        $link = $path . '/test.php?action=config';
    }
}

// Verify that a few important but commonly disabled PHP functions exist and
// that we're on a usable version
if (!check_php()) {
    $link = $path . '/test.php';
}

// Do the redirect if we can't continue
if ($link) {
    header ("Location: $link");
    exit();
}

/** This is the version.... fluf nothing more... **/
$results['version']        = '3.6-alpha6+FUTURE';
$results['int_config_version']    = '12';

if ($results['force_ssl']) {
    $http_type = 'https://';
}

$results['raw_web_path'] = $results['web_path'];
$results['web_path'] = $http_type . $_SERVER['HTTP_HOST'] . $results['web_path'];
$results['http_port'] = $results['http_port'] ?: $http_port;
$results['site_charset'] = $results['site_charset'] ?: 'UTF-8';
$results['raw_web_path'] = $results['raw_web_path'] ?: '/';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?: '';

if (isset($results['user_ip_cardinality']) && !$results['user_ip_cardinality']) {
    $results['user_ip_cardinality'] = 42;
}

/* Variables needed for Auth class */
$results['cookie_path']     = $results['raw_web_path'];
$results['cookie_domain']    = $_SERVER['SERVER_NAME'];
$results['cookie_life']        = $results['session_cookielife'];
$results['cookie_secure']    = $results['session_cookiesecure'];

// Library and module includes we can't do with the autoloader
require_once $prefix . '/modules/getid3/getid3.php';
require_once $prefix . '/modules/phpmailer/class.phpmailer.php';
require_once $prefix . '/modules/phpmailer/class.smtp.php';
require_once $prefix . '/modules/snoopy/Snoopy.class.php';
require_once $prefix . '/modules/infotools/AmazonSearchEngine.class.php';
require_once $prefix . '/modules/infotools/lastfm.class.php';
require_once $prefix . '/modules/php_musicbrainz/mbQuery.php';
require_once $prefix . '/modules/ampacheapi/AmpacheApi.lib.php';

/* Temp Fixes */
$results = Preference::fix_preferences($results);

Config::set_by_array($results, true);

// Modules (These are conditionally included depending upon config values)
if (Config::get('ratings')) {
    require_once $prefix . '/lib/rating.lib.php';
}

/* Set a new Error Handler */
$old_error_handler = set_error_handler('ampache_error_handler');

/* Check their PHP Vars to make sure we're cool here */
$post_size = @ini_get('post_max_size');
if (substr($post_size,strlen($post_size)-1,strlen($post_size)) != 'M') {
    /* Sane value time */
    ini_set('post_max_size','8M');
}

// In case the local setting is 0
ini_set('session.gc_probability','5');

if (!isset($results['memory_limit']) || 
    (UI::unformat_bytes($results['memory_limit']) < UI::unformat_bytes('32M'))
) {
    $results['memory_limit'] = '32M';
}

set_memory_limit($results['memory_limit']);

/**** END Set PHP Vars ****/

// If we want a session
if (!defined('NO_SESSION') && Config::get('use_auth')) {
    /* Verify their session */
    if (!Session::exists('interface', $_COOKIE[Config::get('session_name')])) {
        Auth::logout($_COOKIE[Config::get('session_name')]);
        exit;
    }

    // This actually is starting the session
    Session::check();

    /* Create the new user */
    $GLOBALS['user'] = User::get_from_username($_SESSION['userdata']['username']);

    /* If the user ID doesn't exist deny them */
    if (!$GLOBALS['user']->id && !Config::get('demo_mode')) {
        Auth::logout(session_id());
        exit;
    }

    /* Load preferences and theme */
    $GLOBALS['user']->update_last_seen();
}
elseif (!Config::get('use_auth')) {
    $auth['success'] = 1;
    $auth['username'] = '-1';
    $auth['fullname'] = "Ampache User";
    $auth['id'] = -1;
    $auth['offset_limit'] = 50;
    $auth['access'] = Config::get('default_auth_level') ? User::access_name_to_level(Config::get('default_auth_level')) : '100';
    if (!Session::exists('interface', $_COOKIE[Config::get('session_name')])) {
        Session::create_cookie();
        Session::create($auth);
        Session::check();
        $GLOBALS['user'] = new User($auth['username']);
        $GLOBALS['user']->username = $auth['username'];
        $GLOBALS['user']->fullname = $auth['fullname'];
        $GLOBALS['user']->access = $auth['access'];
    }
    else {
        Session::check();
        if ($_SESSION['userdata']['username']) {
            $GLOBALS['user'] = User::get_from_username($_SESSION['userdata']['username']);
        }
        else {
            $GLOBALS['user'] = new User($auth['username']);
            $GLOBALS['user']->id = '-1';
            $GLOBALS['user']->username = $auth['username'];
            $GLOBALS['user']->fullname = $auth['fullname'];
            $GLOBALS['user']->access = $auth['access'];
        }
        if (!$GLOBALS['user']->id AND !Config::get('demo_mode')) {
            Auth::logout(session_id()); exit;
        }
        $GLOBALS['user']->update_last_seen();
    }
}
// If Auth, but no session is set
else {
    if (isset($_REQUEST['sid'])) {
        session_name(Config::get('session_name'));
        session_id(scrub_in($_REQUEST['sid']));
        session_start();
        $GLOBALS['user'] = new User($_SESSION['userdata']['uid']);
    }
    else {
        $GLOBALS['user'] = new User();
    }

} // If NO_SESSION passed

// Load the Preferences from the database
Preference::init();

if (session_id()) {
    Session::extend(session_id());
    // We only need to create the tmp playlist if we have a session
    $GLOBALS['user']->load_playlist();
}

/* Add in some variables for ajax done here because we need the user */
Config::set('ajax_url', Config::get('web_path') . '/server/ajax.server.php', true);

// Load gettext mojo
load_gettext();

/* Set CHARSET */
header ("Content-Type: text/html; charset=" . Config::get('site_charset'));

/* Clean up a bit */
unset($array);
unset($results);

/* Check to see if we need to perform an update */
if (!defined('OUTDATED_DATABASE_OK')) {
    if (Update::need_update()) {
        header("Location: " . Config::get('web_path') . "/update.php");
        exit();
    }
}
// For the XMLRPC stuff
$GLOBALS['xmlrpc_internalencoding'] = Config::get('site_charset');

// If debug is on GIMMIE DA ERRORS
if (Config::get('debug')) {
    error_reporting(E_ALL);
}
?>
