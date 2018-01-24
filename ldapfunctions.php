<?php

function printuid ($info) {

/* stampa gli attributi e torna numero oggetti o False in caso d'errore*/
	if (!(isset($info["count"]))) return "<pre>Nessun attributo da visualizzare.</pre>";
	if ($info["count"] == 0)
		return '<p>No attributes found.</p>';
	for ($i=0; $i<$info["count"]; $i++) {
		$return = <<<END
			<table border="0" cellpadding="1" cellspacing="2" style="border: none; margin: 0">
			<caption>dn: {$info[$i]["dn"]}</caption>
			<thead>
			<tr>
				<th nowrap>Attributo</th>
				<th nowrap>Valori</th>
			</tr>
			</thead>
			<tbody>
END;
		for ($ii=0; $ii<$info[$i]["count"]; $ii++) {	#cicla negli attributi di questo dn
			$attrib= $info[$i][$ii];
			$tag=NULL;
			$return .= "<tr><td>$attrib</td><td>"; #nome attributo
			for ($iii=0;$iii<$info[$i]["$attrib"]["count"];$iii++) {
				$return .= '<div id="attr">';
				$return .=  nl2br($info[$i]["$attrib"][$iii]); #valore attributo
				$return .=  '</div>';
			}
			$return .= "</td>";
		}
		$return .= "</tbody><tfoot><tr></tr></tfoot></table>";
	}
	return $return;
}

function conn_ldap($username,$host,$port,$user,$pwd) {
        $ldapconn = ldap_connect($host, $port);
        ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 5);
        if ($ldapconn) {
                // binding to ldap server
                syslog(LOG_INFO,  "$username: Info: LDAP: Successfully connected to $host:$port");
                $ldapbind = ldap_bind($ldapconn, $user, $pwd);
                // verify binding
                if ($ldapbind) {
                        syslog(LOG_INFO,  "$username: Info: LDAP: Successfully BIND as <".$user.'>.');
                        return $ldapconn;
                }
                else {
                        $err = 'LDAP: Error trying to bind as <'.$user.'>: '.ldap_error($ldapconn);
                        syslog(LOG_ERR, "$username: Error: $err.");
                        ldap_unbind($ldapconn);
                        return FALSE;
                }
        }
        else {
                $err = 'LDAP: Could not connect to LDAP server '.$host.':'.$port;
                syslog(LOG_ERR, $username.": Error: $err.");
                return FALSE;
        }
}



function ldapsearch($username,$conn,$dn,$print,$key_ldap,$justthese,$order) {
	/* Effettua una ricerca LDAP e se voluto stampa gli attributi; ritorna vettore risultato */

        $filter="$key_ldap";
	$info = array();
	if ($justthese =="") unset ($justthese);
        # $justthese is like  array ( "ou", "o", "uid", "cn", "mail", "mailalternateaddress");
        if (isset($justthese)) {
		if (!($sr=ldap_search($conn, $dn, $filter,$justthese))) {
			$err = sprintf('Query <%s> failed. Base dn: <%s>. Error: <%s>.',
					$key_ldap, $dn, ldap_error($conn));
			syslog(LOG_ERR, "$username: $err.");
			return $info;
		}
	}
	else if (!($sr=ldap_search($conn, $dn, $filter))) {
		$err = sprintf('Query <%s> failed. Base dn: <%s>. Error: <%s>.',
                                        $key_ldap, $dn, ldap_error($conn));
                        syslog(LOG_ERR, "$username: $err.");
                        return $info;
	}

	if (!empty($order)) ldap_sort($conn, $sr, $order);
        $info = ldap_get_entries($conn, $sr);

	if ($print)
		print printuid($info);
	return $info;
}


function fetchattrib ($ds,$basedn,$attrib) {

        $justthese = array("$attrib");
        $sr=ldap_list($ds, $basedn, "$attrib=*", $justthese);
        ldap_sort($ds,$sr,"$attrib");
        $info = ldap_get_entries($ds, $sr);
        $return=NULL;
        for ($i=0; $i<$info["count"]; $i++) 
        	for ($j=0; $j<$info[$i]["$attrib"]["count"]; $j++)
                	$return[]=$info[$i]["$attrib"][$j];
        return $return;
}

?>
