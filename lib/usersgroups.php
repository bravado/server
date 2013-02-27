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
 * @param string $attribute - uidnumber, gidnumber, sambanextrid, sambanextuserrid
 * @return mixed the next ID
 * @throws Exception if any error happens
 */
function get_next_id($attribute = 'uidnumber') {
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

/*
sub get_next_id($$) {
    my $ldap_base_dn = shift;
    my $attribute    = shift;
    my $tries        = 0;
    my $found        = 0;
    my $next_uid_mesg;
    my $nextuid;
    if ( $ldap_base_dn =~ m/$config{usersdn}/i ) {

        # when adding a new user, we'll check if the uidNumber available is not
 # already used for a computer's account
        $ldap_base_dn = $config{suffix};
    }
    do {
        $next_uid_mesg = $ldap->search(
            base   => $config{sambaUnixIdPooldn},
            filter => "(objectClass=sambaUnixIdPool)",
            scope  => "base"
        );
$next_uid_mesg->code
          && die "Error looking for next uid in "
          . $config{sambaUnixIdPooldn} . ":"
          . $next_uid_mesg->error;
        if ( $next_uid_mesg->count != 1 ) {
            die "Could not find base dn, to get next $attribute";
        }
        my $entry = $next_uid_mesg->entry(0);

        $nextuid = $entry->get_value($attribute);
my $modify =
          $ldap->modify( "$config{sambaUnixIdPooldn}",
            changes => [ replace => [ $attribute => $nextuid + 1 ] ] );
        $modify->code && die "Error: ", $modify->error;

      # let's check if the id found is really free (in ou=Groups or ou=Users)...
        my $check_uid_mesg = $ldap->search(
            base   => $ldap_base_dn,
            filter => "($attribute=$nextuid)",
        );
        $check_uid_mesg->code
          && die "Cannot confirm $attribute $nextuid is free";
if ( $check_uid_mesg->count == 0 ) {

   # now, look if the id or gid is not already used in /etc/passwd or /etc/group
            if ($attribute =~ /^uid/i && !getpwuid($nextuid) ||
                $attribute =~ /^gid/i && !getgrgid($nextuid) ) {
                $found = 1;
                return $nextuid;
            }
        }
        $tries++;
        print
"Cannot confirm $attribute $nextuid is free: checking for the next one\n";
    } while ( $found != 1 );
    die "Could not allocate $attribute!";
}

*/


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

function user_create($data) {

    global $ldap;

    $attrs = array();

    // Unix Account
    $attrs['objectclass'] = array('top', 'person', 'organizationalPerson',
        'inetOrgPerson', 'posixAccount', 'shadowAccount');

    $attrs['cn'] = $attrs['uid'];
    $attrs['sn'] = '';
    $attrs['givenname'] = '';
    $attrs['uid'] = tiraAcentos(trim(strtolower($data['uid'])));

    // Samba
    $sambaSID = getLocalSID();

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


    #TODO como deixar atributo em branco ?
    if (!empty($data['gecos']))
        $attrs['gecos'] = tiraAcentos($data['gecos']);


    $attrs['homedirectory'] = sprintf(USER_HOME, $attrs['uid']);
    $attrs['loginshell'] = USER_DEFAULTSHELL;

    // check group
    $group = group_get($data['gidnumber']);


    $attrs['gidnumber'] = $data['gidnumber'];

    // Gerar uidNumber
    $sr = ldap_search(
        $ldap,
        LDAP_USEROU . ',' . LDAP_USERBASE,
        "objectclass=posixAccount", array("uidnumber"));
    $uids = ldap_get_entries($ldap, $sr);

    $attrs['uidnumber'] = $conf['user']['firstUid'];
    // Pega o maior uid cadastrado
    for ($i = 0; $i < $uids['count']; $i++)
        if ($uids[$i]['uidnumber'][0] > $attrs['uidnumber'])
            $attrs['uidnumber'] = $uids[$i]['uidnumber'][0];
    // Soma 1
    $attrs['uidnumber']++;

    # TODO Verificar se n�o estourou o uidNumber (65535)

    // Samba
    if ($conf['smb']) {
        # TODO mail() aqui
        if (!($sambaSID = sambaSID()))
            exit("Erro verificando sambaSID");

        $attrs['objectclass'][] = 'sambaSamAccount';
        $attrs['sambaLogonTime'] = "2147483647";
        $attrs['sambaLogoffTime'] = "2147483647";
        $attrs['sambaKickoffTime'] = "2147483647";
        $attrs['sambaPwdCanChange'] = "0";
        $attrs['sambaPwdMustChange'] = "2147483647";
        $attrs['sambaAcctFlags'] = "[UXP       ]";
        // sambaSID = 2*uidNumber + 1000
        // sambaPrimaryGroupSID = 2 * gidNumber + 1000 + 1
        $attrs['sambaSID'] = "{$sambaSID}-" . (2 * $attrs['uidnumber'] + 1000);
        $attrs ['sambaPrimaryGroupSID'] = "{$sambaSID}-{$_POST['sambaprimarygroupsid']}";
    }

    // Gerar senha ?
    if (empty($_POST['passwd']))
        $_POST['passwd'] = genpasswd();

    $attrs = passwd_attrs($_POST['passwd'], $attrs);

    // Criar usu�rio no ldap
    $dn = "uid={$attrs['uid']},{$conf['ldap']['userOu']},{$conf['ldap']['base']}";
    if (!@ldap_add($ds, $dn, $attrs)) {
        switch (ldap_errno($ds)) {
            case 68:
                infomsg("Nome de usu�rio j� existe!");
                break;
            default:
                infomsg($dn . " " . ldap_error($ds));
                echo "<pre>";
                print_r($attrs);
                echo "</pre>";
                break;
        }
    } else {
        # TODO Melhorar esse programa
        // Criar homeDirectory (programa em c)
        @exec("{$conf['user']['homedirscript']} add " . escapeshellarg($attrs['uid']) . " " . escapeshellarg($attrs['gidnumber']) . " 2>&1", $out, $res);
        if ($res) {
            infomsg("N�o consegui criar diret�rio do usu�rio");
            if (!@ldap_delete($ds, $dn)) {
                # TODO Colocar mail() aqui
                infomsg("N�o consegui apagar o usu�rio!\\nLembre-se ele *n�o possui um diret�rio inicial*\\nContate o suporte!");
            }
        } else {
            mail("{$attrs['uid']}@colegiosantana.net", "Conta criada", "Conta criada");
            infomsg("Criado <b>{$attrs['uid']}</b> com senha <b>{$_POST['passwd']}</b>");
        }
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


    // 1) verificar se username é ok (regex)

    // 2) verificar se usuário não existe no ldap

    // 3) se não passou UID, gerar próximo UID (get_next_id())

    // 4) verificar se UID não existe (getpwuid())

    // 5) Se passou GID, usar GID informado, senão usar DefaultUserGID

    // 6) Samba RID/GID
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
        respond(_('User not found'), 404);
    }
}

