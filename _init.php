<?php

require dirname(dirname(__FILE__)).'/server-config.php';

// Base modules
include dirname(__FILE__).'/lib/ldap.php';
include dirname(__FILE__).'/lib/smbhash.php';
include dirname(__FILE__).'/lib/router.php';

$ldap = ldap_login(LDAP_ADMINDN, LDAP_PW);

if(!$ldap) {
    respond('Cannot connect to LDAP server, check settings', 500);
}

// App modules
include dirname(__FILE__).'/lib/usersgroups.php';

defined('USER_HOME') or define('USER_HOME', '/home/%s');
defined('USER_DEFAULTSHELL') or define('USER_DEFAULTSHELL', '/bin/bash');

function tiraAcentos($string) {
    return(strtr($string,
        'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ',
        'AAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy'));
}