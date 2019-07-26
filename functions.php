<?php
# AUTHOR:       Marco Faverof
# DATE:         23-01-2003
# PROGRAM:	QuotaMailExplorer
# USAGE:	consultare index.php da browser
# PURPOSE:	monitorare lo stato degli utenti definiti su un server IMAP.
#
# FILES:        
#
# NOTES:	
#
# HISTORY:
# Restructured in 2018
#

ini_set('error_log', 'syslog');


function clientIP($username) {
	$ip =	getenv('HTTP_CLIENT_IP')?:
		getenv('HTTP_X_FORWARDED_FOR')?:
		getenv('HTTP_X_FORWARDED')?:
		getenv('HTTP_FORWARDED_FOR')?:
		getenv('HTTP_FORWARDED')?:
		getenv('REMOTE_ADDR');
	syslog(LOG_INFO, "$username: Info: Connection coming from IP <$ip>.");
	return $ip;
}

function username() {
        if (isset ($_SERVER['REMOTE_USER'])) $user = $_SERVER['REMOTE_USER'];
                else if (isset ($_SERVER['USER'])) $user = $_SERVER['USER'];
                else if ( isset($_SERVER['PHP_AUTH_USER']) ) $user = $_SERVER['PHP_AUTH_USER'];
                else {
                        syslog(LOG_ALERT, "No user given by connection from {$_SERVER['REMOTE_ADDR']}. Exiting");
                        exit(0);
                }
        return $user;
}


function search_uid ($userlog,$dn,$ldapattr,$server,$port,$login,$pas,&$opt) {
	// ldap connect
	$ds = conn_ldap($userlog, $server, $port, $login,$pas);
	if (!$ds) {
        	$err = 'Program terminated becasuse it is not possibile to connect to LDAP server.';
        	syslog(LOG_ERR, sprintf('%s: Error: %s',$userlog,$err));
        	return FALSE;
	}

	if ($opt['onlyldap']) {
		$search = '(&(objectclass=*)';
		$retattr = array_merge(array('uid','mail','objectclass'),$opt['retattr']);
	}
	else {
		$search = '(&(objectclass=mailrecipient)(mailhost=*)';
		$retattr = array_merge(array('uid','mail','mailhost','objectclass'),$opt['retattr']);
	}
	switch ($opt['stype']) {
		case 'OR':
			$search .= '(|'; 		//OR search type
			break;
		case 'AND':
			$search .= '(&';		//AND search type
			break;
		case 'UDEF':
			$search = $opt['query'];
	}
	if ($opt['stype']!='UDEF') {
		foreach (array_keys($ldapattr) as $la) {
			if (($la == 'mail')&&($opt['allmail'])) $search.="(|($la=".$ldapattr["$la"].')(mailalternateaddress='.$ldapattr["$la"].'))';
			else $search.="($la=".$ldapattr["$la"].')';
		}
		$search .= '))';
	}

	$opt['query'] = $search;
	$opt['retattr'] = $retattr;
        $result = ldapsearch($userlog,$ds,$dn,FALSE,$search,$retattr,'mailhost');
	syslog(LOG_INFO, sprintf('%s: Performing search: <%s>',$userlog,$search));
	if (!ldap_close($ds))
		syslog(LOG_ERR, 'Error closing LDAP connection.');
	return $result;
}

