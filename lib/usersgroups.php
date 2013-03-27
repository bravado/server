<?php
/*
 * Bravado Server
 * Users and Groups Library
 */

function get_local_sid() {
    global $ldap;
    $sr = ldap_search(
        $ldap,
        LDAP_BASE,
        "(objectclass=sambaDomain)");
    $entry = ldap_first_entry($ldap, $sr);

    if($entry) {
        $dn = ldap_get_dn($ldap, $entry);
        $values = ldap_get_values($ldap, $entry, 'sambaSID');
        if($values['count']) {
            return $values[0];
        }
        else {
            throw new Exception(sprintf(_("sambaSID not defined on %s"), $dn), 500);
        }
    } else {
        throw new Exception(_('Samba domain not found'), 500);
    }
}

/**
 * Returns the next id for the given attribute
 * NOTE: this function only verifies the LDAP database, not /etc/passwd or /etc/group
 * @param string $attribute - uidnumber, gidnumber, sambanextrid, sambanextuserrid
 * @return mixed the next ID
 * @throws Exception if any error happens
 */
function get_next_id($attribute) {
    global $ldap;

    $sr = ldap_search(
        $ldap,
        LDAP_BASE,
        "(objectclass=sambaUnixIdPool)");

    $entry = ldap_first_entry($ldap, $sr);

    if($entry) {
        $dn = ldap_get_dn($ldap, $entry);
        $values = ldap_get_values($ldap, $entry, $attribute);
        $tries = 30;
        $count = 0;
        if($values['count']) {
            $next_id =  $values[0];

            // Let's check if the id found is really free (in ou=Groups or ou=Users)...

            do {
                $check = ldap_search($ldap, LDAP_BASE, "(&($attribute=$next_id)(!(objectClass=sambaDomain)))");

                if($check && ldap_count_entries($ldap, $check) === 0) {
                    // not found, lets persist
                    if(ldap_modify($ldap, $dn, array($attribute => $next_id + 1))) {
                        return $next_id;
                    }
                    else {
                        throw new Exception(sprintf(_("Cannot persist incremented %s"), $attribute), 500);
                    }
                }
                else {
                    $next_id++;
                }
            }
            while($count++ < $tries);

            throw new Exception(sprintf(_("Could not allocate %s after %d tries"), $attribute, $tries), 500);

        }
        else {
            throw new Exception(sprintf(_('Cannot find %s attribute on %s'), $attribute, $dn), 500);
        }
    }
    else {
        throw new Exception(sprintf(_("Could not find entry to get next %s"), $attribute), 500);
    }

}




/**
 * Returns a group based on the gidnumber
 * @param $gidnumber
 * @return array
 * @throws Exception
 */


function group_get($gidnumber) {
    global $ldap;
    $sr = ldap_search(
        $ldap,
        sprintf("%s,%s", LDAP_GROUPOU, LDAP_BASE),
        "(&(objectclass=posixGroup)(gidnumber=$gidnumber))");
    $result = ldap_get_entries($ldap, $sr);

    if ($result['count']) {
        return cleanUpEntry($result[0]);
    } else {
        throw new Exception(_('Group not found'), 404);
    }
}


function group_list() {

    global $ldap;
    $sr = ldap_search($ldap,
        sprintf("%s,%s", LDAP_GROUPOU, LDAP_BASE),
        "(objectClass=posixGroup)",
        array('cn', 'gidnumber', 'sambasid', 'sambagrouptype', 'displayname')
    );
    ldap_sort($ldap, $sr, "cn");
    $result = ldap_get_entries($ldap, $sr);
    return cleanUpEntries($result);
}


/*
 * User functions
 */


