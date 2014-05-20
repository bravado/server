# Bravado Server

Administration panel for Enterprise Servers

## Modules

  * DNSMasq
  * Users/Groups
  * Proxy

### Users and Groups
 Manages Samba/LDAP Accounts, compatible with smbldap-tools

## Install
 Copy server-config.sample.php to ../server-config.php, edit file

# TODO

Doc
  * How to install samba, smbldap-tools properly

Fixes
  * USER_HOME parser, allow complex paths like /home/$user[0]/$user

Tests

  * create user
  * update user
  * update user password
  * delete user
  * create group
  * update group
  * delete group
