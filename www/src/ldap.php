<?php

defined('LDAP_PORT') or define('LDAP_PORT', 389);
defined('LDAP_VERSION') or define('LDAP_VERSION', 3);


/**
 * Take an LDAP and make an associative array from it.
 *
 * From http://www.php.net/manual/en/function.ldap-get-entries.php#62145
 * This function takes an LDAP entry in the ldap_get_entries() style and
 * converts it to an associative array like ldap_add() needs.
 *
 * @param array $entry is the entry that should be converted.
 *
 * @return array is the converted entry.
 */
function cleanUpEntry( $entry ) {

    $retEntry = array();
    for ( $i = 0; $i < $entry['count']; $i++ ) {
        $attribute = $entry[$i];
        if ( $entry[$attribute]['count'] == 1 ) {
            $retEntry[$attribute] = $entry[$attribute][0];
        } else {
            for ( $j = 0; $j < $entry[$attribute]['count']; $j++ ) {
                $retEntry[$attribute][] = $entry[$attribute][$j];
            }
        }
    }
    return $retEntry;
}

/**
 * Return a cleaned up array of entries, excluding the count
 * @param $entries entries as returned by ldap_get_entries
 * @return array|bool
 */
function cleanUpEntries($entries) {
    return array_diff_key(array_map('cleanUpEntry', $entries), array('count' => 0));
}

/*
* ldap_login()
*/
function ldap_login($dn, $passwd) {
    $ds = ldap_connect(LDAP_SERVER, LDAP_PORT);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
    $ldapbind = @ldap_bind($ds, $dn, $passwd);
    if ($ldapbind) {
        return ($ds);
    } else {
        throw new Exception(ldap_error($ds), ldap_errno($ds));
    }
}