function user_create($data) {

    global $ldap;



    // 3) se não passou UID, gerar próximo UID (get_next_id())

    // 4) verificar se UID não existe (getpwuid())

    // 5) Se passou GID, usar GID informado, senão usar DefaultUserGID

    // 6) Samba RID/GID


    $sambaSID = get_local_sid();

    $attrs = array();

    // Unix Attributes
    $attrs['objectclass'] = array(
        'top', 'person', 'organizationalPerson',
        'inetOrgPerson', 'posixAccount', 'shadowAccount');

    // 1) Verify username regex
    if(empty($data['uid']) || !preg_match('/^[a-z_][a-z0-9_]+$/', $data['uid'])) {
        throw new Exception(_("Invalid username"), 500);
    }

    // 2) Verifiy if this user does not already exist
    $sr = ldap_search(
        $ldap,
        sprintf("%s,%s", LDAP_USEROU, LDAP_BASE),
        "(uid={$data['uid']})");
    $entry = ldap_first_entry($ldap, $sr);

    if($entry) {
        throw new Exception(sprintf(_('User %s already exists'), $data['uid']));
    }

    $attrs['uid'] = $data['uid'];

    $attrs['cn'] = $attrs['uid'];
    $attrs['sn'] = $attrs['uid'];
    $attrs['givenname'] = $attrs['uid'];

    // TODO how to delete attribute ?
    if (!empty($data['gecos']))
        $attrs['gecos'] = tiraAcentos($data['gecos']);


    // TODO allow passing any uidnumber
    $attrs['uidnumber'] = get_next_id('uidnumber');

    // Samba Attributes
    $attrs['objectclass'][] = 'sambaSAMAccount';

    $winmagic = 2147483647;
    $userRid = 2* $attrs['uidnumber'] + 1000;

    $attrs['sambapwdlastset'] = 0;
    $attrs['sambalogontime'] = 0;
    $attrs['sambalogofftime'] = $winmagic;
    $attrs['sambakickofftime'] = $winmagic;
    $attrs['sambapwdcanchange'] = !empty($data['pwdcanchange']) ? 0 : $winmagic;
    $attrs['sambapwdmustchange'] = !empty($data['pwdmustchange']) ? 0 : $winmagic;
    $attrs['displayname'] = $attrs['gecos'];
    $attrs['sambaacctflags'] = $attrs['sambapwdmustchange'] ? '[UX]' : "[U]";
    $attrs['sambasid'] = "$sambaSID-$userRid";


    // dn
    $dn = sprintf("uid=%s,%s, %s", $attrs['uid'], LDAP_USEROU, LDAP_BASE);


    $attrs['homedirectory'] = sprintf(USER_HOME, $attrs['uid']);
    $attrs['loginshell'] = USER_DEFAULTSHELL;

    // check primary group
    $group = group_get($data['gidnumber']);

    if(empty($group['sambasid'])) {
        throw new Exception(sprintf(_('Error: SID not set for unix group %s (%d)'), $group['cn'], $group['gidnumber']), 500);
    }

    $attrs['gidnumber'] = $data['gidnumber'];

    if(!empty($data['passwd'])) {
        $attrs = passwd_attrs($data['passwd'], $attrs);
    }

    // Create the user on LDAP
    $dn = sprintf("uid=%s,%s,%s", $attrs['uid'],LDAP_USEROU,LDAP_BASE);

    if (!@ldap_add($ldap, $dn, $attrs)) {
        print_r($attrs);
        throw new Exception(sprintf(_("LDAP error %s"), ldap_error($ldap)), 500);

    } else {
        // TODO create home dir ?
        return $attrs;
    }


    /*
    $add = $ldap_master->add(
        "uid=$userName,$config{usersdn}",
        attr => [
            'objectclass' => [
                'top',                  'person',
                'organizationalPerson', 'inetOrgPerson',
                'posixAccount',         'shadowAccount'
            ],
            'cn'            => "$userCN",
            'sn'            => "$userSN",
            'givenName'     => "$givenName",
            'uid'           => "$userName",
            'uidNumber'     => "$userUidNumber",
            'gidNumber'     => "$userGidNumber",
            'homeDirectory' => "$userHomeDirectory",
            'loginShell'    => "$config{userLoginShell}",
            'gecos'         => "$config{userGecos}",
            'userPassword'  => "{crypt}x"
        ]
    );

    # samba user info
        my $winmagic         = 2147483647;
        my $valpwdcanchange  = 0;
        my $valpwdmustchange = $winmagic;
        my $valpwdlastset    = 0;
        my $valacctflags     = "[UX]";

        if ( defined( $tmp = $Options{'A'} ) ) {
            if ( $tmp != 0 ) {
                $valpwdcanchange = "0";
            }
            else {
                $valpwdcanchange = "$winmagic";
            }
        }

        if ( defined( $tmp = $Options{'B'} ) ) {
            if ( $tmp != 0 ) {
                $valpwdmustchange = "0";

                # To force a user to change his password:
                # . the attribute sambaAcctFlags must not match the 'X' flag
                $valacctflags = "[U]";
            }
            else {
                $valpwdmustchange = "$winmagic";
            }
        }

        if ( defined( $tmp = $Options{'H'} ) ) {
            $valacctflags = "$tmp";
        }

        my $modify = $ldap_master->modify(
            "uid=$userName,$config{usersdn}",
            changes => [
                add => [ objectClass        => 'sambaSAMAccount' ],
                add => [ sambaPwdLastSet    => "$valpwdlastset" ],
                add => [ sambaLogonTime     => '0' ],
                add => [ sambaLogoffTime    => '2147483647' ],
                add => [ sambaKickoffTime   => '2147483647' ],
                add => [ sambaPwdCanChange  => "$valpwdcanchange" ],
                add => [ sambaPwdMustChange => "$valpwdmustchange" ],
                add => [ displayName        => "$displayName" ],
                add => [ sambaAcctFlags     => "$valacctflags" ],
                add => [ sambaSID           => "$config{SID}-$userRid" ]
            ]
        );


     */

    /**
    # Additional stuff (não importante agora)
    $tmp = defined( $Options{'E'} ) ? $Options{'E'} : $config{userScript};
    my $valscriptpath = &subst_user( $tmp, $userName );

    $tmp = defined( $Options{'C'} ) ? $Options{'C'} : $config{userSmbHome};
    my $valsmbhome = &subst_user( $tmp, $userName );

    my $valhomedrive =
    defined( $Options{'D'} ) ? $Options{'D'} : $config{userHomeDrive};

    # if the letter is given without the ":" symbol, we add it
    $valhomedrive .= ':' if ( $valhomedrive && $valhomedrive !~ /:$/ );

    $tmp = defined( $Options{'F'} ) ? $Options{'F'} : $config{userProfile};
    my $valprofilepath = &subst_user( $tmp, $userName );

    my @adds = ();

    if ($valhomedrive) {
    push( @adds, 'sambaHomeDrive' => $valhomedrive );
    }
    if ($valsmbhome) {
    push( @adds, 'sambaHomePath' => $valsmbhome );
    }

    if ($valprofilepath) {
    push( @adds, 'sambaProfilePath' => $valprofilepath );
    }
    if ($valscriptpath) {
    push( @adds, 'sambaLogonScript' => $valscriptpath );
    }
    if ( !$config{with_smbpasswd} ) {
    push( @adds, 'sambaPrimaryGroupSID' => $userGroupSID );
    push( @adds, 'sambaLMPassword'      => "XXX" );
    push( @adds, 'sambaNTPassword'      => "XXX" );
    }
     */

    /*
    * GROUPS
   if ( $userGidNumber != $config{defaultUserGid} ) {
       group_add_user( $userGidNumber, $userName );
   }

   # adds to supplementary groups
   if ( defined( $grouplist = $Options{'G'} ) ) {
       add_grouplist_user( $grouplist, $userName );
   }
    */


    /*
    # as grouprid we use the value of the sambaSID attribute for
    # group of gidNumber=$userGidNumber
    $group_entry  = read_group_entry_gid($userGidNumber);
    $userGroupSID = $group_entry->get_value('sambaSID');
    unless ($userGroupSID) {
        print "Error: SID not set for unix group $userGidNumber\n";
        print "check if your unix group is mapped to an NT group\n";
        exit(7);
    }
     */

    /*
    # as rid we use 2 * uid + 1000
    $userRid = 2 * $userUidNumber + 1000;
     */

    /*
    # let's test if this SID already exist
    $user_sid = "$config{SID}-$userRid";
    my $test_exist_sid = does_sid_exist( $user_sid, $config{usersdn} );
    if ( $test_exist_sid->count == 1 ) {
        print "User SID already owned by\n";

        # there should not exist more than one entry, but ...
        foreach my $entry ( $test_exist_sid->all_entries ) {
            my $dn = $entry->dn;
            chomp($dn);
            print "$dn\n";
        }
        exit(7);
    }
     */

    // 7) Shell

    // 8) userHomeDirectory

    // 9) Detalhes do Usuário
    # RFC 2256 & RFC 2798
    # sn: family name (option S)             # RFC 2256: family name of a person.
    # givenName: prenom (option N)           # RFC 2256: part of a person's name which is not their surname nor middle name.
    # cn: person's full name                 # RFC 2256: person's full name.
    # displayName: preferably displayed name # RFC 2798: preferred name of a person to be used when displaying entries.

    #givenname is the forename of a person (not family name) => http://en.wikipedia.org/wiki/Given_name
    #surname (or sn) is the family name => http://en.wikipedia.org/wiki/Surname
    # my surname (or sn): Tournier
    # my givenname: Jerome

    # gecos

    // se não tiver displayName, usar givenName + sn


    // senha

    /*
    if ( defined( $Options{'P'} ) ) {
        if ( defined( $tmp = $Options{'B'} ) and $tmp != 0 ) {
        exec "$RealBin/smbldap-passwd -B  \"$userName\"";
    }
        else {
        exec "$RealBin/smbldap-passwd \"$userName\"";
    }
    } */


}


function user_get($uidnumber) {
    global $ldap;
    $sr = ldap_search(
        $ldap,
        sprintf("%s,%s", LDAP_USEROU, LDAP_BASE),
        "(&(objectclass=posixAccount)(uidnumber={$uidnumber}))");
    $result = ldap_get_entries($ldap, $sr);

    if ($result['count']) {
        return cleanUpEntry($result[0]);
    } else {
        throw new Exception(_('User not found'), 404);
    }
}