function imapopen($user,$password,$host, $authuser=NULL) {
	/* try {
		$memc = new Horde_Memcache(array(
                                        'compression'   => false,
                                        'c_threshold'   => 0,
                                        'hostspec'      => array('localhost'),
                                        'large_items'   => true,
                                        'persistent'    => false,
                                        'prefix'        => 'CYRUS_',
                                        'port'          => array('11211')
		));
	}
	catch (\Horde_Memcache_Exception $e) {
                // Any errors will cause an Exception.
                syslog(LOG_INFO,  "MEMCACHE Error: $e");
                exit ('Some errors happen about Memcache interface. See at log.');
        }
	 */
	try {
	    /* Connect to an IMAP server.
	     *   - Use Horde_Imap_Client_Socket_Pop3 (and most likely port 110) to
	     *     connect to a POP3 server instead. */
	    if (is_null($authuser))
		    $authuser = $user;
	    $client = new Horde_Imap_Client_Socket(array(
		'username' => $user,
		'authusername' => $authuser,
	        'password' => $password,
        	'hostspec' => $host,
        	'port' => '143',
        	'secure' => false,
 
        	// OPTIONAL Debugging. Will output IMAP log to the /tmp/foo file
        	'debug' => '/tmp/foo',
 
	        // OPTIONAL Caching. Will use cache files in /tmp/hordecache.
        	// Requires the Horde/Cache package, an optional dependency to
        	// Horde/Imap_Client.
        	//'cache' => array(
            	//	'backend' => new Horde_Imap_Client_Cache_Backend_Cache(array(
		//		'cacheob' => new Horde_Cache(new Horde_Cache_Storage_Memcache(array(
		//			'memcache'	=> $memc,
		//			'prefix'	=> 'CYRUS_'
                //		)))
            	//	))
        	//)
	   ));
	   return $client;
	} catch (Horde_Imap_Client_Exception $e) {
		// Any errors will cause an Exception.
		syslog(LOG_INFO,  "IMAP Error: $e");
		exit ('Some errors happen. See at log.');
	}
}

function quota_check ($ldapi,$imapuser,$imap_password,$threshold,$nobelowth) {


        $cont = 0;      //Utenti su mailstore trovati
        $numbaduser=0;  //Come sopra, ma solo quelli con requisiti di quota sopra soglia
        $overquota=0;   //Utenti su mailstore overquota trovati
        $space['occ']=0;        //Tot spazio occupato
        $space['tot']=0;        //Tot spazio riservato
        $space['nl']=array();   //Elenco utenti nolimits

	for ($f=0;$f<$ldapi['count'];$f++) {

		if (empty($ldapi[$f]['mailhost'][0])) {
			continue;
		}
		$ldapi[$f]['perc'] = NULL;
		$ldapi[$f]['usage'] = NULL;
		$ldapi[$f]['limit'] = NULL;

		$mailhost = $ldapi[$f]['mailhost'][0];
		$uid = $ldapi[$f]['uid'][0];
				$account = 'user/'.$uid;
		$mbox=imapopen($imapuser,$imap_password,$mailhost);
		if ($mbox) {
			try {
				$quota_values = $mbox->getQuotaRoot($account);
			}
			catch (Horde_Imap_Client_Exception $e) {
				syslog(LOG_INFO,  "IMAP Error: $e");
				exit ('Some errors happen. See at log.');
			}

			try {
				$meta = array('/shared/vendor/cmu/cyrus-imapd/lastpop',
					'/shared/vendor/cmu/cyrus-imapd/lastupdate',
					'/shared/vendor/cmu/cyrus-imapd/partition' );
				$metaValues = $mbox->getMetadata($account, $meta);
			}
			catch (Horde_Imap_Client_Exception $e) {
				syslog(LOG_INFO,  "IMAP Error: $e");
				exit ('Some errors happen. See at log.');
			}

		}
		$mbox->close();
		$ldapi[$f]['anno'] = $metaValues["$account"];
		if (is_array($quota_values)) {
			$ldapi[$f] = array_merge($ldapi[$f], $quota_values["$account"]['storage']);
		  $ldapi[$f]['perc'] = ceil(($ldapi[$f]['usage']/$ldapi[$f]['limit'])*100);
                  if ($ldapi[$f]['perc'] < $threshold) {
                        $ldapi[$f]['style'] = 3;
                        if ($nobelowth)
				continue;
                  }
		  if (($ldapi[$f]['perc'] >= $threshold) and ($ldapi[$f]['perc'] < 100)) {
			$numbaduser++;
			$ldapi[$f]['style'] = 0;
		  }
		  elseif ($ldapi[$f]['perc'] >= 100) {
			$numbaduser++;
                        $overquota++;
			if ($ldapi[$f]['perc'] == 100) $ldapi[$f]['style'] = 1;
			else $ldapi[$f]['style'] = 2;
		  }
		  if (is_numeric($ldapi[$f]['limit'])) $space['tot']+=$ldapi[$f]['limit'];
		/* Unfortunately for a BUG imap_get_quota returns NULL for NOLIMIT users! */
		  else array_push($space['nl'],$ldapi[$f]['mail'][0]);
                  $space['occ']+=$ldapi[$f]['usage'];
		  $cont++;
		}
		/* For a BUG above, these are the real NOLIMIT users */
		else $ldapi[$f]['style'] = -1;
	}

	$tot = $ldapi['count'];
	$ldapi = reindex($ldapi,$threshold,$nobelowth);

	$ldapi['ldap'] = $tot;			// Users returned by LDAP below threshold included
	$ldapi['numbaduser'] = $numbaduser;	// IMAP Users and over threshold
	$ldapi['overquota']  = $overquota;	// IMAP Users overquota
	$ldapi['cont']       = $cont;		// IMAP USers
	$ldapi['space']      = $space;
	$ldapi['th']	     = $threshold;

	return $ldapi;
}

