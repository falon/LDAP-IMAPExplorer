	<?php

	$opt['onlyldap'] = $_GET["onlyldap"];
        require_once('config.php');
        require_once('ldapfunctions.php');
        require_once('functions.php');
	openlog($syslog['tag'], LOG_PID, $syslog['fac']);
	$user = username();
        $ds = conn_ldap($user, $server, $port, $dnlog,$password);
        if (!$ds) {
                $err = 'Program terminated becasuse it is not possibile to connect to LDAP server.';
                syslog(LOG_ERR, sprintf('%s: Error: %s',$user,$err));
                return FALSE;
        }
        $sr=ldap_read($ds,$_GET["ID"],'objectclass=*');
        $result = ldap_get_entries($ds, $sr);

	$title=$result[0]['cn'][0];
	?>
        <?php
	print "<h2 class=\"modal-header\">Profilo di $title</h2>";
	print printuid($result);
	if (!ldap_close($ds)) exit ("Errore in chiusura LDAP.</p>");
	

	if (!$opt['onlyldap']) { 

		if (!(isset($result[0]['mailhost'][0]))) {
			print "<p>This entry has no mailbox.</p>";
			exit();
		}
		$mailhost = $result[0]['mailhost'][0];
		$mailbox = '*';
				$info = escapeshellcmd('/usr/bin/ssh root@'.$mailhost.' /usr/local/bin/cyr_showuser.pl -u '.$result[0]['uid'][0]);

		$mbox = imap_open('{'.$mailhost.':143/imap/novalidate-cert/readonly/authuser='.$imapAdmin.'}',
			$result[0]['uid'][0], $imapPwd, OP_HALFOPEN)
                     or print ("<p>ERROR: <$imapAdmin> can't connect to < $mailhost >: " . imap_last_error() . 
			"<br>Following results could be ambiguous or wrong.</p>\n");

		$list = imap_list($mbox, '{'.$mailhost.':143/imap/novalidate-cert/readonly}', $mailbox );
		if (is_array($list)) {
		    print '<br><table><thead><tr><th>Folder</th><th>#mail</th><th>Recenti</th><th>Non letti</th></tr></thead><tbody>';
		    foreach ($list as $val) {
			print '<tr><td>'.htmlspecialchars(mb_convert_encoding(mailboxname($val,'proxy'), "UTF-8", "UTF7-IMAP")).'</td>';
		        if ($status=imap_status($mbox, $val, SA_MESSAGES+SA_RECENT+SA_UNSEEN))
				print "<td>". $status->messages    . "</td>".
				      "<td>". $status->recent    . "</td>".
				      "<td>". $status->unseen    . "</td></tr>";
			else print '<td>Denied</td><td>Denied</td><td>Denied</td></tr>';
		    }
		    print '</tbody></table>';
		} else {
		    echo "imap_list failed: " . imap_last_error() . "\n";
		}
		
		imap_close($mbox);

		# Show other info
		exec($info,$out);
		print '<br><table>';
		for ($r=0;$r<4;$r++) {	//only first three lines!
			$keywords = preg_split("/\s{2,}/",$out[$r]);
			$t = count($keywords);
			if ($t>1) print '<tr>';
			for ($k=1;$k<$t;$k++)
				print '<td>'.$keywords[$k].'</td>';
			if ($t>1) print '</tr>';
		}
		print '</table>';
	}
	?>
    <div class="modal-footer">
                <button type="button" class="button" data-dismiss="modal">Close</button>
            </div>                      <!-- /modal-footer -->
