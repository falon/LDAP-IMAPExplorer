<?php
require 'vendor/autoload.php';
use Horde\Imap_Client;
#use Horde\Cache;
#use Horde\Memcache;

require_once('functions.php');
require_once('ldapfunctions.php');
$ini = parse_ini_file('LDAP-IMAPExplorer.conf', true);
$baseDN= $ini['ldap']['baseDN'];
$server= $ini['ldap']['server'];
$port =  $ini['ldap']['port'];
$dnlog = $ini['ldap']['bind'];
$password = $ini['ldap']['password'];
$imapAdmin = $ini['imap']['admin'];
$imapPwd   = $ini['imap']['password'];
$syslog	   = $ini['syslog'];


$threshold = 0;
$opt['nobelowth'] = FALSE;
if (isset($_POST['soglia'])) {
        $opt['soglia'] = $_POST["soglia"];
	$opt['nobelowth'] = (isset($_POST['nobelowth'])) ? $_POST['nobelowth'] : FALSE;
}
else {
	$opt['soglia'] = NULL;
}
if (isset($_POST["uid"])) if ($_POST["uid"]!="") $attr['uid']=$_POST["uid"];
if (isset($_POST["mail"])) { 
	if ($_POST["mail"]!="") {
		$attr['mail']=$_POST["mail"];
		if(!filter_var($attr['mail'], FILTER_VALIDATE_EMAIL))
			exit ('<pre>Email '.$attr['mail'].' is wrong</pre>');
	}
}
if (isset($_POST["mailalternateaddress"])) { 
	if (($_POST["mailalternateaddress"])!='') {
		$attr['mailalternateaddress']=$_POST["mailalternateaddress"];
		if(!filter_var($attr['mailalternateaddress'], FILTER_VALIDATE_EMAIL))
			exit ('<pre>Alias '.$attr['mailalternateaddress'].' wrong</pre>');
	}
}

if (isset($_POST["onlyldap"])) $opt['onlyldap'] = $_POST["onlyldap"];
if (isset($_POST["allmail"])) $opt['allmail'] = $_POST["allmail"];
if (isset($_POST["stype"])) $opt['stype'] = $_POST["stype"];
if (isset($_POST["query"])) if (!empty($_POST["query"])) {
        $opt['query'] = $_POST["query"];
	if ( preg_match('/^(\s*\((?:[&|]\s*(?1)+|(?:!\s*(?1))|[a-zA-Z][a-zA-Z0-9-]*[<>~]?=[^()]*)\s*\)\s*)$/',$opt['query']) !== 1 )
         exit ('<pre>Syntax error in LDAP query '. $opt['query'].'</pre>');
}
if (!isset($_POST['retattr'])) $opt['retattr'] = array();
else $opt['retattr'] = $_POST['retattr'];


if (!isset($attr)) $attr = NULL;



if ((isset($attr['uid']))OR(isset($attr['mail']))OR(isset($attr['mailalternateaddress']))OR(isset($opt['query'])))
{
	$user = username();
	openlog($syslog['tag'], LOG_PID, $syslog['fac']);
	clientIP($user);
	if (version_compare(PHP_VERSION, '7.0.0') < 0)
        	syslog(LOG_ALERT, "Info: Please upgrade to PHP 7.");
	$ldapattr = search_uid($user,$_POST['dn'],$attr,$server,$port,$dnlog,$password,$opt);

        $key=quota_control($user,$ldapattr,$opt,$imapAdmin,$imapPwd);
}
?>