function reindex($entries,$threshold,$nobelow) {
/* Reindex LDAP result exluding if there is the condition
   (no mailbox or mailbox over threshold)     */

	$d=0;	
	for ($f=0;$f<$entries['count'];$f++) {
		if ( empty($entries[$f]['mailhost'][0])OR((isset($entries[$f]['perc']))AND($nobelow)AND($entries[$f]['perc'] < $threshold)) )
		{
			unset($entries[$f]);
			$d++;
		}
	}
	if ($d==0)
		return $entries;
	$count = $entries['count'] - $d;
	unset($entries['count']);
	$indexed = array_values($entries);
	$indexed['count']= $count;
	return $indexed;
}

function quota_control ($userlog,$key,$opt,$imapmanager,$imap_password) {
/* Check the quota and return the updated array */
	if ($opt['onlyldap'])  
		print view_ldap($key,$opt);
	else {
		syslog(LOG_INFO, sprintf('%s: IMAP info included in search result.', $userlog));
		$key = quota_check($key,$imapmanager,$imap_password,$opt['soglia'],$opt['nobelowth']); 
		print view_store($key,$opt);
	}
	if (PHP_SAPI != "cli")
		printformEmail(array($key,$opt));
	return $key;
}

function view_store ($user,$opt) {

	$LDAPnuser = $user['count'];

        if ($LDAPnuser!=0) {
                $message = "<table><caption>Result</caption><thead><tr><th>Username</th>";
                foreach ($opt['retattr'] as $attr) { 
                        if (($attr=='uid')OR($attr=='objectclass')) continue;
                        $message.= "<th>$attr</th>";
                }
	
                $message.='<th>%</th><th>Usage</th><th>Limit</th><th>Last IMAP</th><th>Last POP</th><th>Part</th></tr></thead><tbody>';
        }

	else $message = "<p>No result found.</p>";
	$style=NULL;

	for ($f=0;$f<$LDAPnuser;$f++) {
		if (isset($user[$f]['style'])) switch ($user[$f]['style']) {
			case '-1':
				$style=" style=\"color:gray !important; background-color:white;\"";
				break;
			case '1':
				$style=" style=\"color:yellow;background-color:darkgray;\"";
				break;
			case '2':
				$style=" style=\"color:greenyellow;background-color:darkgray;\"";
				break;
			case '3':
				$style=" style=\"color:slateGray;background-color:white;\"";
				break;
			case '0':
				$style=NULL;
		}

                /* Stabilisce se è un gruppo*/
                $iii=0;
                $nogroup=TRUE;
                while (($iii<$user[$f]['objectclass']['count'])and($nogroup)) {
                        if (($user[$f]['objectclass'][$iii]=='mailgroup')OR($user[$f]['objectclass'][$iii]=='mailGroup'))  $nogroup=FALSE;
                        $iii++;
                }
                if ($nogroup)  $linktext=$user[$f]['uid'][0];
                else 	       $linktext='GROUP';
		$link = $user[$f]['dn'];
                /**********************/
		/* Nota: $user[$f]['ldap']['uid'][0] == $user[$f][2] se quest'ultimo è definito*/
		$message.= "<tr><td$style>".linkurl($link,$linktext,$opt['onlyldap'],$style)."</td>";
                foreach ($opt['retattr'] as $attr) {
                        if ($attr=='objectclass') continue;
                        if ($attr=='uid') continue;
			if (!isset($user[$f]["$attr"]))
				$user[$f]["$attr"] = NULL;
                        $message.= "<td$style>".printattr($user[$f]["$attr"]).'</td>';
		}
		$message .= sprintf('<td %s>%s</td><td %s>%d</td><td %s>%s</td><td %s>%s</td><td %s>%s</td><td %s>%s</td></tr>',
			$style,
			(($user[$f]['perc']) ?: 'N/A'),
			$style,
			formatMB($user[$f]['usage']),
			$style,
			formatMB($user[$f]['limit']),
			$style,
			$user[$f]['anno']['/shared/vendor/cmu/cyrus-imapd/lastupdate'],
			$style,
			$user[$f]['anno']['/shared/vendor/cmu/cyrus-imapd/lastpop'],
			$style,
			$user[$f]['anno']['/shared/vendor/cmu/cyrus-imapd/partition']
		);

	}

	$cs = count($opt['retattr'])+6;
        if ($LDAPnuser!=0) {
		$message.="</tbody><tfoot><tr><td colspan=\"$cs\">LDAP entries: {$user['ldap']}<br>Mailboxes account: ".$user['cont']."<br>Mailboxes over threshold ({$opt['soglia']}%): ".$user['numbaduser'].'<br>Mailboxes overquota: '.$user['overquota'].'<br>Reserved space: '.formatGB($user['space']['tot']).' GiB<br>Busy space: '.formatGB($user['space']['occ'])." GiB</td></tr></tfoot></table>";
	}
	return $message;
}


