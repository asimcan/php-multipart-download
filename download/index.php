<?php

/**
*	PHP MULTIPART DOWNLOAD SCRIPT
*
*	@version 1.0
*	@author Matthew Colf <mattcolf@mattcolf.com>
*
*	@section LICENSE	
*
*	Copyright 2011 Matthew Colf <mattcolf@mattcolf.com>
*
*	Licensed under the Apache License, Version 2.0 (the "License");
*	you may not use this file except in compliance with the License.
*	You may obtain a copy of the License at
*
*	http://www.apache.org/licenses/LICENSE-2.0
*
*	Unless required by applicable law or agreed to in writing, software
*	distributed under the License is distributed on an "AS IS" BASIS,
*	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
*	See the License for the specific language governing permissions and
*	limitations under the License.
*/

#########################################################
# SETTINGS
#########################################################

// define content path including trailing slash
// for example, if your files are in /w/media (/w/media/file.mp4), then your content base would be /w/media/
$content_base= "/path/to/content/directory/";

// full path of default content
// if this script is called with no parameters, then this file is provided 
$default_content = "/path/to/default/file.mp4";

// path to 'file' command
$file_cmd = "/opt/bin/file";

// download file chunk size (in bytes)
$chunksize = 1*(1024*1024);

#########################################################
# SCRIPT, DO NOT CHANGE BELOW THIS LINE
#########################################################

// disable caching
header("Pragma: public");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private");

// disable output buffering
@ob_end_clean();

// disable execution time limit
set_time_limit(0);

// disable compression (for IE)
if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');

// decode the requested file name
if ( isset($_REQUEST["key"]) ) {
	if ( $file = base64_decode($_REQUEST["key"],TRUE) ) {
		$file = $content_base.$file;
	}
	else die("Invalid download key!");
} else $file = $default_content;

// check for valid file
if ( !file_exists($file) ) die("File does not exist!");

// detect mime type
$mime = exec("$file_cmd -i -b '".$file."'");
if ( $clean = strstr($mime,";",TRUE) ) {
	$mime = $clean;
}
if ( strlen($mime) < 1 ) die("Invalid MIME type.");

// file specific headers
header('Accept-Ranges: bytes');
header("Content-Description: File Transfer");
header("Content-Type: $mime");
header('Content-Disposition: attachment; filename="'.basename($file).'"');
header("Content-Transfer-Encoding: binary");

// determine file size
$size = filesize($file);
// workaround for int overflow
if ( $size < 0 ) {
	$size = exec('ls -al "'.$file.'" | awk \'BEGIN {FS=" "}{print $5}\'');
}
			
// add multipart download and resume support			
if ( isset($_SERVER["HTTP_RANGE"]) ) {
	list($a,$range) = explode("=",$_SERVER["HTTP_RANGE"],2);
	list($range) = explode(",",$range,2);
	list($range,$range_end) = explode("=",$range);
	$range = round(floatval($range),0);
	if ( !$range_end ) $range_end = $size-1;
	else $range_end = round(floatval($range_end),0);

	$partial_length = $range_end-$range+1;
	header("HTTP/1.1 206 Partial Content");
	header("Content-Length: $partial_length");
	header("Content-Range: bytes ".($range-$range_end/$size));
}
else {
	$partial_length = $size;
	header("Content-Length: $partial_length");
}

// output file
$bytes_sent = 0;
if ( $fp = fopen($file,"r") ) {
	// fast forward within file, if requested
	if (isset($_SERVER['HTTP_RANGE'])) fseek($fp,$range);
	// read and output the file in chunks
	while( !feof($fp) AND (!connection_aborted()) AND ($bytes_sent < $partial_length) ) {
		$buffer = fread($fp,$chunksize);
		print($buffer);
		flush();
		$bytes_sent += strlen($buffer);
	}
	fclose($fp);
}
else die("Unable to open file.");

// end the script
exit();

?>