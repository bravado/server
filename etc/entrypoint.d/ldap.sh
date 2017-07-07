#!/usr/bin/env bash

if [ ! -d /var/lib/ldap/$OPENLDAP_DATABASE ]; then
  mkdir /var/lib/ldap/$OPENLDAP_DATABASE
fi

chown -R openldap:openldap /var/lib/ldap/$OPENLDAP_DATABASE

if [ ! -f /var/lib/ldap/slapd.conf ]; then

  if [ -z "$OPENLDAP_SUFFIX" ]; then
  	OPENLDAP_SUFFIX=''
		for i in ${OPENLDAP_DATABASE//./$'\n'}; do
			if [ ! -z "$OPENLDAP_SUFFIX" ]; then

				OPENLDAP_SUFFIX="$OPENLDAP_SUFFIX,";
			fi

			OPENLDAP_SUFFIX="${OPENLDAP_SUFFIX}dc=$i";
		done;
	fi

	if [ -z "$OPENLDAP_ROOT_PASSWORD" ]; then
		OPENLDAP_ROOT_PASSWORD=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c${1:-64};)

		echo "[INFO] Generated password $OPENLDAP_ROOT_PASSWORD for cn=root,$OPENLDAP_SUFFIX";
	fi

	OPENLDAP_ROOT_PASSWORD=$(slappasswd -s $OPENLDAP_ROOT_PASSWORD)

	sed -e "s;\$OPENLDAP_DATABASE;$OPENLDAP_DATABASE;g" \
      -e "s;\$OPENLDAP_SUFFIX;$OPENLDAP_SUFFIX;g" \
      -e "s;\$OPENLDAP_ROOT_PASSWORD;$OPENLDAP_ROOT_PASSWORD;g" \
      /etc/ldap/slapd.conf > /var/lib/ldap/slapd.conf
fi