function view_ldap($user,$opt) {
	$LDAPnuser = $user['count'];
	if ($LDAPnuser!=0) {
		$message='
                <table>
		<caption>Result</caption>
		<thead><tr><th>Username</th>';
		foreach ($opt['retattr'] as $attr) { 
			if (($attr=='uid')OR($attr=='objectclass')) continue;
			$message.="<th>$attr</th>";
		}
		$message.='</tr></thead>';
	}
	for ($f=0;$f<$LDAPnuser;$f++) {
		/* Stabilisce se è un gruppo*/
		$iii=0;
		$nogroup=TRUE;
		while (($iii<$user[$f]['objectclass']['count'])and($nogroup)) {
			if (($user[$f]['objectclass'][$iii]=='mailgroup')OR($user[$f]['objectclass'][$iii]=='mailGroup')) $nogroup=FALSE;
			$iii++;
		}
                if ($nogroup)  $linktext=$user[$f]['uid'][0];
                              
                else           $linktext='GROUP';
		$link = $user[$f]['dn'];

		/**********************/

        	$message.= "<tr><td>".linkurl($link,$linktext,$opt['onlyldap'],NULL)."</td>";
		foreach ($opt['retattr'] as $attr) {
			if (($attr=='uid')OR($attr=='objectclass')) continue;
			$message.='<td>'.printattr($user[$f]["$attr"]).'</td>';
                }
		$message.='</tr>';;
	}
	$message.= "<tfoot><tr><td colspan=\"2\"><b>Number of entries:</b> $LDAPnuser</td></tr></tfoot></table>";
	if (!isset($message)) $message=NULL;
	return $message;
}


function printattr ($attr) {
	if (empty($attr))
		$result='-- empty --';
	else {
		$result=NULL;
		for ($iii=0;$iii<$attr['count'];$iii++) {
	        	if ($iii>0)
				$sep='<br>';
			else $sep='';
	                $result.= $sep.nl2br(htmlentities($attr[$iii])); #valore attributo
		}
	}
	return $result;
}

function linkurl($ID,$htext,$onlyldap,$style) {
	return "<a $style title=\"details of $htext\" data-toggle=\"modal\" data-target=\"#myModal\" href=\"view.php?ID=$ID&onlyldap=$onlyldap\">$htext</a>";
}

function formatMB($var) {
	if (empty($var)) return '-';
        if (is_numeric($var)) return ceil ($var/1024);
        return $var;
}

function formatGB($var) {
	if (empty($var)) return '-';
	if (is_numeric($var)) return ceil ($var/1048576);
	return $var;
}

function mailboxname($stream,$type) {

	switch ($type) {
		case 'admin':
			list($_,$folder) = explode ('user/',$stream,2);
			list($_,$folder) = explode ('/',$folder,2);
			list($folder,$_) = explode ('@',$folder,2);
			if (empty($folder)) $folder = 'INBOX';
			break;
		case 'proxy':
			list($_,$folder) = explode ('}',$stream,2);
			break;
	}
	return $folder;
}



