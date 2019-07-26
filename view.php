	<?php

	require 'vendor/autoload.php';
	use Horde\Imap_Client;

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
		list($uid, $dom) = explode('@',$result[0]['uid'][0]);
		$mailbox = array('INBOX','INBOX/*');

		$mbox=imapopen($result[0]['uid'][0],$imapPwd,$mailhost,$imapAdmin);
		if ($mbox) {
			try {
				$list = $mbox->listMailboxes($mailbox,
					\Horde_Imap_Client::MBOX_ALL,
					array(
					      	'status' => 50,
						'sort' => true,
						'sort_delimiter' => '/'
					)
				);
			}
			catch (Horde_Imap_Client_Exception $e) {
				syslog(LOG_INFO,  "IMAP Error in LISTING commands: $e");
				exit ('Some errors happen. See at log.');
			}

			print '<br><table><thead><tr><th title="Move on folder name to show the ACL">Folder and ACL</th><th>#mail</th><th>Recent</th><th>Unseen</th><th>SharedSeen</th><th>Expire</th><th>Size (MB)</th></tr></thead><tbody>';
			if (is_array($list)) {
				foreach ($list as $val) {
					$acl = $mbox ->getACL($val['mailbox']);
					printf('<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td>',
						printACL($val['mailbox'], $acl),
						$val['status']['messages'],
						$val['status']['recent'],
						$val['status']['unseen']
					);
					try {
						$meta = array('/shared/vendor/cmu/cyrus-imapd/expire',
							'/shared/vendor/cmu/cyrus-imapd/sharedseen',
							'/shared/vendor/cmu/cyrus-imapd/size' );
						$metaValues = $mbox->getMetadata($val['mailbox'], $meta);
					}
					catch (Horde_Imap_Client_Exception $e) {
						syslog(LOG_INFO,  "IMAP Error in METADATA query: $e");
						exit ('Some errors happen. See at log.');
		                        }

					if (is_array($metaValues)) {
						$mboxName = \Horde_Imap_Client_Mailbox::get($val['mailbox'], ENT_QUOTES, 'UTF-8');
						printf('<td>%s</td><td>%s</td><td>%d</td></tr>',
							$metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/sharedseen'],
							(isset($metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/expire'])) ?
								$metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/expire'] :
								'-',
							formatGB($metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/size'])
						);
					}
				}
			}
			else print '<tr><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td></tr>';
				
			$mbox->close();
		    }
		    print '</tbody></table>';
	}
		
	?>
    	<div class="modal-footer">
                <button type="button" class="button" data-dismiss="modal">Close</button>
        </div>                      <!-- /modal-footer -->
