<?php

include("../../header.php");

// This example is older than the auto importing example and doesn't Make
// use of some of the newer exporting methods available in MFCS::exporting

$filesExportBaseDir = "/home/mfcs.lib.wvu.edu/public_html/exports/Printed_Ephemera_Collection_-_Hydra_-_JSON/dlxsXmlImageClass/files/".time();
if (!mkdir($filesExportBaseDir)) {
	die("Couldn't Make Directory");
}
if (!mkdir($filesExportBaseDir."/jpg")) {
	die("Couldn't Make Directory");
}
if (!mkdir($filesExportBaseDir."/thumbs")) {
	die("Couldn't Make Directory");
}

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

// Output File:
$outFileName        = "pec-data_".(time()).".json";
$outFile            = "./dlxsXmlImageClass/".$outFileName;

$outDigitalFileName = "pec-files_".(time()).".tar.gz";
$outDigitalFile     = "./dlxsXmlImageClass/".$outDigitalFileName;

localvars::add("outFile",$outFile);
localvars::add("outFileName",$outFileName);
localvars::add("outDigitalFile",$outDigitalFile);
localvars::add("outDigitalFileName",$outDigitalFileName);

$sql       = sprintf("SELECT MAX(`date`) FROM exports WHERE `formID`='2'");
$sqlResult = $engine->openDB->query($sql);

if (!$sqlResult['result']) {
	errorHandle::newError(__METHOD__."() - : ".$sqlResult['error'], errorHandle::DEBUG);
	die ("error getting max.");
}

$row = mysql_fetch_array($sqlResult['result'],  MYSQL_ASSOC);

$lastExportDate = (isnull($row['MAX(`date`)']))?0:$row['MAX(`date`)'];

$objects = objects::getAllObjectsForForm("2");

$oarray = array();

$count = 0;
foreach ($objects as $object) {

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

	// deal with the files
	if ($object['modifiedTime'] > $lastExportDate && isset($object['data']['digitalFiles']) && is_array($object['data']['digitalFiles'])) {

		foreach ($object['data']['digitalFiles']['files']['combine'] as $file) {

			switch ($file['name']) {
				case "thumb.jpg":
					$destinationPath = $filesExportBaseDir."/thumbs/".$object['idno'].".jpg";
					break;
				case "thumb":
					$destinationPath = $filesExportBaseDir."/thumbs/".$object['idno'].".jpg";
					break;
				case "combined.pdf":
					$destinationPath = $filesExportBaseDir."/jpg/".$object['idno'].".pdf";
					break;
				default:
					$destinationPath = NULL;
			}

			if (!isnull($destinationPath)) exec(sprintf("ln -sf %s/%s%s %s",mfcs::config('convertedPath'),$file['path'],$file['name'],$destinationPath));
		}

	}

}

if (!$file = fopen($outFile,"w")) {
	errorHandle::newError(__METHOD__."() - Error creating file", errorHandle::DEBUG);
	print "error opening file.";
	exit;
}
fwrite($file, json_encode($oarray));
fclose($file);

exec(sprintf('tar -hzcf %s %s',$outDigitalFile,$filesExportBaseDir));

$sql       = sprintf("INSERT INTO `exports` (`formID`,`date`) VALUES('%s','%s')",
	'2',
	time()
	);
$sqlResult = $engine->openDB->query($sql);

if (!$sqlResult['result']) {
	errorHandle::newError(__METHOD__."() - : ".$sqlResult['error'], errorHandle::DEBUG);
	print "<p>Error inserting into export table.</p>";
}

?>

Download: <br />
<a href="{local var="outFile"}">{local var="outFileName"}</a> <br />
<a href="{local var="outDigitalFile"}">{local var="outDigitalFileName"}</a>