/******* Funzione invio mail **********/

function printformEmail ($data) {
	$post=htmlentities(serialize($data));
	print <<<END
        <div class="cellfix" style="width: 50ex;">
        <form method="POST" name="OutForm" action="sendout.php">
        <input type="hidden" name="entries" value="$post"></input>
        <p><input type="submit" value="Download the Excel file" name="Engage" class="btn">
        </form>
        </div>
	<div id="content" class="left">
	 <form method="POST" name="EmailForm" action="sendmail.php" onSubmit="xmlhttpPost('sendmail.php', 'EmailForm', 'Email', '<img src=\'/include/pleasewait.gif\'>'); return false;">
	<fieldset><legend>Send these results to:</legend><p><input type="email" placeholder="type your email address" name="mailto" size="50" required></p>
	<input type="hidden" name="entries" value="$post"></input>
	<p><input type="submit" value="Engage" name="Engage" class="button">
	<input type="reset" value="Reset" name="Reset" class="button"></p></fieldset>
	</form>
	</div>
END;
}


function emailSent ($opt,$subject,$mailopt) {
	/* mailopt is array(mailfrom => $mail_from, from => $from,
		reply => $reply_to, client => $infoenv, prio => $prio,
		attachment => $filename */

	$date = new DateTime('now');
	$today = $date->format('r');
	$uniqid = md5(uniqid('',1));
	$uniqid2 = md5(uniqid('',1));

        $header="From: {$mailopt['from']}\r\n".
                "Reply-To: {$mailopt['reply']}\r\n".
		"Importance: {$mailopt['prio']}\r\n".
                "MIME-Version:1.0\r\n".
		"Message-ID: <$uniqid@".gethostname().">\r\n".
		"Organization: Societa` Cibernetica Sirio\r\n".
		"Date: $today\r\n".
		"X-Mailer: PHP/" . phpversion()."\r\n".
                "Content-Type: multipart/mixed; boundary=\"".$uniqid2."\"\r\n";

        /* Messaggio */
	$message = "This is a multi-part message in MIME format.\r\n\r\n";
	$message.= "--".$uniqid2."\r\n";
	$message.= "Content-type:text/plain; charset=iso-8859-1\r\n";
	$message.= "Content-Transfer-Encoding: 7bit\r\n\r\n";
	$message.= "Searching options:\r\n- ";
	$message.= wordwrap("query LDAP: ".$opt['query'], 76, "\r\n", true);
	if ($opt['stype']=='UDEF') $message.=' *written by user.*';
	if ($opt['onlyldap']) $message.= "\r\n- LDAP only";
	else {
		$message.= "\r\n- with IMAP data.\r\n";
		$message.= "\tThreshold selected: ".$opt['soglia']."%";
	}

	if (PHP_SAPI == "cli")
		$message.= wordwrap("\r\n\r\n{$mailopt['infoenv']}", 76, "\r\n", false);
	/* Attach the file */	
	if (file_exists('tmp/'.$mailopt['attachment']))
		$file = file_get_contents('tmp/'.$mailopt['attachment'], NULL, NULL, 0, filesize('tmp/'.$mailopt['attachment']));
	else {
		syslog(LOG_ERR, sprintf('%s: Error: %s','ldapuser','Can\'t find the attachment!'));
		return FALSE;
	}
	$message.= "\r\n\r\n--".$uniqid2."\r\n";
	$message.= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;\tname=\"".$mailopt['attachment']."\"\r\n";
	$message.= "Content-Transfer-Encoding: base64\r\n";
	$message.= "Content-Disposition: attachment;\tfilename=\"".$mailopt['attachment']."\"\r\n\r\n";
	$message.= chunk_split(base64_encode($file));
	$message.= "--".$uniqid2."--";

	if ( strlen(ini_get("safe_mode"))< 1) {
        	$old_mailfrom = ini_get("sendmail_from");
        	ini_set("sendmail_from", $mailopt['mailfrom']);
		$params = sprintf("-oi -f %s", $mailopt['mailfrom']);
	        if (!(mail($mailopt['mailto'], $subject, $message,$header,$params))) $flag=FALSE;
        	else $flag=TRUE;
        	if (isset($old_mailfrom))
            		ini_set("sendmail_from", $old_mailfrom);
	}	
	else {
		if (!(mail($opt['mailto'], "Result ".$subject."- Query LDAP: ".$opt['query'], $message,$header))) $flag=FALSE;
                else $flag=TRUE;
	}
	return $flag;
}


