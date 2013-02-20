<?php

include "../_init.php";


route("GET /?", "user_list");
route("GET /([a-z_.0-9]+)/?", "user_get");
route("PUT /([a-z_.0-9]+)/?", "user_update");
route("POST /?", 'user_create');


function user_create() {

    global $ldap;

    $data = parse_post_body();

    $attrs = array();

    // Básico da conta

    $attrs['objectclass'][] = 'top';
    $attrs['objectclass'][] = 'person';
    $attrs['objectclass'][] = 'organizationalPerson';
    $attrs['objectclass'][] = 'inetOrgPerson';
    $attrs['objectclass'][] = 'account';
    $attrs['objectclass'][] = 'posixAccount';
    $attrs['objectclass'][] = 'shadowAccount';





    #TODO como deixar atributo em branco ?
    if (!empty($data['gecos']))
        $attrs['gecos'] = tiraAcentos($data['gecos']);
    $attrs['uid'] = tiraAcentos(trim(strtolower($data['uid'])));
    $attrs['cn'] = $attrs['uid'];
    $attrs['homedirectory'] = sprintf('USER_HOME', $attrs['uid']);
    $attrs['loginshell'] = USER_DEFAULTSHELL;
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

    if($result['count']) {
        return cleanUpEntry($result[0]);
    }
    else {
        respond(_('User not found'), 404);
    }
}

function user_list()
{
    global $ldap;

    $filter = empty($_GET['filter']) ? '*' : "*{$_GET['filter']}*";

    $fields = empty($_GET['fields']) ? array('cn', 'sn', 'givenname', 'uid', 'uidnumber','gidnumber', 'displayname', 'gecos') : (is_array($_GET['fields']) ? $_GET['fields'] : array($_GET['fields']));

    $sr = ldap_search($ldap,
        sprintf("%s,%s", LDAP_USEROU, LDAP_BASE),
        "(&(objectClass=posixAccount)(|(uid={$filter})(gecos={$filter})))",
        $fields
    );

    if(in_array('uid', $fields)) {
        ldap_sort($ldap, $sr, "uid");
    }
    $result = ldap_get_entries($ldap, $sr);
    ldap_free_result($sr);

    return cleanUpEntries($result);
}

