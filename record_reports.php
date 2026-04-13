<?php
$data = file_get_contents("php://input");
file_put_contents("user_reports.json", $data);
?>