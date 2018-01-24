#!/usr/bin/php
<?php

$usage = <<<END

Usage: $argv[0] -i -t <th> -n -q <query LDAP> -e <email>

 where:

i		= with IMAP info
-t <th>		= threshold %
-n		= don't include user below threshold
-q <query LDAP>	= LDAP query
-e <email>	= Recipient address of report
-h		= this brief summary usage message

Enjoy!

END;
 

if (PHP_SAPI != "cli") {
    exit;
}


$opts = getopt('it:nq:e:h');

$_POST['onlyldap'] = !isset($opts['i']);
$_POST['nobelowth'] = isset($opts['n']);
$_POST['soglia'] = 0;
$_POST['retattr'] = array('givenname','sn','mailalternateaddress');
$_POST['dn'] = 'ou=People,o=servizirete,c=it';
$_POST['stype'] = 'UDEF';
$pathweb= '/ldap/report.html';
$date = new DateTime('now');
$tstamp = $date->format(DATE_RFC850);

// Handle command line arguments
foreach (array_keys($opts) as $op) switch ($op) {
  case 't':
    $_POST['soglia'] = $opts['t'];
    break;
  case 'q':
    $_POST["query"] = $opts['q'];
    break;
  case 'e':
    $_POST["mailto"] = $opts['e'];
    break;
  case 'h':
    var_dump($_POST);
    exit($usage);
}

fclose(STDOUT);
$STDOUT = fopen('/var/www/html'.$pathweb, 'wb');
print <<<END
<html>
<head>
<link rel="stylesheet" type="text/css" href="/include/style.css">
<link rel="stylesheet" type="text/css" href="modal.css">
<link rel="SHORTCUT ICON" href="favicon.ico">
<script  src="/include/ajaxsbmt.js" type="text/javascript"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body>
<h1>Unified Quota Report</h1>
<p class="right">Generated on $tstamp</p>
END;

require_once('result.php');

if(!empty($_POST["mailto"])) {
	$_POST['entries'] = serialize(array($key,$opt));
	require_once('sendmail.php');
}

?>
<!-- Modal -->
<div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        </div><!-- /.modal-content -->
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<script type="text/javascript">
<!-- Remove cache in modal window -->
$('body').on('hidden.bs.modal', '.modal', function () {
        $(this).removeData('bs.modal');
      });
</script>
</body>
</html>