/* Excel Export */

function composeXLS($input) {
/* save LDAP search result into Excel xlsx */
	
	/* Initialize values */
        $retattr = $input[1];
        $ldapres = $input[0];
        $offset = 3;
        $imap = FALSE;

	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
	$spreadsheet = $reader->load("template/template.xlsx");

	$worksheet = $spreadsheet->getActiveSheet();

	/* Determine if LDAP pure or IMAP data is present */
	if (isset($ldapres['cont']))
		$imap = TRUE;

	/* Attributes array */
	if ($imap) 
		array_push($retattr, 'perc','usage','limit','Last IMAP','Last POP','Partition');
	foreach ($retattr as $val) {
		switch ( $val ) {
			case 'objectclass':
			case is_int($val):
			case 'count':
			case 'dn':
				continue;
			default:
        			$write[0][] = $val;
		}
	}

	$nattr = count($write[0]);
	for ($i=0; $i<$ldapres['count']; $i++) {
		for( $j=0; $j<$nattr; $j++) {
			$name = $write[0][$j];
			switch ($name) {
				case 'Last IMAP':
					$write[$i+1][$j] = $ldapres[$i]['anno']['/shared/vendor/cmu/cyrus-imapd/lastupdate'];
					continue 2;
				case 'Last POP':
					$write[$i+1][$j] = $ldapres[$i]['anno']['/shared/vendor/cmu/cyrus-imapd/lastpop'];
					continue 2;
				case 'Partition':
					$write[$i+1][$j] = $ldapres[$i]['anno']['/shared/vendor/cmu/cyrus-imapd/partition'];
					continue 2;
			}
			if (isset( $ldapres[$i]["$name"]['count'] ))
				unset ($ldapres[$i]["$name"]['count']);
			if (empty($ldapres[$i]["$name"]))
				$ldapres[$i]["$name"][0] = '-- empty --';
			if (is_array($ldapres[$i]["$name"]))
				$write[$i+1][$j] = implode("\n",$ldapres[$i]["$name"]);
			else
				$write[$i+1][$j] = $ldapres[$i]["$name"];
		}
	}
	/* honor \n in cell */
	\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );
	/* write the sheet */
	$spreadsheet->getActiveSheet()->fromArray(
        	$write,  // The data to set
        	NULL,        // Array values with this value will not be set
        	"A{$offset}"         // Top left coordinate of the worksheet range where
        	             //    we want to set these values (default is A1)
    		);

	$lastCol = columnLetter($nattr);
	$lastRow = $ldapres['count']+$offset+1; /* There is also the header line! */
	$footRow = $lastRow+2;

	$spreadsheet->getActiveSheet()->setCellValue("A{$footRow}", 'Total user:');
	$spreadsheet->getActiveSheet()->setCellValue("B{$footRow}", ($ldapres['ldap']) ?: $ldapres['count']);	
	if ($imap) {
		$spreadsheet->getActiveSheet()->setCellValue('A'.++$footRow, 'User with mailbox account:');
		$spreadsheet->getActiveSheet()->setCellValue('B'.$footRow, $ldapres['cont']);
		$spreadsheet->getActiveSheet()->setCellValue('A'.++$footRow, "User over threshold ({$ldapres['th']}%):");
		$spreadsheet->getActiveSheet()->setCellValue('B'.$footRow, $ldapres['numbaduser']);
		$spreadsheet->getActiveSheet()->setCellValue('A'.++$footRow, 'User overquota:');
		$spreadsheet->getActiveSheet()->setCellValue('B'.$footRow, $ldapres['overquota']);
		$spreadsheet->getActiveSheet()->setCellValue('A'.++$footRow, 'Disk space allocated:');
		$spreadsheet->getActiveSheet()->setCellValue('B'.$footRow, $ldapres['space']['tot']);
		$spreadsheet->getActiveSheet()->setCellValue('A'.++$footRow, 'Disk space busy:');
		$spreadsheet->getActiveSheet()->setCellValue('B'.$footRow, $ldapres['space']['occ']);
	}

	/* Set properties */
	$spreadsheet->getProperties()
	    ->setCreator("Marco Favero")
	    ->setTitle("LDAP&IMAP Explorer")
	    ->setSubject("Search result")
	    ->setDescription("Search result from LDAP and IMAP.")
	    ->setKeywords("LDAP IMAP Email")
	    ->setCategory("Email Admin");

	/* autosize the cell width */
	foreach(range('A',$lastCol) as $columnID) {
		$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
			->setAutoSize(true);
	}

	/* Style of header line */
	$styleHead = array(
	    'font' => array(
	        'bold' => true,
	    ),
	    'alignment' => array(
	        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
	    ),
	    'borders' => array(
	        'top' => array(
	            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
	        ),
	    ),
	    'fill' => array(
	        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
	        'rotation' => 90,
	        'startColor' => array(
	            'argb' => 'FFA0A0A0',
	        ),
	        'endColor' => array(
	            'argb' => 'FFFFFFFF',
	        ),
	    ),
	);
	$spreadsheet->getActiveSheet()->getStyle("A{$offset}:{$lastCol}{$offset}")->applyFromArray($styleHead);

	for ($k=$offset+1;$k<$lastRow;$k++) {
	        /* Style of body */
	        $styleBody = array(
	                'alignment' => array(
	                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
	                ),
	        );
		if (isset($ldapres[$k-$offset-1]['style'])) {
			switch ( $ldapres[$k-$offset-1]['style'] ) {
				case 0:
					/* Over threshold but not overquota */
					break;
				case 2:
					/* Overquota over 100% */
					$styleBody['font']['color'] = array('argb' => 'ffff0000');
				case 1:
					/* Overquota at 100% */
					$styleBody['font']['bold'] = true;
					break;
				case 3:
					/* No mailbox or above threshold */
					$styleBody['font']['color'] = array('argb' => 'ff808080');
					$styleBody['font']['italic'] = true;
					break;
				case -1:
					/* Mailbox NO LIMIT */
					$styleBody['font']['color'] = array('argb' => 'ff0000ff');
			}
		}
		$spreadsheet->getActiveSheet()->getStyle("A$k:{$lastCol}$k")->applyFromArray($styleBody);
	}

	/* Return the formatted sheet */
	return $spreadsheet;
}

