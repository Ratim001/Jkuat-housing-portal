<?php
require_once __DIR__ . '/../includes/db.php';
 $sql = 'SELECT applicant_id,name,email,is_disabled,status FROM applicants ORDER BY applicant_id DESC LIMIT 10';
 $res = $conn->query($sql);
 if (!$res) {
	 echo "QUERY ERROR: " . $conn->error . PHP_EOL;
	 exit(1);
 }
 $out = array();
 while($r = $res->fetch_assoc()) { $out[] = $r; }
 echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
