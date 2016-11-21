<?php

// Load the MFCS header file
include("/home/mfcs.lib.wvu.edu/public_html/header.php");

// Project name. Must match HYDRA_PROJECT_NAME env variable
$project_name = "pec";
$form_id = "2";
$time_stamp = time();

if (!($directories = Exporting::createExportDirectories($project_name, mfcs::config('nfsexport'), $time_stamp, array("data","jpg","thumbs")))) {
	die("Couldn't create export directories");
}

// This handles any custom conversions that we need to do, to handle special
// characters that are in the meta data.
function convertCharacters($string) {
	$string = preg_replace('/…/', '...', $string);
	$string = preg_replace('/\*\*\!\*\*/', ';', $string);
	$string = preg_replace('/&auml;/', "ä", $string);
	$string = preg_replace('/&hellip;/', "...",$string);
	$string = preg_replace('/&amp;/', "&",$string);
	$string = preg_replace('/&quot;/', '"',$string);
	return $string;
}

function getHeadingByID($id) {
  $object = objects::get($id);
  return($object['data']['name']);
}

$metadata_output = sprintf("%s/pec-data.json",$directories["data"]);

$last_export_date = exporting::getExportDate($form_id);
$objects = objects::getAllObjectsForForm($form_id);

$oarray = array();
$record_count = 0;
$digital_count = 0;
foreach ($objects as $object) {

	if ($object['modifiedTime'] < $last_export_date) continue;
	if ($object['data']['publicRelease'] == "No") continue;

  $object['data']['description'] = preg_replace('/<p>/', '', $object['data']['description']);
	$object['data']['description'] = preg_replace('/<\/p>/', '', $object['data']['description']);
	$object['data']['description'] = preg_replace('/&nbsp;/', '', $object['data']['description']);
  $object['data']['description'] = convertCharacters($object['data']['description']);
  $object['data']['description'] = preg_replace('/&lt;br \/&gt;/', '<br>', $object['data']['description']);

  $tmp = array(

    "identifier"          => $object['idno'],
    "title"               => convertCharacters($object['data']['title']),
    "date"                => $object['data']['date'],
    "description"         => $object['data']['description'],
    "format"              => $object['data']['format'],
    "type"                => $object['data']['type'],
    "creatorPersName"     => array_map("getHeadingByID",(array)$object['data']['creatorPersName']),
    "creatorCorpName"     => array_map("getHeadingByID",(array)$object['data']['creatorCorpName']),
    "creatorMeetName"     => array_map("getHeadingByID",(array)$object['data']['creatorMeetName']),
    "creatorUniformTitle" => array_map("getHeadingByID",(array)$object['data']['creatorUniformTitle']),
    "subjectPersName"     => array_map("getHeadingByID",(array)$object['data']['subjectPersName']),
    "subjectCorpName"     => array_map("getHeadingByID",(array)$object['data']['subjectCorpName']),
    "subjectMeetingName"  => array_map("getHeadingByID",(array)$object['data']['subjectMeetingName']),
    "subjectUniformTitle" => array_map("getHeadingByID",(array)$object['data']['subjectUniformTitle']),
    "subjectTopical"      => array_map("getHeadingByID",(array)$object['data']['subjectTopical']),
    "subjectGeoName"      => array_map("getHeadingByID",(array)$object['data']['subjectGeoName'])

  );

  $oarray[] = $tmp;
	$record_count++;

	// deal with the files
	 if(isset($object['data']['digitalFiles']) && is_array($object['data']['digitalFiles']) &&
	 isset($object['data']['digitalFiles']['files']['combine']) && is_array($object['data']['digitalFiles']['files']['combine'])) {

		foreach ($object['data']['digitalFiles']['files']['combine'] as $file) {

			switch ($file['name']) {
				case "thumb.jpg":
				case "thumb":
					$destinationPath = sprintf("%s/%s",$directories["thumbs"],$object['idno'].".jpg");
					break;
				case "combined.pdf":
					$destinationPath = sprintf("%s/%s",$directories["jpg"],$object['idno'].".pdf");
					break;
				default:
					$destinationPath = NULL;
			}

			if (!isnull($destinationPath)) {
				exec(sprintf("cp %s/%s%s %s",mfcs::config('convertedPath'),$file['path'],$file['name'],$destinationPath));
			}
		}

		$digital_count++;
	}

}

if (!$file = fopen($metadata_output,"w")) {
	errorHandle::newError(__METHOD__."() - Error creating file", errorHandle::DEBUG);
	print "error opening file.";
	exit;
}
fwrite($file, json_encode($oarray));
fclose($file);

$contact_emails = notification::get_notification_emails($form_id);

if (!exporting::writeControlFile($directories["export_control_file"],"pec", $time_stamp, "partial", $digital_count, $record_count, $contact_emails)) {
	die("Error writing control file");
}

if (!exporting::setExportDate("2",$time_stamp)) {
	print "<p>Error inserting into export table.</p>";
}

?>

Done Exporting.
