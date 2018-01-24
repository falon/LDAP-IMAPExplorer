<?php
$data = unserialize($_POST['entries']);
require_once('functions.php');
$data[1] = $data[1]['retattr'];

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sheet= composeXLS($data);
$writer = createWriter($sheet);
saveXLS($writer, 'out');
?>
