<?php
if (!isset($_POST['entries'])) exit(255);
$data = unserialize($_POST['entries']);
require_once('config.php');
require_once('functions.php');
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$user = username();
$err = NULL;

openlog($syslog['tag'], LOG_PID, $syslog['fac']);
clientIP($user);

if (PHP_SAPI != "cli") {
	if(!filter_var($_POST['mailto'], FILTER_VALIDATE_EMAIL))
		exit ($err = sprintf('<p>%s</p>',htmlentities("The email <{$_POST['mailto']}> is not valid.")));
	else
		if (explode('@',$_POST['mailto'])[1] != $allowed_domain)
			exit ($err= sprintf('<p>%s</p>',htmlentities("You can send mail only to domain <$allowed_domain>. Email not sent.")));
}

if (!is_null($err))
	syslog(LOG_ERR, "$user: $err");

$date = new DateTime('now');
$today = $date->format(DATE_RFC850);	
$infoenv  = "Generated on $today by LDAP&IMAP Explorer.";

$opt=$data[1];
$data[1] = $data[1]['retattr'];
$sheet= composeXLS($data);
$writer = createWriter($sheet);
$filename = saveXLS($writer, 'file');
$mailopt = array(
	'mailfrom' => $mail_from,
	'from'	=> $from,
	'mailto' => $_POST['mailto'],
	'reply'	=> $reply_to,
	'prio'	=> 1,
	'infoenv' => $infoenv,
	'attachment'	=> $filename
);

$sent = emailSent ($opt,'Your LDAP&IMAP Explorer result',$mailopt);
if ($sent) {
	$notice = sprintf ('Email successfully sent to "%s".', $mailopt['mailto']);
	syslog(LOG_INFO, "$user: $notice");
}
else
	$notice = 'Some errors occurred. Email could not be sent.';

printf ('<p>%s</p>',htmlentities($notice));
unlink ('tmp/'.$filename);
?>
