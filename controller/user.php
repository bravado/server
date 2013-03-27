<?php

include "../_init.php";


route("GET /?", "user_list");
route("GET /([a-z_.0-9]+)/?", "user_get");
route("PUT /([a-z_.0-9]+)/?", "user_update");
route("POST /?", 'handle_user_create');

function handle_user_create() {

    $data = parse_post_body();

    return user_create($data);
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

    if(!empty($data['gecos']))
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
        $attrs = passwd_attrs($data['passwd'], $attrs);
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


/*
 * Encripta senha e adiciona os campos necessários na array $attrs
 */
function passwd_attrs($passwd, $attrs = array())
{
    $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',4)),0,4);

    # TODO shadowLastChange, shadowMin, shadowMax, shadowWarning ?

    // Unix
    $attrs['userpassword'] = '{SSHA}' . base64_encode(sha1( $passwd.$salt, TRUE ). $salt);

    $smbhash = new smbHash();
    $attrs['sambalmpassword'] = $smbhash->lmhash($passwd);
    $attrs['sambantpassword'] = $smbhash->nthash($passwd);
    $attrs['sambapwdlastset'] = date('U');

    return $attrs;

}
