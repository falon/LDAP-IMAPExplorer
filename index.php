<html>
<head>
<title>I Quoting You!</title>
<link rel="stylesheet" type="text/css" href="/include/style.css">
<link rel="stylesheet" type="text/css" href="modal.css">
<link rel="stylesheet" type="text/css" href="menustyle.css">
<link rel="SHORTCUT ICON" href="favicon.ico">

<script  src="/include/ajaxsbmt.js" type="text/javascript"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body>
<?php
$ini = parse_ini_file('LDAP-IMAPExplorer.conf', true);
$baseDN= $ini['ldap']['baseDN'];
?>

<h1>LDAP-IMAP Explorer</h1>
 <form method="POST" name="Richiestadati" action="result.php" onSubmit="xmlhttpPost('result.php', 'Richiestadati', 'Risultato', '<img src=\'/include/pleasewait.gif\'>'); return false;">
<table>
 <thead>
  <tr><td>Search options</td>
    <td><input type="radio" name="onlyldap" value="0" onclick="xmlhttpPost('quota.htm', 'Richiestadati', 'soglia', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>i'); return true;">Include IMAP data</td>
    <td><input type="radio" name="onlyldap" value="1" checked onclick="xmlhttpPost('noquota.htm', 'Richiestadati', 'soglia', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>'); return true;">LDAP only (faster)</td>
  </tr>
 </thead>
 <tbody>
  <tr id="soglia"></tr>
    <tr>
    <td>DN</td>
    <td colspan="2">
    <input type="text" name="dn" size="45" value="<?php echo $baseDN; ?>" required></td>
  </tr>
    <tr><td colspan="3">
    <fieldset>
    <legend>Looking for:</legend>
    UID:  <input class="right" type="text" name="uid" size="45"><br>
    <div class="left">Email: <input type="checkbox" name="allmail" value="1" checked title="Search in both mail and mailAlternateAddress"></div><input class="right" type="email" name="mail" size="45"><br>
    <div class="left">Alias Email:</div><input class="right" type="email" name="mailalternateaddress" size="45">
    </fieldset>
    </td></tr>
  <tr>
    <td>

Attribute logic: </td><td colspan="2">

AND<input type="radio" value="AND" checked name="stype" onclick="xmlhttpPost('noquota.htm', 'Richiestadati', 'query', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>'); return true;"> or OR<input type="radio" name="stype" value="OR"  onclick="xmlhttpPost('noquota.htm', 'Richiestadati', 'query', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>'); return true;"> or your query<input type="radio" name="stype" value="UDEF" onclick="xmlhttpPost('query.htm', 'Richiestadati', 'query', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>'); return true;">:</td>
  </tr>
<tr><td colspan="3" id="query"></tr>
<tr>
      <td colspan="2">
      Attributes to show<br />(uid and mail always)</td>
      <td class="noscroll">
      <select multiple="" name="retattr[]" size="4">
      <option value="mailalternateaddress">Alias email</option>
      <option value="cn">Common Name</option>
      <option value="givenname">Name</option>
      <option value="sn">Surname</option>
      </select></td>

    </tr>
</tbody>
<tfoot>
<tr><td colspan="3">

<p style= "margin-top: 3; text-align:center"><input type="submit" value="Engage"
   name="Engage" class="button" onMouseOver="xmlhttpPost('noquota.htm', 'Richiestadati', 'Email', '<td colspan=\'3\'><img src=\'/include/pleasewait.gif\'>'); return true;"> <input type="reset" value="Reset" name="Reset" class="button"></p></td></tr>
</tfoot>
</table>
</form>
<div id="Risultato"></div>
<div id="Email"></div>


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
$('#myModal').on('shown.bs.modal', function () {
    $('#myModal').animate({ scrollTop: 0 }, 'slow');
});
</script>
</body>
</html>