function createWriter($spreadsheet) {
	return \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
}

function saveXLS($writer, $where) {

	switch ( $where ) {
		case 'file':
			$file = uniqid('ldap_', TRUE). '.xlsx';
			$writer->save("tmp/$file");
			return $file;
		case 'out':
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename=ldap.xlsx');
			header('Cache-Control: max-age=0');
			$writer->save('php://output');
			return TRUE;
	}
}

function columnLetter($c){
/* Convert number to Excel letter */
/* Taken from https://icesquare.com/wordpress/example-code-to-convert-a-number-to-excel-column-letter/ */
    $c = intval($c);
    if ($c <= 0) return '';

    $letter = '';
             
    while($c != 0){
       $p = ($c - 1) % 26;
       $c = intval(($c - $p) / 26);
       $letter = chr(65 + $p) . $letter;
    }
    
    return $letter;
        
}

function printACL($mailbox, $acl) {
	/* print a menu with ACL mailboxes
	 * $mailbox = Horde_Imap_Client_Mailbox object
	 * $acl = array of Horde_Imap_Client_Data_Acl object
	 * 	as returned by getACL function */
	$mboxName = htmlspecialchars(\Horde_Imap_Client_Mailbox::get($mailbox, ENT_QUOTES, 'UTF-8'));
	$return = sprintf('<div class="dropdown"><div class="dropbtn">%s</div><div class="dropdown-content">',
		$mboxName);
	foreach ($acl as $account => $priv) {
		$return .= sprintf('<div id="tablerow"><p>%s</p><p>%s</p></div>',
			$account,
			$priv->getString('RFC_4314')
		);
	}
	$return .= '</div></div>';
	return $return;
}
?>
