<?php
// Redirect common root-level requests to the PHP folder where the real file lives.
$qs = $_SERVER['QUERY_STRING'] ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: php/applicantlogin.php' . $qs);
exit;
?>
