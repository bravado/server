# Bravado Server

Administration panel for Enterprise Servers

## server.yml
  * LDAP Users and Groups
  * Management Interface
  * Collectd
  * OAuth
  * Join Network Service (first key exchange)
  
### server-cloud.yml
  Includes server.yml plus
  
  * Apache and PHP
    
  * MySQL
  * CouchDB
  * XMPP Server
  * Redis
  * n2n
  * openvpn
  * monit
  * osquery
  * s3ql
  * syncthing

nginx is used to handle public traffic, giving us extreme flexibility
when exposing our services

## server-workgroup.yml

  * Samba 4 Users
  * Primary Domain Controller
  * Proxy
  
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
