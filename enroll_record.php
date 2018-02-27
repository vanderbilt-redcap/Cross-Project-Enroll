<?php

define("CONNECT_FILE_PATH", dirname(dirname(dirname(__FILE__))));
require_once(CONNECT_FILE_PATH."/plugins/Core/bootstrap.php");

$satellite_pid = $_POST['satellite_pid'];
$unique_id = $_POST['unique_id'];
$unique_field = $_POST['unique_field'];

$insertData = array(
	$unique_field => $unique_id
);

header('Content-Type: application/json');

$thisjson = \REDCap::getData($satellite_pid, 'json', $unique_id, $unique_field);
$thisdata = json_decode($thisjson, true);
if(empty($thisdata)) {
	$satelliteProject = new \Project($satellite_pid);
	$firstEvent = $satelliteProject->firstEventId;

	$insertData = [$unique_id => [$firstEvent => [$unique_field => $unique_id]]];

	$insert = \REDCap::saveData($satellite_pid, 'array', $insertData, 'normal');
	$return = array();
	$return['status'] = ($insert['item_count'] >= 1 ? true : false );
	$return['pid'] = $satellite_pid;
	$return['record_id'] = $unique_id;

	echo json_encode($return);
} else {
	echo json_encode(array('status' => false));
}
exit();