function user_update($uidnumber)
{
    global $ldap;

    $user = user_get($uidnumber);

    if (!$user) {
        throw new Exception('User not found', 404);
    }

    $data = parse_post_body();

    $attrs = array();
    $dn = sprintf("uid=%s,%s,%s", $user['uid'], LDAP_USEROU, LDAP_BASE);

    if(!empty($_POST['gecos']))
        $attrs['gecos'] = tiraAcentos($data['gecos']);

    // Grupo mudou ?
//    if($_POST['gidnumber_orig'] != $_POST['gidnumber']) {
//        $attrs['gidnumber'] = $_POST['gidnumber'];
//        (2 * $_POST['gidnumber'] + 1001);
//        @exec("{$conf['user']['homedirscript']} group ".escapeshellarg($_POST['gidnumber'])." ".escapeshellarg($_POST['homedirectory'])." 2>&1", $out, $res);
//        if($res) {
//            #TODO mail() aqui
//            infomsg("Erro acertando permiss�es");
//        }
//    }

//    if(!($sambaSID = sambaSID()))
//        exit("erro verificando sambasid");

//    $attrs['sambaprimarygroupsid'] = "{$sambaSID}-{$_POST['sambaprimarygroupsid']}";


    $user = array_merge($user, $attrs);

    if(!empty($data['passwd'])) {
        $attrs = passwd_attrs($_POST['passwd'], $attrs);
    }

    if(ldap_modify($ldap, $dn, $attrs))
        return $user;
    else
        throw new Exception(_("Error updating %s: %s", $dn ,ldap_error($ldap)), 500);











    /*

    # Let's connect to the directory first
    my $winmagic         = 2147483647;
    my $valpwdcanchange  = 0;
    my $valpwdmustchange = $winmagic;
    my $valpwdlastset    = 0;
    my $valacctflags     = "[UX]";
    my $user_entry       = read_user_entry($user);
    my $uidNumber        = $user_entry->get_value('uidNumber');
    my $userRid          = 2 * $uidNumber + 1000;

    # apply changes
    my $modify = $ldap_master->modify(
        "$dn",
        changes => [
            add => [ objectClass        => 'sambaSAMAccount' ],
            add => [ sambaPwdLastSet    => "$valpwdlastset" ],
            add => [ sambaLogonTime     => '0' ],
            add => [ sambaLogoffTime    => '2147483647' ],
            add => [ sambaKickoffTime   => '2147483647' ],
            add => [ sambaPwdCanChange  => "$valpwdcanchange" ],
            add => [ sambaPwdMustChange => "$valpwdmustchange" ],
            add => [ sambaSID           => "$config{SID}-$userRid" ],
            add => [ sambaAcctFlags     => "$valacctflags" ],
        ]
    );
     */

    // GROUPS
    /*

    # when adding samba attributes, try to set samba primary group as well.
    my $group_entry =
      read_group_entry_gid( $user_entry->get_value('gidNumber') );

    # override group if new group id sould be set with this call as well
    $group_entry = read_group_entry_gid( $Options{'g'} ) if $Options{'g'};
    my $userGroupSID = $group_entry->get_value('sambaSID');
    if ($userGroupSID) {
        my $modify_grpSID = $ldap_master->modify( "$dn",
            changes => [ add => [ sambaPrimaryGroupSID => "$userGroupSID" ], ]
        );

        if ( $modify_grpSID->code ) {
            warn "failed to modify entry: ", $modify_grpSID->error;
            exit 1;
        }
        else {    # no reason to abort imho
        print
"Warning: sambaPrimaryGroupSID could not be set beacuse group of user $user is not a mapped Domain group!\n",
"To get a list of groups mapped to Domain groups, use \"net groupmap list\" on a Domain member machine.\n";
    }
    }
     */


    /**
    CHANGE UID
     * # Process options
    my $changed_uid;
    my $_userUidNumber;
    my $_userRid;

    if ( defined( $tmp = $Options{'u'} ) ) {
    if ( !defined( $Options{'o'} ) ) {
    $nscd_status = system "/etc/rc.d/init.d/nscd status >/dev/null 2>&1";
    if ( $nscd_status == 0 ) {
    system "/usr/sbin/nscd -i passwd > /dev/null 2>&1";
    system "/usr/sbin/nscd -i group > /dev/null 2>&1";
    }
    if ( getpwuid($tmp) ) {
    print "$0: uid number $tmp exists\n";
    exit(6);
    }
    }

    push( @mods, 'uidNumber', $tmp );
    $_userUidNumber = $tmp;
    if ($samba) {

    # as rid we use 2 * uid + 1000
    my $_userRid = 2 * $_userUidNumber + 1000;
    if ( defined( $Options{'x'} ) ) {
    $_userRid = sprint( "%x", $_userRid );
    }
    push( @mods, 'sambaSID', $config{SID} . '-' . $_userRid );
    }
    $changed_uid = 1;
    }
     */

    /*
    my $changed_gid;
    my $_userGidNumber;
    my $_userGroupSID;
    if ( defined( $tmp = $Options{'g'} ) ) {
        $_userGidNumber = parse_group($tmp);
        if ( $_userGidNumber < 0 ) {
            print "$0: group $tmp doesn't exist\n";
            exit(6);
        }
        push( @mods, 'gidNumber', $_userGidNumber );
        if ($samba) {

            # as grouprid we use the sambaSID attribute's value of the group
            my $group_entry   = read_group_entry_gid($_userGidNumber);
            my $_userGroupSID = $group_entry->get_value('sambaSID');
            unless ($_userGroupSID) {
                print
    "Error: sambaPrimaryGroupSid could not be set (sambaSID for group $_userGidNumber does not exist)\n";
                exit(7);
            }
            push( @mods, 'sambaPrimaryGroupSid', $_userGroupSID );
        }
        $changed_gid = 1;
    }
     */

    /**
    gecos
    if ( defined( $tmp = $Options{'s'} ) ) {
    push( @mods, 'loginShell' => $tmp );
    }

     */


    /**
    if ( defined( $tmp = $Options{'c'} ) ) {
    push(
    @mods,
    'gecos'       => $tmp,
    'description' => $tmp
    );
    }

     */

    /**

    if ( defined( $tmp = $Options{'d'} ) ) {
    push( @mods, 'homeDirectory' => $tmp );
    }
     */


    /**
    # RFC 2256 & RFC 2798
    # sn: family name (option S)             # RFC 2256: family name of a person.
    # givenName: prenom (option N)           # RFC 2256: part of a person's name which is not their surname nor middle name.
    # cn: person's full name                 # RFC 2256: person's full name.
    # displayName: perferably displayed name # RFC 2798: preferred name of a person to be used when displaying entries.

    #givenname is the forename of a person (not famiy name) => http://en.wikipedia.org/wiki/Given_name
    #surname (or sn) is the familiy name => http://en.wikipedia.org/wiki/Surname
    # my surname (or sn): Tournier
    # my givenname: Jerome

    if ( defined( $tmp = $Options{'N'} ) ) {
    push( @mods, 'givenName' => utf8Encode($characterSet,$tmp) );
    }

    if ( defined( $tmp = $Options{'S'} ) ) {
    push( @mods, 'sn' => utf8Encode($characterSet,$tmp) );
    }

    my $cn;
    if ( $Options{'N'} or $Options{'S'} or $Options{'a'} ) {
    $Options{'N'} = $user_entry->get_value('givenName') unless $Options{'N'};
    $Options{'S'} = $user_entry->get_value('sn')        unless $Options{'S'};

    # if givenName eq sn eq username (default of smbldap-useradd), cn and displayName would
    # be "username username". So we just append surname if its not default
    # (there may be the very very special case of an user where those three values _are_ equal)
    $cn = "$Options{'N'}";
    $cn .= " " . $Options{'S'}
    unless ( $Options{'S'} eq $Options{'N'} and $Options{'N'} eq $user );
    my $push_val = utf8Encode($characterSet,$cn);
    push( @mods, 'cn' => $push_val );

    # set displayName for Samba account
    if ($samba) {
    push( @mods, 'displayName' => $push_val );
    }
    }
     */

    /**
    if ( defined $Options{'e'} ) {
    if ( !defined $Options{'shadowExpire'} ) {
    $Options{'shadowExpire'} = $Options{'e'};
    }
    if ( !defined $Options{'sambaExpire'} ) {
    $Options{'sambaExpire'} = $Options{'e'};
    }
    }
     */


    /**
    # Shadow password parameters
    my $localtime = time() / 86400;
    if ( defined $Options{'shadowExpire'} ) {

    # Unix expiration password
    my $tmp = $Options{'shadowExpire'};
    chomp($tmp);

    #    my $expire=`date --date='$tmp' +%s`;
    #    chomp($expire);
    #    my $shadowExpire=int($expire/86400)+1;
    # date syntax asked: YYYY-MM-DD

    $tmp = parse_date_to_unix_days($tmp);
    if ( $tmp != -1 ) {
    push( @mods, 'shadowExpire', $tmp );
    }
    else {
    print "Invalid format for '--shadowExpire' option.\n";
    }
    }

    if ( defined $Options{'shadowWarning'} ) {
    push( @mods, 'shadowWarning', $Options{shadowWarning} );
    }

    if ( defined $Options{'shadowMax'} ) {
    push( @mods, 'shadowMax', $Options{shadowMax} );
    }

    if ( defined $Options{'shadowMin'} ) {
    push( @mods, 'shadowMin', $Options{shadowMin} );
    }

    if ( defined $Options{'shadowInactive'} ) {
    push( @mods, 'shadowInactive', $Options{shadowInactive} );
    }

    if ( defined $Options{'L'} ) {

    # lock shadow account
    $tmp = $user_entry->get_value('userPassword');
    if ( !( $tmp =~ /!/ ) ) {
    $tmp =~ s/}/}!/;
    }
    push( @mods, 'userPassword' => $tmp );
    }

    if ( defined $Options{'U'} ) {

    # unlock shadow account
    $tmp = $user_entry->get_value('userPassword');
    if ( $tmp =~ /!/ ) {
    $tmp =~ s/}!/}/;
    }
    push( @mods, 'userPassword' => $tmp );
    }

     */
}


function get_next_id()
{
    // TODO not implemented
    throw new Exception('Not Implemented');
}


/*
 * Encripta senha e adiciona os campos necessários na array $attrs
 */
function passwd_attrs($passwd, $attrs = array())
{
    $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',4)),0,4);

    # TODO shadowLastChange, shadowMin, shadowMax, shadowWarning ?

    // Unix
    $attrs['userpassword'] = "{crypt}" . crypt($passwd, '$1$' . substr(session_id(), 0, 8));

    $smbhash = new smbHash();
    $attrs['sambalmpassword'] = $smbhash->lmhash($passwd);
    $attrs['sambantpassword'] = $smbhash->nthash($passwd);
    $attrs['sambapwdlastset'] = date('U');

    return ($attrs);

}
