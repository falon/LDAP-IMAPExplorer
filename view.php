	<?php

	require 'vendor/autoload.php';
	use Horde\Imap_Client;

	$opt['onlyldap'] = $_GET["onlyldap"];
	$ini = parse_ini_file('LDAP-IMAPExplorer.conf', true);
	$baseDN= $ini['ldap']['baseDN'];
	$server= $ini['ldap']['server'];
	$port =  $ini['ldap']['port'];
	$dnlog = $ini['ldap']['bind'];
	$password = $ini['ldap']['password'];
	$imapAdmin = $ini['imap']['admin'];
	$imapPwd   = $ini['imap']['password'];
	$syslog    = $ini['syslog'];

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

		$special_use = array(   strtolower(\Horde_Imap_Client::SPECIALUSE_ALL),
					strtolower(\Horde_Imap_Client::SPECIALUSE_ARCHIVE),
					strtolower(\Horde_Imap_Client::SPECIALUSE_DRAFTS),
					strtolower(\Horde_Imap_Client::SPECIALUSE_FLAGGED),
					strtolower(\Horde_Imap_Client::SPECIALUSE_JUNK),
					strtolower(\Horde_Imap_Client::SPECIALUSE_SENT),
					strtolower(\Horde_Imap_Client::SPECIALUSE_TRASH)
				);

		if (!(isset($result[0]['mailhost'][0]))) {
			print "<p>This entry has no mailbox.</p>";
			exit();
		}
		$mailhost = $result[0]['mailhost'][0];
		list($uid, $dom) = explode('@',$result[0]['uid'][0]);
		$mailbox = array('INBOX','INBOX/*');

		$tstart = microtime(TRUE);
		$mbox=imapopen($result[0]['uid'][0],$imapPwd,$mailhost,$imapAdmin);
		if ($mbox) {
			try {
				$list = $mbox->listMailboxes($mailbox,
					\Horde_Imap_Client::MBOX_ALL,
					array(
						'status' => 63,
						'special_use' => true,
						'attributes' => true,
						'sort' => true,
						'sort_delimiter' => '/'
					)
				);
			}
			catch (Horde_Imap_Client_Exception $e) {
				syslog(LOG_INFO,  "IMAP Error in LISTING commands: $e");
				exit ('Some errors happen. See at log.');
			}

			print '<br><table><thead><tr><th title="Move on folder name to show the ACL">Folder and ACL</th><th>#mail</th><th>Recent</th><th>Unseen</th><th title="Special Use flag as RFC6154">Special</th><th>SharedSeen</th><th>Expire</th><th>Size (MB)</th></tr></thead><tbody>';
			if (is_array($list)) {
				$tot['messages'] = 0;
                                $tot['recent'] = 0;
                                $tot['unseen'] = 0;
                                $tot['size'] = 0;
				foreach ($list as $val) {
					$acl = $mbox ->getACL($val['mailbox']);
					printf('<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td>',
						printACL($val['mailbox'], $acl),
						$val['status']['messages'],
						$val['status']['recent'],
						$val['status']['unseen']
					);
					$tot['messages'] += $val['status']['messages'];
					$tot['recent'] += $val['status']['recent'];
					$tot['unseen'] += $val['status']['unseen'];

					$specialflags = '';
					foreach ($val['attributes'] as $special) {
						if ( in_array(strtolower($special), $special_use) )
							$specialflags .= " $special";
					}

					printf('<td>%s</td>', $specialflags);

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
						printf('<td>%s</td><td>%s</td><td>%.1f</td></tr>',
							$metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/sharedseen'],
							(isset($metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/expire'])) ?
								$metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/expire'] :
								'-',
							formatGB($metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/size'])
						);
						$tot['size'] += $metaValues["$mboxName"]['/shared/vendor/cmu/cyrus-imapd/size'];
					}
				}
			}
			else print '<tr><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td><td>Denied</td></tr>';
				
			$mbox->close();
			# IMAP operations time in microsec
			$elaps = microtime(TRUE)- $tstart;
		}
		printf ('</tbody><tfoot><tr><td>TOT</td><td>%d</td><td>%d</td><td>%d</td><td></td><td></td><td></td><td>%.1f</td></tr><tr><th colspan="7">Elapsed IMAP time: %.2f s</th></tr></tfoot></table>',
			$tot['messages'],
			$tot['recent'],
			$tot['unseen'],
			formatGB($tot['size']),
			$elaps
		);
	}
		
	?>
    	<div class="modal-footer">
                <button type="button" class="button" data-dismiss="modal">Close</button>
        </div>                      <!-- /modal-footer -->
