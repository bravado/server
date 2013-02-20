<?php

require dirname(dirname(__FILE__)).'/server-config.php';

include dirname(__FILE__).'/lib/ldap.php';
include dirname(__FILE__).'/lib/smbhash.php';
include dirname(__FILE__).'/lib/router.php';

defined('USER_HOME') or define('USER_HOME', '/home/%s');
defined('USER_DEFAULTSHELL') or define('USER_DEFAULTSHELL', '/bin/bash');


function get_ds() {

}
function tiraAcentos($string) {
    return(strtr($string,
        'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ',
        'AAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy'));
}